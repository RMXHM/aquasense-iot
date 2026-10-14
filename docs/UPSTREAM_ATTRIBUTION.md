# Upstream Attribution

## Provenance

AquaSense began as a fork of:

- **Project:** hladinomer-studna-scripty ("Watmonitor" water level monitor)
- **Author:** Ing. Martin Chlebovec (`martinius96`)
- **Licence:** MIT
- **URL:** <https://github.com/martinius96/hladinomer-studna-scripty>

Upstream Git history was **not** carried into this repository; it was exported to a local
Git bundle for reference. The original copyright and licence notices are preserved in
`LICENSE` and `THIRD_PARTY_LICENSES/`, as the MIT licence requires.

## What was retained, and why

Only material that is genuinely useful **reference** for future physical hardware was kept.
It lives under `firmware/reference/` and is **not compiled, imported, or executed** by any
part of the running AquaSense application.

### Retained (reference only)

| Path | Upstream origin | Purpose |
| --- | --- | --- |
| `firmware/reference/sketches/upstream-wokwi-HCSR04.ino` | upstream Wokwi HC-SR04 sketch | Reference for TRIG/ECHO handling |
| `firmware/reference/sketches/upstream-ESP32_VL53L1X.ino` | upstream VL53L1X sketch | Reference for the ToF/I2C variant |
| `firmware/reference/sketches/upstream-ULP_WiFi-HCSR04.ino` | upstream ULP sketch | Reference for ultra-low-power deep sleep |
| `firmware/reference/sketches/upstream-RTOS-ESP32.ino` | upstream FreeRTOS sketch | Reference for a task-based design |
| `firmware/reference/libs/vl53l1x-arduino-master/` | (c) Rob Tillaart / pololu, BSD-3-Clause | ToF driver, licence intact |

### Removed from the product

- All upstream `examples/` variants not needed as reference (Ethernet, Sigfox, LoRa/LoRaWAN,
  ESP8266, Arduino AVR, HTTPS variants, RTOS and ULP variants beyond the four retained).
- The upstream multilingual static web presentation (`docs/` HTML frontend, QR pages,
  MATLAB tooling) and all upstream branding.
- The upstream `Simulation_Wokwi` project, replaced by our own `simulation/wokwi/`.
- The upstream `Tools_Integrations` directory (ThingsBoard, Power BI, Grafana connectors).
- A stray root-level file named `o` - an accidental `less` help/pager dump containing binary
  garbage, verified as unrelated to the project before deletion.

## No upstream endpoints remain

The upstream firmware reported to `hladinomer.eu` / `arduino.clanweb.eu`. No AquaSense
application code, firmware, or simulator references those hosts, and no code depends on
Watmonitor's PHP endpoints or MySQL schema. AquaSense's own `POST /api/v1/telemetry`
contract was written independently.

Ideas taken (not code): measuring distance to the water surface, deep sleep between
readings, and treating a zero echo as a failed measurement.

## Licence inventory

| Component | Licence | Location |
| --- | --- |
| AquaSense application code (backend, frontend, simulator, our Wokwi firmware) | Original to this project | repository |
| Upstream hladinomer-studna-scripty (MIT, (c) 2021-2024 Ing. Martin Chlebovec) | MIT | `LICENSE`, retained reference sketches |
| NewPing ((c) Tim Eckel) | **Custom / restrictive - NOT open source** | **Not included.** Deliberately excluded: its licence forbids forks and derivative works |
| VL53L1X Arduino library (BSD-3-Clause, derived from ST's API) | BSD-3-Clause | `THIRD_PARTY_LICENSES/VL53L1X-LICENSE.txt` |
| Laravel, React, Vite, Tailwind | MIT | managed by `composer.json` / `package.json` |

## Obligations if you redistribute

- Keep `LICENSE` and the upstream copyright notice with any distribution.
- Keep `THIRD_PARTY_LICENSES/` and the licence headers inside
  `firmware/reference/libs/` intact if you ship that reference material.
- Do **not** add the upstream `NewPing` library to this project - its licence forbids
  forks and derivative works, so vendoring it would create an obligation we cannot meet.
- Attributing AquaSense as an original product is accurate for the application code; do
  not strip the MIT/BSD notices from the retained third-party libraries.
