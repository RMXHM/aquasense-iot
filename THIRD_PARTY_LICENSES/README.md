# Third-party licences

AquaSense's own application code (backend, frontend, `simulation/software/simulate.php`,
and `simulation/wokwi/sketch.ino`) is original to this project.

Third-party and inherited material is documented in
[`docs/UPSTREAM_ATTRIBUTION.md`](../docs/UPSTREAM_ATTRIBUTION.md).

| File | Component | Licence |
| --- | --- | --- |
| `VL53L1X-LICENSE.txt` | VL53L1X Arduino driver (pololu, derived from ST's API) | BSD-3-Clause |
| `VL53L1X-library.properties` | VL53L1X library metadata | BSD-3-Clause |

## Not included, and why

The upstream project bundled the **NewPing** ultrasonic library. It was **deliberately not
retained**: its header states that forks and derivative works are *not* permitted without
the author's permission, which makes it unsafe to vendor into a derivative product even as
reference material. Nothing in AquaSense depends on it. If you need it for a physical
prototype, install it separately from the Arduino Library Manager under its own terms.

The upstream MIT licence for `hladinomer-studna-scripty` itself is preserved at the
repository root in `LICENSE`.
