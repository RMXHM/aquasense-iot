# Sensor Calibration and Water Maths

## What the sensor actually measures

An ultrasonic sensor mounted at the top of a tank measures the **distance to the water
surface** - not the depth of the water, and certainly not litres. AquaSense keeps that
distinction at every layer: the device reports `distance_cm` and nothing else.

```
        tank top (sensor mounting point)
        |<-------- d --------->|          d = distance_cm (what the sensor reports)
        +---------------------+  ^
        |                     |  |
        |      water surface  |  |
        |      ~~~~~~~~~~~~   |  |
        |                     |  |  h = water_height_cm (what we want)
        |                     |  |
        |                     |  v
        +---------------------+
              tank bottom

        <------- H -------->            H = sensor_height_cm (calibrated, sensor -> bottom)
```

### Step 1 - water height

```
water_height_cm = sensor_height_cm - distance_cm
```

`sensor_height_cm` is the **calibrated distance from the sensor face down to the tank
bottom**. It is a stored configuration value, not something the sensor can measure, and it
is the single most important calibration constant. If it is wrong, every derived number is
wrong by the same offset.

### Step 2 - percentage full

```
percent_full = water_height_cm / height_cm * 100
```

Clamped to 0-100%. `height_cm` is the tank's internal height.

### Step 3 - volume

Volume is cross-sectional area x water height. The area depends on the shape, and AquaSense
computes it from the configured geometry rather than assuming a linear height/volume ratio.

**Vertical cylinder** - area is constant with depth, so volume *is* linear in height:

```
area_cm2 = pi * r^2
volume_litres = area_cm2 * water_height_cm / 1000
```

**Rectangular tank:**

```
area_cm2 = length_cm * width_cm
volume_litres = area_cm2 * water_height_cm / 1000
```

**Unit conversions** (`LevelCalculator::cm3ToLitres`, `litresToCubicMeters`):
1 litre = 1000 cm3; 1 m3 = 1000 litres.

### Shapes we deliberately do not compute

Only `cylinder` and `rectangle` are implemented, and `crossSectionCm2()` **throws**
`InvalidArgumentException` for anything else. This is intentional: a conical, horizontal
cylindrical, or irregular tank does not have a constant cross-section, so volume is *not*
linear with height and a single area factor would produce confident, wrong litres.

For such a tank, either supply a measured capacity and treat percentage as the primary
figure, or add a shape-specific area function - do not reuse the linear one.

## When NOT to claim an exact volume

The dashboard avoids asserting a precise litre figure when:

- the shape is unsupported, so no area can be derived;
- required dimensions are missing (a cylinder with no `diameter_cm`, a rectangle with no
  `length_cm`/`width_cm`);
- the reading is invalid or the sensor has no echo;
- the tank has never been calibrated against a known volume.

A calculated figure is labelled as an **estimate** derived from configured geometry.

## Validation and error handling

`backend/app/Domain/ReadingValidator.php` rejects or flags a reading when:

- the distance is not numeric, or is missing;
- the distance is `0` - treated as a **missing echo**, the standard HC-SR04 failure mode,
  stored as invalid rather than read as an empty tank;
- the distance falls outside the sensor's plausible range;
- the change from the previous valid reading is physically impossible in the elapsed time
  (`sensor_status: implausible_drop`);
- the reading is older than the configured staleness window.

Invalid readings are still **persisted** with `valid: false`, so gaps appear in the history
chart instead of silently vanishing.

## Alert interpretation

Alert wording is deliberately conservative. A sudden drop is reported as an *unexplained
change* and explicitly states it is **not** automatically a leak - an open valve, a burst
fitting, or a displaced sensor produce the same signature. Confirming a leak needs a
person.

## Calibrating a physical installation

1. Measure from the sensor face down to the tank bottom. Enter this as
   `sensor_height_cm`. Do not guess it from the datasheet.
2. Measure the empty-tank reading: with the tank empty, the sensor should report
   approximately `sensor_height_cm`. Correct the constant if it does not.
3. Set `height_cm` to the tank's internal height.
4. Enter the geometry (`diameter_cm`, or `length_cm`/`width_cm`).
5. Cross-check against reality: fill to a known volume (for example by meter reading) and
   confirm the reported litres match. Adjust `sensor_height_cm` for any constant offset.
6. Verify the sensor does not sit inside its own **blind zone** (typically the first
   ~2-5 cm); if the full level puts the surface inside it, raise the mount.
7. Set `low_water_threshold_percent` and `high_water_threshold_percent` from real usage.

## Current verification status

- The conversion formulae are **unit tested**
  (`backend/tests/Unit/LevelCalculatorTest.php`) against representative dimensions.
- Boundary and invalid-input behaviour is **unit tested**.
- **No measurement has been validated against a real tank** - no hardware was available.
  Real-world accuracy is therefore unverified.
