# AquaSense Hardware Components

Component availability is classified for every part:

1. **Found in upstream source** - present in the retained `firmware/reference/` code.
2. **Confirmed in Wokwi** - verified against current Wokwi documentation with an exact part ID.
3. **Intended physical prototype** - planned for real hardware, not simulated.
4. **Not yet verified** - no evidence either way yet.

## Wokwi search names (exact)

Search these strings verbatim in the Wokwi "Add Part" dialog:

| Search term | Exact part ID for `diagram.json` | Status |
| --- | --- | --- |
| `ESP32-S3 DevKitC` | `wokwi-esp32-s3-devkitc-1` | Confirmed in Wokwi |
| `HC-SR04` | `wokwi-hc-sr04` | Confirmed in Wokwi |
| `LED` (red/green) | `wokwi-led` | Confirmed in Wokwi |
| `Resistor` | `wokwi-resistor` | Confirmed in Wokwi |
| `WiFi Access Point` | `wokwi-wifi-ap` | Confirmed in Wokwi (requires paid plan) |
| `VL53L0X` / `VL53L1X` | - | **No Wokwi part exists** |

Sources: <https://docs.wokwi.com/guides/esp32> (board list),
<https://docs.wokwi.com/parts/wokwi-hc-sr04> (pins and behaviour).
The VL53L0X part URL returns HTTP 404 and no ToF sensor appears in the parts index, so
the ToF path cannot be simulated in Wokwi today.

---

## 1. ESP32-S3-DevKitC-1 microcontroller

- **Wokwi part ID:** `wokwi-esp32-s3-devkitc-1` (status: confirmed in Wokwi)
- **Chip:** ESP32-S3, dual-core Xtensa LX7, Wi-Fi + BLE
- **Role:** reads the sensor, computes the reported distance, deep-sleeps between
  readings, and POSTs telemetry.
- **Why S3:** it is the project's target MCU and Wokwi supports it natively. It also has
  USB Serial/JTAG, so `Serial` output can be monitored without extra wiring.
- **Voltage:** 3.3 V logic. This matters - see the level shifter note on the HC-SR04.

## 2. HC-SR04 ultrasonic distance sensor

- **Wokwi part ID:** `wokwi-hc-sr04` (status: confirmed in Wokwi; also found in upstream
  source as the sensor the original Wokwi simulation used)
- **Pins:** `VCC`, `TRIG`, `ECHO`, `GND`
- **How it works:** drive `TRIG` high for >=10 us to start a measurement, then measure the
  length of the `ECHO` high pulse. Distance in cm = `pulse_us / 58`.
- **Simulated range:** 2-400 cm, adjustable with the on-screen slider or the `distance`
  attribute (default `"400"`).
- **Code reference:** `firmware/reference/sketches/upstream-wokwi-HCSR04.ino`.

### The HC-SR04 is a simulation component, NOT the field solution

An HC-SR04 in an open frame is **not waterproof** and is not suitable for installation over
a water tank. It is used here because Wokwi simulates it natively, which makes the signal
chain easy to demonstrate. It is not being proposed as the final physical sensor.

**Physical wiring caution:** the real HC-SR04 is a 5 V part whose `ECHO` pin outputs 5 V.
The ESP32-S3 is 3.3 V logic and is **not 5 V tolerant**, so a level shifter or divider is
mandatory on `ECHO` in hardware. The simulated part does not model this, and our sketch
carries a warning comment at the pin definition.

## 3. JSN-SR04T waterproof ultrasonic sensor (future physical sensor)

- **Status:** intended physical prototype; **not** available in Wokwi.
- **Why:** same trigger/echo measurement principle and pinout as the HC-SR04, but the
  transducer is potted and IP67-rated, so it can be mounted above an open tank.
- **Implication for firmware:** because it uses the same TRIG/ECHO interface, the same
  `pulseIn`-based measurement code works unchanged; only the mounting height and
  calibration constants differ. This is why the sketch keeps `SENSOR_HEIGHT_CM` a
  separately tunable value.
- Wokwi cannot simulate it, so any JSN-SR04T validation needs real hardware.

## 4. VL53L1X time-of-flight laser distance sensor

- **Status:** found in upstream source (`firmware/reference/sketches/upstream-ESP32_VL53L1X.ino`,
  library `firmware/reference/libs/vl53l1x-arduino-master/`), **not simulatable in Wokwi**
  (no part exists).
- **Interface:** I2C (SDA/SCL), not TRIG/ECHO.
- **Role in a physical build:** a laser ToF alternative to ultrasound - a narrow beam is
  less affected by tank wall echoes, condensation and steam, which improves accuracy in
  an enclosed tank.
- **Licence note:** its driver is BSD-3-Clause and derived from ST's API. The notice is
  retained at `THIRD_PARTY_LICENSES/VL53L1X-LICENSE.txt`. Unlike the upstream `NewPing`
  library, BSD-3-Clause permits modification and redistribution with attribution.

## 5. Status LED (optional, present in the Wokwi diagram)

- **Wokwi part ID:** `wokwi-led` + `wokwi-resistor` (confirmed in Wokwi)
- **Purpose:** visual heartbeat so a judge can see the device is awake and reporting
  without opening the serial monitor.

## Wiring used in `simulation/wokwi/diagram.json`

| HC-SR04 pin | ESP32-S3 pin | Notes |
| --- | --- | --- |
| `VCC` | `5V` | See the level shifter note above |
| `GND` | `GND` | |
| `TRIG` | GPIO 5 | Output from the MCU |
| `ECHO` | GPIO 6 | **Input. 5 V on real hardware - needs a divider/level shifter** |

These pins match `PIN_TRIG` / `PIN_ECHO` in `simulation/wokwi/sketch.ino`, so the
simulated circuit and the firmware cannot drift apart.

## Library requirements

| Library | Needed for | Status |
| --- | --- |
| Arduino core for ESP32 | Any ESP32 build | Required |
| `VL53L1X` (pololu) | ToF variant only | Retained at `firmware/reference/libs/vl53l1x-arduino-master/` (BSD-3-Clause) |
| `WiFi.h`, `HTTPClient.h`, `WiFiClientSecure.h` | ESP32 Arduino core built-ins | No extra dependency |

Our Wokwi firmware uses **only the Arduino core's built-in libraries** - `pulseIn()` for the
echo timing and `HTTPClient` for the POST - so it carries no third-party firmware dependency.

**Note on NewPing:** the upstream project bundled the popular `NewPing` ultrasonic library.
We deliberately did **not** retain it: its header states that forks and derivative works are
not permitted, which makes it unsuitable to vendor into a new product even as reference
code. If you want it for a physical prototype, install it separately from Arduino Library
Manager under its own terms. Nothing in AquaSense requires it.

## Unverified items

- Actual accuracy of any sensor over a real tank (no hardware available).
- Whether `ECHO` timing behaves identically on the simulated and real parts.
- Behaviour in a humid enclosed tank with condensation or wall echoes.

All of the above require the physical ESP32-S3 + sensor and remain **not yet verified**.
