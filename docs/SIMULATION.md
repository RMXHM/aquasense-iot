# AquaSense Simulation

Two independent simulation paths exist. Only the first has been verified end-to-end.

## 1. Software simulator - verified

`simulation/software/simulate.php` is a standalone PHP process that impersonates a
device. It posts to `POST /api/v1/telemetry` using exactly the payload real firmware will
use: **a distance in centimetres**, plus battery and RSSI diagnostics. It performs no
level maths - all conversion happens server-side.

```bash
# from the repository root
php simulation/software/simulate.php --list
php simulation/software/simulate.php --tank=1 --scenario=consumption --interval=5
php simulation/software/simulate.php --tank=1 --scenario=refill --interval=5 --steps=20
php simulation/software/simulate.php --tank=1 --scenario=offline --duration=60

# point it elsewhere (env var or flag)
AQUASENSE_API=https://example.test/api/v1 php simulation/software/simulate.php --tank=1
```

Options: `--tank`, `--scenario`, `--interval` (seconds), `--steps`, `--duration`,
`--api`, `--list`, `--help`.

The script calls `GET /scenarios` first as a connectivity check, and fails with an
actionable message if the API is not reachable.

### Scenarios

Each scenario is a **deterministic function of step index**
(`backend/app/Domain/TankSimulator.php`), so the same scenario always produces the same
sequence of readings. There is no unrelated randomness.

| Scenario | Behaviour |
| --- | --- |
| `normal` | Steady state, small realistic variation. |
| `consumption` | Gradual, steady decline from ~72% downward. |
| `refill` | Level rises, triggering a refill-detected event. |
| `low_water` | Crosses the low-water threshold, raising a `low_water` warning. |
| `sudden_drop` | An implausibly fast fall, flagged as an **unexplained change, not a leak**. |
| `overflow_risk` | Approaches/exceeds the high-water threshold. |
| `sensor_error` | Emits invalid readings (missing echo / out-of-range). |
| `offline` | Stops reporting, so the tank goes stale and shows as offline. |

### In-browser simulation panel

The dashboard has a `SimulationPanel` (tank detail -> *Device & Settings*) to select a
scenario, run steps, run a backfill, pause/resume, and reset. It drives the same server
endpoints as the CLI, so both paths produce identical data.

## 2. Wokwi circuit - written, NOT executed

`simulation/wokwi/` contains our own virtual circuit so we do not depend on the upstream
author's external Wokwi project:

- `diagram.json` - `wokwi-esp32-s3-devkitc-1` + `wokwi-hc-sr04` and wiring
- `sketch.ino` - AquaSense firmware: read sensor, validate, deep sleep, POST telemetry
- `wokwi.toml` - firmware/ELF paths for a compiled build

### Wokwi networking limitation - important

**The Wokwi browser simulator cannot reach `http://127.0.0.1:8000` on your laptop.**

Browser JavaScript runs inside a sandboxed virtual device. `localhost` / `127.0.0.1`
inside that context resolves to the *simulated device*, not your machine. Wokwi
addresses this with the special gateway host **`host.wokwi.internal`**, but that name only
resolves when you are running the **Private IoT Gateway**, which requires a paid
(Hobby+/Pro) Wokwi plan. The free browser playground runs on the **Public Gateway**, which
provides outbound internet access only and does not expose your local machine.

So on the free tier the sketch *can* reach any public HTTPS endpoint, but **cannot** reach
`http://127.0.0.1:8000`.

Because of that:

- The sketch defaults to a public-looking placeholder endpoint
  (`https://api.aquasense.example/api/v1/telemetry`) that will never receive data.
- **No tunnel was created and no local service was exposed publicly.** Exposing your
  laptop or a private API to the internet is not an acceptable cost for a demo.
- The sketch's transmit path is therefore **unverified**. The measurement maths, the
  pin wiring, and the payload shape are documented in
  [`HARDWARE_COMPONENTS.md`](HARDWARE_COMPONENTS.md) and mirror the verified PHP
  simulator, but nothing has proven the Wi-Fi stack actually completes a POST.

**Verified substitute for the same data path:** run the software simulator against the
local API. It exercises the identical `POST /telemetry` contract, so everything downstream
of the radio - validation, conversion, alerts, storage, dashboard - is proven, and only
the Wi-Fi transmit leg remains untested.

If you later want Wokwi-to-local traffic, the options are a paid Wokwi plan using the
Private IoT Gateway with `host.wokwi.internal`, or a self-hosted tunnel you control.
Neither was set up here. The verified path meanwhile is the software simulator, which
uses the identical telemetry contract.

## 3. Verifying the full flow by hand

```bash
cd aquasense/backend && php artisan serve --host=127.0.0.1 --port=8000

# 1. seed a tank with history
curl -s -X POST http://127.0.0.1:8000/api/v1/tanks/1/simulate/backfill \
  -H 'Content-Type: application/json' -d '{"scenario":"consumption"}'

# 2. send one reading exactly as a device would
curl -s -X POST http://127.0.0.1:8000/api/v1/telemetry \
  -H 'Content-Type: application/json' -d '{
    "serial_number":"AQS-S3-0001",
    "device_token":"demo-device-token-0001",
    "measured_at":"2026-10-14T20:30:00+00:00",
    "distance_cm":97.5,"source":"hardware",
    "battery_percent":93,"wifi_rssi":-61
  }'

# 3. read the resulting state
curl -s http://127.0.0.1:8000/api/v1/tanks/1
```
