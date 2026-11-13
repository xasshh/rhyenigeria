# Rhye Nigeria — backend

API backend for rhyenigeria.org. Field agents carry a mobile app or PWA that
reports their location while they are on shift and submits data collected in
the field. Coordinators watch a live map of the agents in their area, organised
by state, LGA, ward and polling unit.

It also serves the public website: the landing page, public sign-up for
members and canvassers, the member dashboard, and web dashboards for state
coordinators and super admins (see [Website](#website)).

- Laravel 13 on PHP 8.5
- PostgreSQL with PostGIS (all geometry is SRID 4326)
- Redis for cache, sessions and the queue
- Sanctum API tokens
- Reverb for live position updates over WebSockets

## Contents

- [Local setup](#local-setup)
- [Roles and scoping](#roles-and-scoping)
- [Website](#website)
- [API](#api)
- [Live updates](#live-updates)
- [Importing geography](#importing-geography)
- [Scheduled tasks and settings](#scheduled-tasks-and-settings)
- [Deployment](#deployment)
- [Later](#later)

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
| Member | `member` | Anyone who signs up at `/join`. Sees only their own card, profile, events and news. Never sees other people's data. |

**Canvassers are members.** A canvasser is a `member` whose
`member_profiles.canvasser_status` is `approved` (`pending` and `rejected` are the
other states; `NULL` means they never applied). Approval switches canvasser
tools on without moving anyone between roles, so a canvasser keeps the same
card, dashboard and scoping, and can never gain field-agent or coordinator
rights. Sign-up always saves `role = member` explicitly; the old `field_agent`
column default is never relied on.

A coordinator's area is one state, one LGA or one ward. An agent is inside a
coordinator's area when the agent's own assigned area falls within it; a
submission is inside it when the polling unit it relates to does. A coordinator
with no area sees nothing. Scoping is applied in the queries themselves, so
lists, single records, exports and WebSocket channels all follow the same rule.

Members follow the same rule on the website: `User::membersVisibleTo()` limits
a coordinator to members whose own state/LGA/ward is inside the coordinator's
area. Every member list, detail page, empowerment photo and CSV export starts
from it, so filters can narrow it but never widen it. A member outside your
area returns 404. Canvasser decisions are made by **state-level** coordinators
(and super admins) only.

## Website

Pages use Blade, Tailwind 4 and Alpine.js (`resources/js/site.js`). Chart.js is
loaded only on the coordinator and admin dashboards (`resources/js/charts.js`).
The Echo/Reverb client in `app.js` is not loaded on these pages.

### Public

| Path | What |
|---|---|
| `/` | Homepage: header slider (edited under **Admin → Homepage slides**), the election countdown directly below it, and an interactive map of Nigeria coloured by member count. Every "Join the Movement" button goes to `/join`; the nav has **Sign in**. |
| `/map/states.json` | Members per state, keyed by state code (`{"counts": {"LA": 13, …}, "total": …}`). Aggregate counts only, cached for 5 minutes (`Cache-Control: max-age=300`). Feeds the homepage map. |
| `/empowerment` | The homepage **Empowerment** button. Signed-in users go to their own Empowerment page (members `/membership/empowerment`, coordinators and admins their staff page); guests are sent to `/login` and land there after signing in. |
| `/join` | Chooser: *Member* or *Canvasser*. Keeps `?ref=` and `?state=`. |
| `/join?state=LA` | Pre-selects the state in the wizard (the homepage map links here). Unknown codes are ignored. |
| `/join?as=member` / `?as=canvasser` | Four-step wizard: Account → Details (name, gender, age range, state) → Location (LGA, ward, polling unit, PVC) → Finish (Volunteer for canvassers). Member/Canvasser can also be switched on step 1. A live membership card on the left fills in as you go. Each step is checked on the server (`POST /join/check/{step}`) before moving on, and the final `POST /join` re-checks everything. |
| `/verify/{token}` | Where the card's QR code points. Shows first name + last initial, state and month joined. Nothing else. |
| `/privacy` | Privacy notice (NDPA 2023). |
| `/login`, `/forgot-password`, `/reset-password/{token}` | Session sign-in (email + password) and password reset by email. |
| `/geo/states/{id}/lgas`, `/geo/lgas/{id}/wards`, `/geo/wards/{id}/polling-units` | Public lookups for the sign-up dropdowns. |

The sign-in, join, password-reset and welcome screens follow the *RHYE — Sign in & Join screens* PDF (dark
forest panel with the headline and card on the left, the form on the right; on phones the panel shrinks to a
short band above the form). Headlines use Bricolage Grotesque, body text DM Sans. The thumbprint motif
(`<x-fingerprint>`) is an original SVG drawn in code and is shared with the homepage. After a reset link is sent,
`/forgot-password` shows a "Check your email" step; saving a new password signs the person in (except field
agents and deactivated accounts) and signs out every other browser and API token.

The slogan, **Hope Renewed, Nation Rebuilt.**, lives in `config('membership.slogan')` and is used in page titles
and descriptions, the homepage hero, announcement bar and footer, the membership card (screen and PNG), share
messages and the campaign-DP frame. The favicon set (`public/favicon.ico`, `favicon-32.png`,
`apple-touch-icon.png`, `images/icon-512.png`) is the four-figure emblem from the RHYE logo.

After sign-in, users are sent by role: members to `/membership`, coordinators to
`/coordinator`, super admins to `/admin`. Field agents keep using the API and
cannot sign in on the website.

Sign-up details:

- Phone numbers are stored as `+234XXXXXXXXXX` and are unique; email is unique and lower-cased.
- If the chosen LGA has wards in the database, a ward is required; if not (only states and LGAs imported), the ward and polling unit are skipped.
- Polling unit is optional ("Not sure"). VIN (19 characters) and NIN (11 digits) are optional and stored with Laravel's `encrypted` cast.
- Location is only requested when the person taps **Share location**.
- Protection: server-side validation per step, a hidden honeypot field, and per-IP limits (`JOIN_RATE_LIMIT_PER_MINUTE` 120, `JOIN_CHECK_RATE_LIMIT_PER_MINUTE` 600, `GEO_RATE_LIMIT_PER_MINUTE` 1200; generous because venue Wi-Fi and mobile networks put many phones behind one IP).
- Member numbers are `RHYE-{STATE CODE}-{6 random digits}`. The state code is the `code` column of `states`, so import states with short codes such as `LA` and `FC`.
- The referral code is the member number. `?ref=RHYE-LA-123456` pre-fills "Who invited you?", and `member_profiles.referred_by_user_id` records it.

### Homepage slides and countdown

**Admin → Homepage slides** (`/admin/slides`) lists the header slides. Each has an image (JPEG, PNG or WebP, at
least 600 px wide; a 1600 px WebP copy is made with GD and the original is kept, both on the `public` disk under
`slides/`), an optional small line, a headline, short text, a button label and link (a path such as `/join` or a
full `https://` link), an order number, an on/off switch and a **layout**: *Photo of a person*, *Flyer or other image*, *Original hero* (the first landing design: round
portrait from `public/images/hero-portrait.webp` and the three stat badges) or *Image beside
the text*. Backgrounds alternate by position, whatever the layout: white, green, white, green… All "Join the
Movement" buttons on the homepage use the site's yellow (`accent`, #f2b705). Current order: 1 the Plateau State
Education Empowerment Initiative flyer (`slide-1.webp`, original `slide-1.jpeg`), 2 the original hero with
President Tinubu, 3 the patron, Bar. Seyi Tinubu (`seyi-tinubu.webp`), 4 the Minister, Comrade Ayodele Olawande
(`ayodele-olawande.webp`, original `.jpeg`), 5 the National Convener, Abdulazeez Kaka (`abdulazeez-kaka.webp`). Slides 3–5 use the *Photo of a person* layout. Photos and flyers share one rounded 4:5 frame (cropped in CSS, so
uploads need no editing) and carry the RHYE logo sticker; only the original hero keeps its round portrait.

The top announcement bar also shows a compact live countdown (weeks, days, h:m:s) that links to the big one.

The slider advances every 7 seconds, pauses on hover, keyboard focus and touch, swipes on phones, has dots,
arrows and a pause button, answers the left/right arrow keys, and does not auto-advance for people who ask for
reduced motion.

The countdown counts to `Setting::electionDate()`: the date set under **Settings**, or, when none is set,
**Saturday 16 January 2027, 00:00 Africa/Lagos** (Presidential and National Assembly election). It shows weeks,
days, hours, minutes and seconds, rendered on the server first and then ticked by Alpine. Once it reaches zero
it shows "Today is Election Day: go and vote." The member dashboard uses the same date.

### Members (`/membership`, role `member`)

Home (election countdown, next event, latest national/state/ward news, apply to
canvass), **My card** (membership card with QR, download as PNG, share links,
state WhatsApp group, edit details, add PVC/VIN/NIN, change password), **Events
& news**, **Empowerment** (report a programme with a private photo), and **Make
your campaign DP** (canvas in the browser; the photo is never uploaded).

### Coordinators (`/coordinator`, role `coordinator`)

Dashboard (totals, sign-ups, LGA and ward breakdowns), **Members** (search and
filters; phones masked in the list, full on the detail page; NIN/VIN always
masked), **Canvassers** (approve/reject with a note; every decision is written to
the audit log as `canvasser.approved` / `canvasser.rejected`), **Empowerment**,
**News & events** (post, edit and remove, for their own state, optionally one ward), a **map of their state by
LGA** on the dashboard (coloured by member count; clicking an LGA opens the member list filtered to it; counts
come from `/coordinator/map/lgas`, scoped like everything else), and **CSV export**
(audit-logged as `members.exported`; never includes NIN/VIN). Viewing a member's
detail page is logged as `member.viewed`.

### Field work and the live map (canvasser tracking)

Adapted from GeoVerify's live operations console. Only **approved canvassers** can share their location, and
only by choice:

- **Field work** (`/membership/field`, shown in the member menu to approved canvassers only). The canvasser
  first agrees to a plain-language location notice (stored as `users.tracking_consent_*`, version
  `TRACKING_CONSENT_VERSION`), then presses **Start shift**. While a shift runs *and the page is open*, the
  phone's GPS is read with `watchPosition`, a fix is kept when the phone has moved 15 m or a minute has
  passed, queued in the phone's storage, and sent in batches to `POST /membership/field/locations`, which uses
  the same `LocationIngestor` as the field-agent API (points outside a shift are dropped, repeats are
  ignored). The page asks the phone to keep the screen on. **End shift** or **Withdraw** stops it at once;
  other member pages show a "Your shift is running" banner. Browsers cannot track with the page closed or
  the screen locked; a native app would be needed for that.
- **Activities** (`field_activities`): door-to-door visits (households reached and outcome), event check-ins
  (events for their state or national) and reports, each with an optional photo on the private disk
  (`/field/activities/{id}/photo`, the canvasser or staff who can see them only), stamped with GPS, time,
  shift, state and the ward the point falls in. Retries are safe (each has a phone-generated UUID).
- **Live map** (`/coordinator/live`, `/admin/live` with a state picker). A MapLibre map (style from
  `MAP_STYLE_URL`, default OpenFreeMap, OpenStreetMap data, no key) with the state outline, each canvasser's
  latest position today coloured active / on shift but quiet (no fix for 30 minutes) / off shift, today's
  activities, and a side list with distance walked (PostGIS, ignoring impossible jumps), activities,
  households, last seen and flags (impossible jumps over 160 km/h, weak GPS most of the day). Selecting a
  canvasser draws today's route (`/live/{member}/trail`, simplified to 500 points); **every route view is
  written to the audit log** as `trail.viewed`. The feed refreshes every 20 seconds. Everything starts from
  `User::canvassersVisibleTo()`, so a coordinator sees only their own state.
- Positions are deleted after `TRACKING_RETENTION_DAYS` (90) by `tracking:prune`; activities are kept. The
  privacy notice explains all of this under **Location sharing for canvassers** (`/privacy#location`).

### Super admins (`/admin`, role `super_admin`)

National overview (totals, registrations chart, sortable by-state table linking
to `/admin/states/{id}`, gender/age/PVC breakdowns), every coordinator page
nationally, **Coordinators** (create, change state, deactivate), **News & events**
(national or any state), **Settings** (election date and label, each state's
WhatsApp group link), and CSV export with an explicit **Include NIN & VIN** box.

### Creating a coordinator

Sign in as a super admin, open **Coordinators**, and fill in name, email, phone,
state and a temporary password (10+ characters). Or use the API:

```sh
curl -s $API/users -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json' \
  -d name='Bola Ade' -d email=bola@example.com -d password='a-long-password' \
  -d role=coordinator -d area_level=state -d area_id=1
```

Only **state-level** coordinators get the web dashboard features for canvasser
decisions; LGA- and ward-level coordinators (used by the field-agent API) can
view their area but not approve canvassers.

A coordinator never sees another state: every list, detail page, count, map, export and post edit starts from
their own scope, and putting another state's id in a URL or filter narrows the result to nothing rather than
widening it (`tests/Feature/CoordinatorStateIsolationTest.php`).

### Geography for sign-up

The sign-up dropdowns read the `states`, `lgas`, `wards` and `polling_units`
tables, loaded with `geo:import` (below). The repository only has made-up
samples. For production, provide INEC data as CSV:

| File | Columns |
|---|---|
| `states.csv` | `code,name` (use two-letter codes such as `LA`, `FC`: they appear in member numbers) |
| `lgas.csv` | `state_code,code,name` |
| `wards.csv` | `state_code,lga_code,code,name` |
| `polling_units.csv` | `state_code,lga_code,ward_code,code,name,latitude,longitude` (lat/lng may be empty) |

States and LGAs alone are enough to launch; wards and polling units can be
imported later without changing anything else.

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

### Real Nigerian data and boundaries

`database/data/nigeria/` holds the production files:

| File | What |
|---|---|
| `states.csv`, `lgas.csv`, `wards.csv` | 37 states (two-letter codes), 774 LGAs, 8,809 wards. No polling units yet. |
| `state-boundaries.geojson` | State boundaries (`code`, `name`), used by the homepage map. |
| `lga-boundaries.geojson` | LGA boundaries (`state_code`, `code`, `name`), simplified to about 250 m, used by the coordinator maps. |

The boundaries come from **geoBoundaries gbOpen, Nigeria ADM1 and ADM2** (release `9469f09`, simplified
versions), whose source is **GRID3**, licensed **CC BY 4.0**: <https://www.geoboundaries.org/> (Runfola et al.
2020, *geoBoundaries: A global database of political administrative boundaries*, PLoS ONE). Attribution:
"Boundaries © GRID3 via geoBoundaries, CC BY 4.0". ADM1 shapes were matched to our states by ISO code. ADM2
shapes were assigned to a state with PostGIS (`ST_PointOnSurface` inside the state boundary) and matched to our
LGAs by name; the 31 spelling differences (for example *Yenegoa*/*Yenagoa*, *Kogi*/*Koton-Karfe*,
*Yewa North*/*Egbado North*) were paired within each state. All 774 LGAs have a boundary.

```sh
php artisan geo:import state database/data/nigeria/states.csv
php artisan geo:import lga   database/data/nigeria/lgas.csv
php artisan geo:import ward  database/data/nigeria/wards.csv
php artisan geo:import state database/data/nigeria/state-boundaries.geojson
php artisan geo:import lga   database/data/nigeria/lga-boundaries.geojson
```

The maps are drawn on the server as SVG from these boundaries (`App\Services\Geo\AreaMap`, cached for a day and
cleared by every `geo:import`), so no mapping library is loaded.

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
| `JOIN_RATE_LIMIT_PER_MINUTE` | 120 | Completed sign-ups per IP per minute. Generous because at rallies many phones share one IP. |
| `JOIN_CHECK_RATE_LIMIT_PER_MINUTE` | 600 | Wizard step checks per IP per minute (about 4 per sign-up). |
| `GEO_RATE_LIMIT_PER_MINUTE` | 1200 | State/LGA/ward/polling-unit dropdown lookups per IP per minute (about 3 per sign-up). |
| `MEMBERSHIP_CONSENT_VERSION` | 1 | Raise when the data-consent wording changes; stored with each member. |
| `EMPOWERMENT_PHOTO_MAX_KB` | 8192 | Largest empowerment photo. Photos are on the private disk (`storage/app/private/empowerment`). |
| `SOCIAL_FACEBOOK_URL`, `SOCIAL_INSTAGRAM_URL`, `SOCIAL_TIKTOK_URL`, `SOCIAL_X_URL` | see `config/membership.php` | Social media links in the homepage top bar and footer. TikTok and X point at the profiles; Facebook and Instagram point at posts until the profile links are set here. Leave one empty (`=`) to hide its icon. |
| `MAP_STYLE_URL` | OpenFreeMap "liberty" | MapLibre style for the live map's street background. |
| `HELP_URL` | (empty) | Where "Get help" on the sign-in and join screens points, e.g. a WhatsApp link or `mailto:`. Empty uses the privacy notice. |
| `MAIL_*` | `log` | Password-reset emails. See the SMTP block in `.env.example`. `APP_URL` must be the public `https://` address: QR codes, share links and reset links use it. |

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

The membership release adds one migration
(`2026_11_12_100000_create_membership_tables`): it adds a unique index on
`users.phone` (it fails if two existing users share a phone number; fix those
first), `states.whatsapp_group_url`, and the `member_profiles`, `events`,
`news_posts`, `empowerment_reports` and `settings` tables. `deploy.sh` runs it.

Useful checks on the server:

```sh
sudo supervisorctl status
tail -n 50 /var/www/rhyenigeria/storage/logs/laravel.log
```

### Release: new sign-in/join screens, homepage slider, countdown and maps

- **New migrations** `2026_11_13_130501_create_hero_slides_table` (creates `hero_slides` with five starting
  slides) and `2026_11_13_133230_add_layout_to_hero_slides_table` (adds `layout` and reorders the slides: original
  hero, Seyi Tinubu, the flyer, two placeholders; only untouched placeholders are reused). `deploy.sh` runs them.
- **New `.env` key** (optional): `HELP_URL`.
- `deploy.sh` now runs `php artisan storage:link` when `public/storage` is missing (uploaded slides are served
  from it).
- **Run once on the server after deploying**, from `/var/www/rhyenigeria`, if the real geography is not loaded
  there yet (states first; each file is safe to re-run):

  ```sh
  php artisan geo:import state database/data/nigeria/states.csv
  php artisan geo:import lga   database/data/nigeria/lgas.csv
  php artisan geo:import ward  database/data/nigeria/wards.csv
  php artisan geo:import state database/data/nigeria/state-boundaries.geojson
  php artisan geo:import lga   database/data/nigeria/lga-boundaries.geojson
  ```

  Without the boundary files the maps show "Boundaries for this map have not been loaded yet."

### Release: canvasser field work and the live map

- **New migration** `create_field_activities_table`. `deploy.sh` runs it.
- **New npm dependency** `maplibre-gl` (6.16.0), bundled only into `resources/js/live-map.js`, loaded only on
  the live map page. `deploy.sh` installs and builds it.
- Optional `.env`: `MAP_STYLE_URL`.
- The queue worker must be running (it already is under Supervisor): it works out the ward of each GPS point.

## Later

Not built yet, on purpose:

- **INEC election calendar.** Publish the official INEC election timetable on the platform, once someone has
  researched and confirmed it from INEC's own publications.
- **Complete ward list.** Import the complete ward list for all 774 LGAs once the file is supplied
  (`geo:import ward`; see [Importing geography](#importing-geography)). Polling units can follow the same way.
