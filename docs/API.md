# AquaSense API v1

Base URL (local dev): `http://127.0.0.1:8000/api/v1`

Every endpoint below was exercised with `curl` against a running server during
development. Content type is JSON for requests and responses.

## The telemetry contract

This is the single contract shared by the software simulator, the Wokwi firmware, and
future real ESP32 firmware. A device reports a **distance**, never a litre value.

```json
POST /api/v1/telemetry
{
  "serial_number": "AQS-S3-0001",
  "device_token": "demo-device-token-0001",
  "measured_at": "2026-10-14T20:11:00+00:00",
  "distance_cm": 97.5,
  "source": "simulation",
  "battery_percent": 95,
  "wifi_rssi": -58,
  "firmware_version": "aquasense-sim/1.0.0"
}
```

| Field | Type | Required | Meaning |
| --- | --- | --- | --- |
| `serial_number` | string | yes | Device identity. Paired with `device_token`. |
| `device_token` | string | yes | Shared device secret. **Prototype weakness - see below.** |
| `measured_at` | ISO-8601 | yes | When the device took the measurement. Also sets `devices.last_seen_at`. |
| `distance_cm` | number | yes | Raw distance from sensor to water surface, in **centimetres**. The only physical measurement. |
| `source` | string | no | `simulation` or `hardware`. Defaults to the device's configured source. |
| `battery_percent` | int 0-100 | no | Battery diagnostic, if the device is battery powered. |
| `wifi_rssi` | number | no | Wi-Fi signal in dBm. |
| `firmware_version` | string | no | Recorded on the device. |

**Derived server-side, never sent by the device:** `water_height_cm`, `percent_full`,
`volume_litres`, `volume_cubic_meters`, `sensor_status`, `valid`.

`distance_cm = 0` is treated as a **missing echo** (a real HC-SR04 convention) and is
stored as an invalid reading with `sensor_status` set accordingly, not as an empty tank.

### Response

```json
{
  "status": "accepted",
  "reading": { "distance_cm": 97.5, "water_height_cm": 52.5, "percent_full": 35,
                "volume_litres": 593.6, "sensor_status": "ok", "valid": true },
  "alerts_raised": ["low_water"]
}
```

Invalid readings are still persisted (with `valid: false`) so the gap is visible in
history rather than silently disappearing.

## Endpoints

### `GET /api/v1/overview`
All tanks for the demo customer: id, name, geometry summary, current level, litres,
capacity, thresholds, monitoring flag, device serial/online state, staleness,
condition, battery, last-reading time, unacknowledged alert count.

### `GET /api/v1/tanks/{tank}`
Full tank detail: configuration, `current` reading block, and a `state` block with
`device_online`, `readings_stale`, `minutes_since_reading`, `unacknowledged_alerts`,
plus the top-level `condition` (`Normal`, `Low Water`, `High Water`, `Sensor Offline`,
`Monitoring Paused`).

### `GET /api/v1/tanks/{tank}/readings`
Measurement history, newest first, including invalid rows with their `sensor_status`.

### `GET /api/v1/tanks/{tank}/alerts`
Alert history with `type`, `severity`, `title`, `message`, `acknowledged`.

### `PATCH /api/v1/tanks/{tank}/settings`
```json
{ "name": "Household Tank", "shape": "cylinder", "height_cm": 150,
  "sensor_height_cm": 160, "diameter_cm": 120, "capacity_litres": 1696,
  "low_water_threshold_percent": 20, "high_water_threshold_percent": 90,
  "monitoring_enabled": true, "stale_after_minutes": 30 }
```
Changing geometry re-derives level and volume for subsequent readings - stored raw
distances are never rewritten.

### `POST /api/v1/alerts/{alert}/acknowledge`
Marks an alert acknowledged (sets `acknowledged_at`).

### `POST /api/v1/devices/claim`
Prototype activation flow.
```json
{ "serial_number": "AQS-S3-0001", "activation_code": "AQUA-2026-DEMO",
  "tank_id": 1, "tank_name": "Household Tank", "shape": "cylinder",
  "height_cm": 150, "sensor_height_cm": 160, "diameter_cm": 120 }
```
Verifies serial + activation code, rejects already-claimed serials (HTTP 422), and
associates the device with the named tank.

**Demo credentials (prototype only, deliberately insecure):**

| Serial | Activation code |
| --- | --- |
| `AQS-S3-0001` | `AQUA-2026-DEMO` |
| `AQS-S3-0002` | `AQUA-2026-DEMO2` |

The response carries an explicit notice that this is not a production-secure activation
system.

### `GET /api/v1/scenarios`
Lists simulator scenarios (`normal`, `consumption`, `refill`, `low_water`,
`sudden_drop`, `overflow_risk`, `sensor_error`, `offline`).

### `POST /api/v1/tanks/{tank}/simulate/step`
Advances the scenario by one reading. Body: `{"scenario":"refill","steps":10}`.

### `POST /api/v1/tanks/{tank}/simulate/backfill`
Seeds a full history for one scenario instantly (`{"scenario":"consumption"}`,
default 24 readings at 30-minute spacing) so the dashboard is never empty.

### `POST /api/v1/tanks/{tank}/simulate/reset`
Clears the tank's readings and alerts and returns it to a normal baseline.

## Authentication status

Device auth is a **shared bearer-style token** sent alongside the serial number.
There is no user authentication at all. Both are intentional prototype shortcuts and are
tracked in [`ARCHITECTURE.md#security-notes`](ARCHITECTURE.md#security-notes).
