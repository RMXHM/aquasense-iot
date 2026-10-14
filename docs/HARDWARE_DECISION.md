# Hardware Sensor Decision Note

**Status: decision not finalised — for review, not a validated selection.**

Development board on hand: **ESP32-S3-WROOM-1 (N16R8)**. This note records the
considerations for choosing a *real* household-tank level sensor. **No component has
been purchased, validated, or committed to firmware.**

## Requirements driving the choice

1. Reliable continuous water-level measurement.
2. Suits typical household tank depths (~1–2 m) and shapes (cylinder/rectangle).
3. Waterproofing and safe installation over an open tank.
4. Low power with replaceable batteries (the tank is often outdoors, away from mains).
5. Wi-Fi reporting with recovery after network outages.
6. Affordable and obtainable **in Morocco**.
7. Calibratable into estimated litres.

## Candidate A — Waterproof ultrasonic (e.g. JSN-SR04T / AJ-SR04M)

- **How it works:** time-of-flight of a sound pulse to the water surface; distance =
  surface-to-sensor gap. Same TRIG/ECHO interface as the simulated HC-SR04, so the
  existing measurement code and the `distance → litres` conversion apply unchanged.
- **Pros:** non-contact (never touches water → no fouling, no corrosion, minimal
  maintenance); range (~0.25–4 m) comfortably covers household tanks; the probe is
  potted/IP67; widely available and cheap in Morocco (common with Arduino kits).
- **Cons:** the beam widens with distance, so tank walls, lids, or condensation can
  produce spurious echoes; needs a clear vertical path; a blind zone (~2–5 cm) means the
  sensor must sit above the full level.
- **Power:** tens of mA during a ping, µA at rest — pairs well with deep sleep.

## Candidate B — Submersible hydrostatic pressure sensor (e.g. waterproof pressure
 transducer with 4–20 mA or analogue output, or a submersible level probe)

- **How it works:** water pressure at a fixed depth is proportional to the height of
  water above the probe; volume follows from the calibrated depth.
- **Pros:** immune to beam/echo problems, tank shape at the top, fog, and condensation;
  measures a true column height, so it is often more accurate in enclosed tanks;
  submersible probes are inherently waterproof.
- **Cons:** the probe hangs in the water (fouling/scaling possible over time, harder to
  service); an analogue/4–20 mA variant needs an ADC or converter, adding parts and
  calibration; a vented-cable or gauge-pressure setup must handle barometric drift;
  must not rest on the very bottom if sediment collects.
- **Power:** continuous or periodic sampling; an analogue front-end draws more than an
  ultrasonic ping, so battery design needs care.

## Preliminary lean

For a **battery-powered, low-maintenance household monitor**, a **waterproof ultrasonic
top-mount sensor (Candidate A)** is the lower-risk first step: it is non-contact,
needs no extra electronics, matches the conversion code we already have, and is the most
readily available and cheapest option in Morocco. A **submersible pressure probe
(Candidate B)** is the stronger choice where the tank is enclosed, humid, or the top has
an awkward geometry — at the cost of an in-water part and a more complex front-end.

The HC-SR04 stays labelled **simulation-only** until one of these is physically tested.

## Unresolved questions (need physical testing)

- Real accuracy over a full and an empty tank, and repeatability of the reading.
- Which waterproof ultrasonic housing actually withstands the local humidity/heat.
- Battery life under a realistic reporting interval with Wi-Fi on.
- Behaviour of Wi-Fi reconnection after an outage, and recovery of the deep-sleep cycle.
- Whether an analogue pressure front-end fits the power budget and BOM.
- Confirmation that a suitable submersible probe is locally obtainable at acceptable cost.

**Next step when hardware is available:** build a bench test on the ESP32-S3-WROOM-1,
calibrate `sensor_height_cm` against a known volume (see
[`CALIBRATION.md`](CALIBRATION.md)), and only then update firmware.
