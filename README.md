# 💧 AquaSense IoT

**Affordable, open-source household water-tank monitoring.**

AquaSense helps households understand how much water they have left, watch how it
changes over time, and get useful early warnings — all from a simple dashboard that
works on a phone or desktop. A distance sensor mounted at the top of the tank measures
the gap to the water surface; the backend turns that into a water level, an estimated
volume in litres, and a percentage of capacity.

The long-term goal is a low-cost device that measures water level, reports over Wi-Fi,
and gives the customer a dashboard reachable from anywhere with an internet connection.

> **⚠️ Demo data only.** Every tank in this repository is currently filled by a software
> simulator — these are **not** real-world sensor readings and are not suitable as
> evidence of a real water level. The dashboard labels this clearly throughout.

---

## ✨ What exists today (implemented)

- **Laravel / PHP API** — telemetry ingestion, validation, processing, and alerting.
- **SQLite development database** — tanks, devices, readings, and alerts, with a schema
  designed so MySQL/PostgreSQL is a straightforward swap later.
- **React + TypeScript + Vite + Tailwind CSS dashboard** — animated water-level
  visualization, overview cards, tank detail, history charts, alert list, and device
  settings.
- **Software sensor simulator** — a standalone PHP program that sends the *same* payload
  a real device would (a distance in centimetres), driving 8 deterministic scenarios.
- **Tank geometry & distance-to-level conversion** — server-side calculation of water
  height, estimated volume (litres and m³), and percentage for vertical-cylinder and
  rectangular tanks.
- **Measurement history, charts, and alert states** — low water, unusual drop, refill,
  overflow risk, sensor error, and stale/offline detection.
- **Device status handling** — online/offline and stale-reading detection, so an old
  value is never presented as live.
- **Demo seed data** and an in-browser simulation control panel for demonstrations.
- **Wokwi ESP32-S3 + HC-SR04 simulation** — our own virtual circuit and firmware.

## 🧭 Roadmap (planned, not yet built)

- Validated physical measurement accuracy on a real tank.
- Proven ESP32-S3 → AquaSense-server transmission over real Wi-Fi.
- Production customer authentication, device ownership, and secure activation.
- Telemetry authentication hardening, rate limiting, and HTTPS deployment.
- Deployment so remote monitoring works **without the customer's computer running**.
- Support for additional tank shapes (conical / horizontal / irregular).
- Battery-life and waterproofing validation for the physical device.

---

## 🌊 Long-term vision

AquaSense aims to grow beyond a single-tank monitor toward broader water-resource
management, using affordable ESP32-S3-based hardware:

- **Flow + level together:** pair tank-level sensing with a flow meter (e.g. a
  Hall-effect flow sensor) to distinguish refills from consumption and estimate usage.
- **Automated refill / control:** drive a relay-connected pump or solenoid valve to
  top up a tank or trough when it drops below a threshold.
- **Leak detection:** flag anomalies in consumption that suggest a leak or open valve.
- **Network & mapping dashboard:** visualize multiple tanks, zones, or reservoirs on a
  map, from household scale up to agricultural or municipal installations.

These are **aspirational** and not part of the current implementation. The telemetry
pipeline and dashboard built here are designed so these capabilities can be layered on
later without changing the core level-monitoring flow.

---

## 🏗️ Architecture

The data path is deliberately identical whether the source is the simulator today or a
real ESP32 tomorrow:

```mermaid
flowchart LR
    SIM[Software simulator<br/>(simulate.php)] -->|POST /api/v1/telemetry| API
    DEV[Future physical sensor<br/>ESP32-S3 + ultrasonic] -.->|same JSON payload| API
    API[Laravel API<br/>validate + process] --> CALC[Level & volume<br/>conversion]
    CALC --> ALERT[Alert engine]
    ALERT --> DB[(SQLite<br/>dev database)]
    DB --> UI[React dashboard]
```

**In words:** software simulator or future physical sensor → authenticated telemetry
endpoint → Laravel validation and processing → SQLite during development → React
dashboard. The device reports a **raw distance**; the server computes level and volume,
so tank geometry can be corrected in the dashboard without touching the device.

---

## 🚀 Run it locally (Arch Linux)

Three processes run in three terminals. Requires **PHP ≥ 8.3**, **Composer**, and
**Node.js ≥ 18**.

### Terminal 1 — Backend API

```bash
# System deps (if not already installed)
sudo pacman -S php php-sqlite composer nodejs npm

cd ~/Desktop/Projects/aquasense/backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seeder=AquaSenseDemoSeeder
php artisan serve --host=127.0.0.1 --port=8000
```

### Terminal 2 — Frontend dashboard

```bash
cd ~/Desktop/Projects/aquasense/frontend
npm install
npm run dev
```

Open **http://localhost:5173**. Vite proxies `/api` to the Laravel server, so the
browser stays on one origin (no CORS or public exposure needed).

### Terminal 3 — Software simulator (optional)

```bash
cd ~/Desktop/Projects/aquasense
php simulation/software/simulate.php --list
php simulation/software/simulate.php --tank=1 --scenario=consumption --interval=5
```

### Tests, type-check & build

```bash
# Backend test suite (from backend/)
./vendor/bin/phpunit

# Frontend TypeScript check (from frontend/)
npm run typecheck

# Frontend production build (from frontend/)
npm run build
```

### Reset the local demo database (safe, disposable)

```bash
cd ~/Desktop/Projects/aquasense/backend
php artisan migrate:fresh --seeder=AquaSenseDemoSeeder
```

> Only run the reset against the **local demo database**. It drops and re-seeds the
> SQLite file and must never be pointed at a database holding real customer data.

---

## 🗂️ Repository structure

```text
aquasense/
├── backend/             Laravel/PHP API + SQLite dev database
│   ├── app/Domain/      LevelCalculator, ReadingValidator, AlertEngine, TankSimulator
│   ├── app/Http/        API controller + routes
│   └── tests/           PHPUnit unit + feature tests
├── frontend/            React + TypeScript + Vite + Tailwind dashboard
├── simulation/
│   ├── software/        Standalone deterministic simulator (simulate.php)
│   └── wokwi/           Our ESP32-S3 + HC-SR04 virtual circuit & firmware
├── firmware/reference/  Retained upstream sensor reference code (MIT)
├── docs/                Architecture, API, simulation, hardware, calibration
└── THIRD_PARTY_LICENSES/ Third-party licence notices
```

---

## ⚠️ Limitations & honest status

- **Real hardware accuracy is not yet validated.** All current measurements come from
  the software simulator; none have been checked against a physical tank.
- **The simulated HC-SR04 is a software demonstration component**, not a confirmed
  waterproof household sensor. A waterproof sensor (e.g. JSN-SR04T or a submersible
  pressure-level sensor) still needs physical selection and testing — see
  [`docs/HARDWARE_COMPONENTS.md`](docs/HARDWARE_COMPONENTS.md).
- **ESP32-S3 → server transmission has not been demonstrated over real Wi-Fi.** The
  Wokwi browser sandbox cannot reach a laptop's `localhost`, and no public tunnel was
  created, so the radio/transmit path remains unverified. See
  [`docs/SIMULATION.md`](docs/SIMULATION.md).
- **Production hardening is incomplete:** customer authentication, device ownership,
  secure single-use activation, telemetry authentication with rate limiting, and HTTPS
  deployment are not finished. The demo uses a shared, committed token — prototype only.
- **Battery life and waterproofing require physical testing** before any product claim.
- The long-term product must support remote monitoring **without the customer's own
  computer staying on**; the current local setup does not provide that.

---

## 📄 Licensing & acknowledgements

AquaSense's application code (backend, frontend, simulator, and our Wokwi firmware) is
original to this project.

This project began as a fork of
[**hladinomer-studna-scripty (Watmonitor)**](https://github.com/martinius96/hladinomer-studna-scripty)
by Ing. Martin Chlebovec, released under the **MIT License**. The upstream copyright and
licence are retained in [`LICENSE`](LICENSE). Some firmware **reference material** is
preserved under [`firmware/reference/`](firmware/reference/) with its notices intact.
The **VL53L1X** driver is BSD-3-Clause; its notice is in
[`THIRD_PARTY_LICENSES/`](THIRD_PARTY_LICENSES/). Full provenance, what was kept/removed,
and licence obligations are documented in
[`docs/UPSTREAM_ATTRIBUTION.md`](docs/UPSTREAM_ATTRIBUTION.md).

> Note: the upstream **NewPing** ultrasonic library was deliberately **not** retained —
> its licence forbids forks/derivative works. AquaSense uses only the Arduino core's
> built-in libraries.

---

## 📚 Documentation

- [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) — components, data flow, security notes
- [`docs/API.md`](docs/API.md) — telemetry contract and endpoints
- [`docs/SIMULATION.md`](docs/SIMULATION.md) — simulator scenarios & Wokwi networking
- [`docs/HARDWARE_COMPONENTS.md`](docs/HARDWARE_COMPONENTS.md) — parts & Wokwi search names
- [`docs/CALIBRATION.md`](docs/CALIBRATION.md) — distance-to-litres maths
- [`docs/UPSTREAM_ATTRIBUTION.md`](docs/UPSTREAM_ATTRIBUTION.md) — provenance & licences
