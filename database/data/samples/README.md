# Sample geography files

These files only demonstrate the formats `php artisan geo:import` accepts. The
codes, names and square "boundaries" are **made up** - they are not official
INEC data and must not be loaded into production.

| File | Level | Format |
|---|---|---|
| `states.csv` | `state` | CSV |
| `lgas.csv` | `lga` | CSV |
| `wards.geojson` | `ward` | GeoJSON with boundary polygons |
| `polling_units.csv` | `polling-unit` | CSV with latitude/longitude |

Import them top-down, because each level looks up its parent by code:

```sh
php artisan geo:import state database/data/samples/states.csv
php artisan geo:import lga database/data/samples/lgas.csv
php artisan geo:import ward database/data/samples/wards.geojson
php artisan geo:import polling-unit database/data/samples/polling_units.csv
```
