# AquaSense Architecture

## Overview

AquaSense is a three-process system for a local prototype:

1. **`backend/`** - Laravel API (PHP 8.2+) backed by SQLite. Owns all persistence,
   the measurement maths, reading validation, and alert generation.
2. **`frontend/`** - React 18 + TypeScript + Vite dashboard. Read/display only; it
   performs **no** measurement maths and invents no data.
3. **`simulation/software/simulate.php`** - a standalone PHP process that behaves
   like a physical device. It sends **distance in centimetres** through the same
   `POST /api/v1/telemetry` endpoint real ESP32 firmware will use.

A fourth, optional path is `simulation/wokwi/` - our own browser-based virtual circuit.
See [`SIMULATION.md`](SIMULATION.md#wokwi-networking-limitation) for why it cannot reach
your laptop directly.

## The data path

```
  distance (cm)              AquaSense API                        Dashboard
       |                    POST /api/v1/telemetry                     |
       |                            |                                 |
  simulator ----------------------->|                                 |
  real ESP32 ---------------------->|                                 |
                                   v                                  |
                        ┌──────────────────────┐                       |
                        │ ReadingValidator     │ reject out-of-range,  │
                        │  - numeric distance  │ missing echo (0),      │
                        │  - plausibility vs   │ impossible jumps       │
                        │    previous reading  │                       │
                        └──────────┬───────────┘                       |
                                   v                                   |
                        ┌──────────────────────┐                       |
                        │ LevelCalculator      │ water_height =         │
                        │                      │   sensor_height - d     │
                        │  percent / litres /  │ volume = f(geometry)    │
                        │  cubic metres        │                        │
                        └──────────┬───────────┘                       |
                                   v                                   |
                        ┌──────────────────────┐                       |
                        │ AlertEngine          │ low_water, sudden_drop, |
                        │  (30-min dedupe)     │ overflow_risk, refill,  │
                        │                      │ sensor_error, offline   │
                        └──────────┬───────────┘                       |
                                   v                                   |
                        ┌──────────────────────┐      GET /tanks/{id}  │
                        │  SQLite              │<----------------------┘
                        │  readings  alerts    │
                        │  devices.last_seen_at│
                        └──────────────────────┘
```

## Where each responsibility lives

| Responsibility | File |
| --- | --- |
| Raw sensor measurement (simulator) | `backend/app/Domain/TankSimulator.php`, `simulation/software/simulate.php` |
| Raw sensor measurement (firmware) | `simulation/wokwi/sketch.ino` |
| Distance -> water height, percent, volume | `backend/app/Domain/LevelCalculator.php` |
| Reading validation | `backend/app/Domain/ReadingValidator.php` |
| Alert rules | `backend/app/Domain/AlertEngine.php` |
| Telemetry ingest + persistence | `backend/app/Services/TelemetryService.php`, `TelemetryServiceHelpers.php` |
| Scenario stepping / history backfill | `backend/app/Services/SimulationService.php` |
| HTTP routing + request shaping | `backend/app/Http/Controllers/Api/AquaSenseController.php`, `backend/routes/api.php` |
| Device authentication (telemetry + claim) | `AquaSenseController::telemetry()`, `AquaSenseController::claimDevice()` |
| Measurement scheduling (software) | `simulate.php --interval` / `--steps` |
| Measurement scheduling (firmware) | `sketch.ino` deep sleep `TIME_TO_SLEEP_SECONDS` |
| Stale / offline determination | `AquaSenseController::tankSummary()` |

**Key design decision:** all level/volume conversion happens **server-side**. A device
reports a distance and nothing else, so firmware never needs to know the tank geometry
and the geometry can be corrected in the dashboard without touching a device.

## Data model

```
tanks      id, customer_id, name, shape, height_cm, sensor_height_cm,
           diameter_cm, length_cm, width_cm, capacity_litres,
           low_water_threshold_percent, high_water_threshold_percent,
           monitoring_enabled, stale_after_minutes, demo
devices    id, tank_id, serial_number (unique), device_token (unique),
           activation_code, activation_code_used_at, last_seen_at,
           firmware_version, source, battery_percent, wifi_rssi,
           reporting_interval_minutes
readings   id, tank_id, device_id, measured_at, distance_cm, water_height_cm,
           percent_full, volume_litres, volume_cubic_meters, source,
           sensor_status, valid, battery_percent, wifi_rssnalerts    id, tank_id, type, severity, title, message, acknowledged_at
```

Only `tanks` and `devices` hold configuration. `readings` is append-only history and
stores both the raw `distance_cm` and the derived values, so a geometry change can be
re-derived without re-collecting data.

## Storage layer

SQLite is used for zero-setup local development (`DB_CONNECTION=sqlite`, the Laravel
default). Migrations use only portable column types - no SQLite-specific SQL and no
MySQL `enum` - so switching to MySQL or PostgreSQL later is a matter of changing
`DB_CONNECTION` plus credentials. `capacity_litres` is stored as a decimal, not an
integer, so fractional capacities survive the switch.

## Frontend structure

```
src/
├── api/types.ts        TypeScript mirrors of every API response
├── api/client.ts       fetch wrapper; all calls go to the same origin (/api)
├── hooks/usePolling.ts interval polling with loading/error/refresh state
├── lib/format.ts       litres, cm, m3 and relative-time formatting
├── components/
│   ├── TankVisual.tsx     animated water column (CSS transition on transform/height)
│   ├── TankCard.tsx       overview card
│   ├── HistoryChart.tsx   inline SVG level history + threshold line
│   ├── SimulationPanel.tsx dev/demo scenario controls
│   ├── DevicePanel.tsx     device + tank settings
│   └── Ui.tsx              shared card, badge, button, state primitives
└── pages/              Overview, TankDetail, Activate
```

The Vite dev server proxies `/api` to `http://127.0.0.1:8000` (`frontend/vite.config.ts`).
This keeps the browser on one origin, so **no CORS policy and no public exposure** are
needed during development.

**Animation integrity:** `TankVisual` animates its rendered height toward the latest
committed reading. It never generates readings, never interpolates between two stored
values as if they were measurements, and never overwrites the displayed litres. There is
exactly one source of truth - the server - and the animation only eases the eye toward it.

## Security notes

These are prototype gaps, documented rather than hidden:

- **No user authentication.** `GET /api/v1/overview` returns every demo tank. There is no
  login, session, or tenancy check. `AquaSenseDemoSeeder` seeds a customer record with a
  random unknown password purely so the schema is production-shaped.
- **Seeded shared device token.** `demo-device-token-0001` is committed in the seeder.
  Anyone with the source can post telemetry for the demo device. This is deliberate for a
  local demo and must be replaced with per-device credentials before any real deployment.
- **Activation codes are static and reusable-once.** `AQUA-2026-DEMO` is a fixed string in
  the seeder; the API answers with an explicit notice that production must issue
  single-use, expiring codes over a verified channel. It is not a secure claim system.
- **No rate limiting** on `/api/v1/telemetry`, and **no replay protection** - a repeated
  `measured_at` will simply append another row.
- **No TLS.** The dev server is plain HTTP bound to `127.0.0.1`.

Required before production: per-device credentials (ideally mTLS or signed JWTs), rate
limiting and idempotency keys on telemetry, HTTPS everywhere, real user accounts with
tenancy enforcement, and activation codes issued out-of-band.
