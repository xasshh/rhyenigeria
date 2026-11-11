# Rhye Nigeria — backend

API backend for rhyenigeria.org. Field agents carry a mobile app or PWA that
reports their location while they are on shift and submits data collected in
the field. Coordinators watch a live map of the agents in their area, organised
by state, LGA, ward and polling unit.

This repository is the API only. There are no front-end pages beyond Laravel's
default welcome page.

- Laravel 13 on PHP 8.5
- PostgreSQL with PostGIS (all geometry is SRID 4326)
- Redis for cache, sessions and the queue
- Sanctum API tokens
- Reverb for live position updates over WebSockets

## Contents

- [Local setup](#local-setup)
- [Roles and scoping](#roles-and-scoping)
- [API](#api)
- [Live updates](#live-updates)
- [Importing geography](#importing-geography)
- [Scheduled tasks and settings](#scheduled-tasks-and-settings)
- [Deployment](#deployment)

## Local setup

You need PHP 8.5 with the `pgsql`, `redis`, `mbstring`, `intl` and `gd`
extensions, Composer, Node 22+, PostgreSQL with PostGIS, and Redis.

```sh
git clone https://github.com/xasshh/rhyenigeria.git && cd rhyenigeria
createdb rhyenigeria
createdb rhyenigeria_testing        # used only by the test suite

composer install
cp .env.example .env                # then set DB_USERNAME / DB_PASSWORD, and give
                                    # REVERB_APP_ID, REVERB_APP_KEY and REVERB_APP_SECRET any random values
php artisan key:generate
php artisan migrate                 # the first migration enables the postgis extension
npm install && npm run build
```

The database user must be allowed to run `CREATE EXTENSION postgis`, or the
extension must already be enabled in both databases.

Create a super admin (the password must be at least 12 characters and is never
stored in a file):

```sh
SUPER_ADMIN_EMAIL=you@example.com SUPER_ADMIN_PASSWORD='a-long-password' \
  php artisan db:seed --class=SuperAdminSeeder
```

Optionally load the made-up sample geography (see
[Importing geography](#importing-geography)), then run everything:

```sh
composer run dev        # web server, queue worker, logs and Vite
php artisan reverb:start   # in a second terminal, for live updates
```

### Tests

```sh
php artisan test --compact
```

The tests need a real PostGIS database (`rhyenigeria_testing`); they do not run
on SQLite. They never touch the development database.

## Roles and scoping

| Role | Value | Can do |
|---|---|---|
| Super admin | `super_admin` | Everything, everywhere. Manages users and forms, reads the audit log. |
| Coordinator | `coordinator` | Sees agents, positions, trails and submissions inside their area only. |
| Field agent | `field_agent` | Accepts consent, runs their own shifts, posts locations and submissions. Sees only their own data. |

A coordinator's area is one state, one LGA or one ward. An agent is inside a
coordinator's area when the agent's own assigned area falls within it; a
submission is inside it when the polling unit it relates to does. A coordinator
with no area sees nothing. Scoping is applied in the queries themselves, so
lists, single records, exports and WebSocket channels all follow the same rule.

## API

All endpoints are under `/api`, send and return JSON, and (except login) need
`Authorization: Bearer <token>`. Times are ISO 8601; anything without an offset
is read as UTC, and everything is returned in UTC. Send `Accept:
application/json`.

Errors use the usual status codes: `401` no or bad token, `403` not allowed
(wrong role, outside your area, deactivated account, consent not accepted),
`409` conflicts with the current state (for example no shift in progress),
`422` validation, `429` rate limited.

The examples use:

```sh
API=https://rhyenigeria.org/api
TOKEN=...   # from the login call
```

### Authentication

| Method | Path | Notes |
|---|---|---|
| POST | `/auth/login` | Returns a token. 10 attempts per minute per email and IP. |
| POST | `/auth/logout` | Revokes the token used for the request. |
| GET | `/auth/me` | The signed-in user. |

```sh
curl -s $API/auth/login -H 'Accept: application/json' \
  -d email=agent@example.com -d password='...' -d device_name='Pixel 8'
```

```json
{ "token": "12|Xy...", "token_type": "Bearer",
  "user": { "id": 7, "name": "Ada Obi", "role": "field_agent", "area_level": "ward",
            "state_id": 1, "lga_id": 4, "ward_id": 31,
            "tracking_consent_accepted_at": null, "tracking_consent_version": null } }
```

### Consent and shifts (field agents)

Location is only collected while the agent has a shift in progress that they
started themselves, and only after they have accepted the tracking notice.

| Method | Path | Notes |
|---|---|---|
| GET | `/consent/tracking` | Whether the current notice version has been accepted, and when. |
| POST | `/consent/tracking` | Body `{"accepted": true}`. Records the time and notice version. |
| DELETE | `/consent/tracking` | Withdraws consent and ends any shift in progress. |
| GET | `/shifts/current` | The shift in progress, or `null`. |
| POST | `/shifts/start` | `201`; `403` without consent; `409` if one is already running. |
| POST | `/shifts/end` | Ends the shift. Agents can do this at any time. |

```sh
curl -s -X POST $API/consent/tracking -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' -d '{"accepted": true}'
curl -s -X POST $API/shifts/start -H "Authorization: Bearer $TOKEN"
```

### Location batches (field agents)

`POST /locations` takes 1 to 500 points. Devices should queue points while
offline and send them in batches when they reconnect.

```sh
curl -s $API/locations -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' -H 'Accept: application/json' -d '{
  "points": [
    { "uuid": "7c0b1c1e-3f0a-4f6e-9a55-0d3a4b1f2a01", "latitude": 6.6018, "longitude": 3.3515,
      "accuracy": 12.5, "recorded_at": "2026-11-11T09:15:00Z" },
    { "uuid": "7c0b1c1e-3f0a-4f6e-9a55-0d3a4b1f2a02", "latitude": 6.6021, "longitude": 3.3519,
      "accuracy": 9, "recorded_at": "2026-11-11T09:15:30Z" }
  ]
}'
```

```json
{ "received": 2, "stored": 2, "duplicates": 0, "outside_shift": 0, "invalid_time": 0, "shift_active": true }
```

- `uuid` is generated on the device, once per point. A UUID that has already
  been stored is counted under `duplicates` and otherwise ignored, so a batch
  can be re-sent safely after a dropped connection.
- Points may arrive in any order. The trail is always ordered by `recorded_at`,
  and the agent's latest position only moves forward in time.
- A point recorded outside all of the agent's shifts is dropped and counted
  under `outside_shift`; one time-stamped more than five minutes in the future
  is counted under `invalid_time`.
- The answer is `200` whenever the batch was understood. It is `403` if consent
  has not been accepted, and `409` if no shift is running and none of the points
  belonged to an earlier shift.
- Limited to 60 batches per minute per agent (`TRACKING_RATE_LIMIT_PER_MINUTE`).

The ward each point falls in and its nearest polling unit are worked out
afterwards by a queued job, which then broadcasts the agent's new position.

### Map (coordinators and super admins)

| Method | Path | Notes |
|---|---|---|
| GET | `/map/positions` | Latest position of every agent you may see, as a GeoJSON FeatureCollection. `?on_shift=1` limits it to agents on shift. |
| GET | `/agents/{id}/trail` | One agent's trail as GeoJSON. `from` and `to` set the window (default: the last 24 hours; at most 31 days). `points=0` returns only the line. Every call is written to the audit log. |

```sh
curl -s "$API/agents/7/trail?from=2026-11-11T06:00:00Z&to=2026-11-11T18:00:00Z" \
  -H "Authorization: Bearer $TOKEN"
```

```json
{ "type": "FeatureCollection",
  "features": [
    { "type": "Feature",
      "geometry": { "type": "LineString", "coordinates": [[3.3515, 6.6018], [3.3519, 6.6021]] },
      "properties": { "kind": "trail", "agent_id": 7,
                      "started_at": "2026-11-11T09:15:00Z", "ended_at": "2026-11-11T09:15:30Z" } },
    { "type": "Feature", "id": "7c0b1c1e-3f0a-4f6e-9a55-0d3a4b1f2a01",
      "geometry": { "type": "Point", "coordinates": [3.3515, 6.6018] },
      "properties": { "kind": "point", "recorded_at": "2026-11-11T09:15:00Z", "accuracy": 12.5,
                      "shift_id": 3, "ward_id": 31, "nearest_polling_unit_id": 412,
                      "nearest_polling_unit_distance_m": 84.2 } }
  ],
  "meta": { "agent": { "id": 7, "name": "Ada Obi" }, "from": "...", "to": "...", "point_count": 2 } }
```

Field agents may call the trail endpoint for themselves only.

### Field data

Submissions carry a GPS point, the polling unit they relate to, the time they
were collected, optional photos, and free-form `answers` stored as JSON. No
specific form has been defined yet. A super admin can define one later with
`POST /forms`; a submission that names a form must include that form's required
answers, and a submission with no form accepts any answers.

| Method | Path | Notes |
|---|---|---|
| GET | `/forms` | Form definitions. |
| POST | `/forms` | Super admin. `key`, `name`, optional `description`, `fields`. |
| PATCH | `/forms/{id}` | Super admin. Set `is_active` to `false` to close a form. |
| POST | `/submissions` | Field agent. See below. |
| POST | `/submissions/{id}/photos` | Field agent, own submissions. Multipart field `photo`. |
| GET | `/submissions` | Scoped list. Filters: `form_id`, `user_id`, `polling_unit_id`, `ward_id`, `lga_id`, `state_id`, `from`, `to`, `per_page`. |
| GET | `/submissions/{id}` | One submission. |
| GET | `/submissions/{id}/photos/{photo}` | The photo file (needs the token; photos are not public). |
| GET | `/submissions/export` | Coordinators and super admins. CSV with the same filters, at most 50,000 rows. Every export is written to the audit log. |

```sh
curl -s $API/submissions -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' -H 'Accept: application/json' -d '{
  "uuid": "b9a3f6f2-58c1-4a0e-8a0e-2f1d6c7e9a10",
  "polling_unit_id": 412,
  "latitude": 6.6018, "longitude": 3.3515, "accuracy": 8,
  "collected_at": "2026-11-11T10:02:00Z",
  "answers": { "turnout": 143, "notes": "Materials arrived late" }
}'
```

With photos, send `multipart/form-data` with `photos[]` files (up to 5, 8 MB
each; JPEG, PNG, WebP or HEIC) and `answers` as a JSON string. As with
locations, `uuid` makes the request safe to retry: sending it again returns the
stored record with `"duplicate": true`.

A form definition:

```json
{ "key": "polling-unit-report", "name": "Polling unit report",
  "fields": [ { "name": "turnout", "label": "Turnout", "type": "number", "required": true } ] }
```

Only `name` and `required` are enforced by the API. The rest of a field
definition is for the app that renders the form.

### Geography lookups

| Method | Path |
|---|---|
| GET | `/states` |
| GET | `/states/{id}/lgas` |
| GET | `/lgas/{id}/wards` |
| GET | `/wards/{id}/polling-units` |

### Users and audit log

| Method | Path | Notes |
|---|---|---|
| GET | `/users` | Scoped list. Filters: `role`, `search`, `per_page`. |
| GET | `/users/{id}` | One user, if you may see them. |
| POST | `/users` | Super admin. `name`, `email`, `password` (10+ characters), `role`, optional `phone`, and `area_level` (`state`, `lga` or `ward`) with `area_id`. |
| PATCH | `/users/{id}` | Super admin. Deactivating a user or changing their password signs them out everywhere. |
| GET | `/audit-logs` | Super admin. Who viewed which trail or exported data, and when. |

```sh
curl -s $API/users -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' -H 'Accept: application/json' -d '{
  "name": "Ada Obi", "email": "ada@example.com", "password": "a-long-password",
  "role": "field_agent", "area_level": "ward", "area_id": 31
}'
```

## Live updates

Each new latest position is broadcast as the event `agent.position.updated` on
private channels:

| Channel | Who may join |
|---|---|
| `private-tracking.all` | Super admins |
| `private-tracking.state.{id}` | Super admins; coordinators whose area covers that state |
| `private-tracking.lga.{id}` | Super admins; coordinators whose area covers that LGA |
| `private-tracking.ward.{id}` | Super admins; coordinators whose area covers that ward |
| `private-tracking.agent.{id}` | Super admins; coordinators who may see that agent |

Field agents cannot join any tracking channel. A coordinator normally
subscribes to the one channel for their own area.

Reverb speaks the Pusher protocol. Channel authorisation is at
`POST /api/broadcasting/auth` and uses the same bearer token as the rest of the
API:

```js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
window.Pusher = Pusher;

const echo = new Echo({
  broadcaster: 'reverb',
  key: REVERB_APP_KEY,            // public; the secret never leaves the server
  wsHost: 'rhyenigeria.org', wssPort: 443, forceTLS: true,
  enabledTransports: ['ws', 'wss'],
  authEndpoint: 'https://rhyenigeria.org/api/broadcasting/auth',
  auth: { headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' } },
});

echo.private('tracking.lga.4').listen('.agent.position.updated', (position) => {
  // { agent: { id, name, phone, state_id, lga_id, ward_id }, latitude, longitude, accuracy,
  //   recorded_at, ward: { id, code, name } | null,
  //   nearest_polling_unit: { id, code, name, distance_m } | null }
});
```

## Importing geography

```sh
php artisan geo:import {state|lga|ward|polling-unit} path/to/file.{csv|geojson} [--dry-run]
```

Import top-down (states, then LGAs, wards, polling units), because each row
finds its parent by code.

| Level | Columns (CSV) or properties (GeoJSON) |
|---|---|
| `state` | `code`, `name` |
| `lga` | `state_code`, `code`, `name` |
| `ward` | `state_code`, `lga_code`, `code`, `name` |
| `polling-unit` | `state_code`, `lga_code`, `ward_code`, `code`, `name`, and `latitude` + `longitude` (or a GeoJSON Point) |

- Column and property names are not case-sensitive.
- Boundaries are optional at every level: a Polygon or MultiPolygon geometry in
  GeoJSON, or a `boundary_wkt` column in CSV. Coordinates must be WGS 84
  longitude/latitude.
- Rows are matched on their code within their parent, so the command is safe to
  re-run. Running the same file again changes nothing; running a corrected file
  updates only the rows that differ. A row without geometry never erases a
  boundary or location that is already stored.
- Slightly defective polygons (self-touching rings and similar) are repaired on
  the way in. A row whose geometry cannot be used, whose parent is missing, or
  which has no code or name is skipped and listed with its row number; the rest
  of the file still imports.
- The command prints how many rows were created, updated and skipped (unchanged
  or invalid). `--dry-run` reports the same numbers without saving anything.

`database/data/samples/` holds small example files in each format. Their codes,
names and boundaries are made up; do not load them into production.

```sh
php artisan geo:import state database/data/samples/states.csv
php artisan geo:import lga database/data/samples/lgas.csv
php artisan geo:import ward database/data/samples/wards.geojson
php artisan geo:import polling-unit database/data/samples/polling_units.csv
```

## Scheduled tasks and settings

| Command | When | What |
|---|---|---|
| `tracking:prune` | Daily, 03:15 | Deletes location points older than the retention period. `--days=N` overrides it for one run; `--dry-run` only counts. |
| `sanctum:prune-expired` | Daily | Removes expired API tokens. |

| `.env` setting | Default | Meaning |
|---|---|---|
| `TRACKING_RETENTION_DAYS` | 90 | How long location points are kept. |
| `TRACKING_RATE_LIMIT_PER_MINUTE` | 60 | Location batches per agent per minute. |
| `TRACKING_MAX_BATCH_SIZE` | 500 | Points per batch. |
| `TRACKING_CONSENT_VERSION` | 1 | Raise it when the consent notice changes; agents must accept again. |
| `SUBMISSION_PHOTO_MAX_KB` | 8192 | Largest photo. |
| `SUBMISSION_MAX_PHOTOS` | 5 | Photos per submission. |

Run `php artisan config:cache` after changing `.env` on the server.

## Deployment

Production is a single Ubuntu server.

| What | Where |
|---|---|
| Code | `/var/www/rhyenigeria` (a clone of this repository's `main` branch, pulled with a read-only deploy key) |
| Web | Nginx site `/etc/nginx/sites-available/rhyenigeria.org` → PHP-FPM, web root `public/` |
| WebSockets | Reverb on `127.0.0.1:8080`; Nginx proxies `/app` and `/apps` to it, so browsers connect with `wss` on port 443 |
| Processes | Supervisor programs `rhye-queue` and `rhye-reverb` in `/etc/supervisor/conf.d/rhyenigeria.conf`, running as `www-data` |
| Scheduler | `/etc/cron.d/rhyenigeria` runs `schedule:run` every minute as `www-data` |
| Database | PostgreSQL database `rhye`; nightly dump to `/var/backups/rhye` (kept 14 days) |
| Secrets | `/var/www/rhyenigeria/.env` only. Never commit it. |

To release a change, push to `main`, then on the server:

```sh
/var/www/rhyenigeria/deploy.sh
```

The script pulls, installs Composer and npm dependencies, builds the assets,
runs the migrations (with the site briefly in maintenance mode), rebuilds the
config, route, view and event caches, and restarts the queue worker and Reverb.
It needs no sudo: the two processes are told to stop through the cache and
Supervisor starts them again on the new code.

Useful checks on the server:

```sh
sudo supervisorctl status
tail -n 50 /var/www/rhyenigeria/storage/logs/laravel.log
```
