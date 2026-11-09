# Leiterrunde – Schulungen (Training)

Module app of the [Leiterrunde](https://github.com/ollimaz/leiterrunde) suite:
training records and certificates of conduct ("Führungszeugnisse") for the
leader team.

> **Requires** the core app [`leiterrunde`](https://github.com/ollimaz/leiterrunde)
> to be enabled first.

## Features

- **Training types** — configurable training types with validity rules, seeded on install
- **Training records** — per-person records with file attachments (stored via the core document storage), approval/rejection workflow and status overview
- **Certificates of conduct (FZ)** — issue and expiry tracking, locked to the `fz` role (`fz.read`/`fz.write` are not editable and invisible even to Nextcloud admins). The editable `fz.expiry.read` capability (default roles: `fz`, `vorstand`, `schulung`) exposes only the status and expiry date
- **Supervisor check** — `betreuerCheck` shows at a glance whether a person may supervise
- **Rules** — configurable validity/expiry rules in the admin settings
- **Reminders** — `TrainingCheckJob` background job notifies about expiring trainings and certificates
- **Dashboard widget** — personal training status on the Nextcloud dashboard

## Requirements

- Nextcloud 29 – 36
- Enabled [`leiterrunde`](https://github.com/ollimaz/leiterrunde) app

## Installation

```bash
cd /path/to/nextcloud/apps
git clone https://github.com/ollimaz/leiterrunde_schulungen.git
occ app:enable leiterrunde          # core, once
occ app:enable leiterrunde_schulungen
```

## API

Base path `/apps/leiterrunde_schulungen/api` — authentication as in the core
app (session + `requesttoken`, or HTTP Basic + `OCS-APIRequest: true`).

| Verb | Paths |
|---|---|
| GET | `/training-types`, `/training/status`, `/training/check`, `/persons/{id}/training-status`, `/training-records`, `/training-records/{id}`, `/training-records/{id}/file`, `/fz`, `/settings` |
| POST | `/training-types`, `/training-records`, `/training-records/{id}/approve`, `/training-records/{id}/reject`, `/fz` |
| PUT | `/training-types/{id}`, `/fz/{id}`, `/settings` |
| DELETE | `/training-types/{id}`, `/training-records/{id}`, `/fz/{id}` |

## Localization

Source strings are English; the maintained translation is German (informal
"du"). Only `l10n/de.json` is maintained — the generated `de.js`,
`de_DE.json` and `de_DE.js` are built with
[`l10n-tools`](https://github.com/ollimaz/leiterrunde/tree/main/l10n-tools)
from the core app.

## Development

```bash
cd tests && phpunit -c phpunit.xml
```

## License

[AGPL-3.0-or-later](LICENSE)
