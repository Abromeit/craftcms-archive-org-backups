# Archive.org Backups for Craft CMS

Archive.org Backups is a Craft CMS 5 plugin that submits selected entry URLs to the
Internet Archive Save Page Now service, tracks submission history, and confirms when a
snapshot becomes visible through Archive.org indexing APIs.

## Features

- Archive selected Craft entry sections to archive.org automatically
- See all tracked URLs in one control panel screen
- Monitor last submission time, next submission time, and indexing status
- Prioritize changed pages while still refreshing unchanged pages on a schedule
- Stay within the public Save Page Now daily limit
- Get live dashboard updates while you keep the page open

## Requirements

- PHP 8.2+
- Craft CMS 5.x

## Installation

Until the package is published on Packagist, install it from the Git repository.

Add a VCS repository to your Craft project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:Abromeit/craftcms-archive-org-backups.git"
        }
    ]
}
```

Then require the package from the branch or tag you want to use:

```bash
composer require abromeit/craftcms-archive-org-backups:dev-master
```

Then install the plugin in the Craft control panel or with:

```bash
php craft plugin/install archive-org-backups
```

After the package is published on Packagist, the install command can be simplified to:

```bash
composer require abromeit/craftcms-archive-org-backups
```

## Configuration

Open `Settings -> Plugins -> Archive.org Backups` and configure:

- `Entry sections`
- `Public daily limit` (default `150`)
- `Changed resubmit window (hours)` (default `24`)
- `Unchanged refresh interval (days)` (default `7`)
- `Heartbeat interval (minutes)` (default `15`)

Only selected entry sections are tracked in v1.

### `.env` endpoint overrides

For local testing, the remote Archive.org hostnames can be overridden from your Craft
`.env` file.

Use one global override for all requests:

```dotenv
ARCHIVEORG_BACKUPS_BASE_URL="http://127.0.0.1:8080"
```

Or override individual endpoint bases:

```dotenv
ARCHIVEORG_BACKUPS_SAVE_BASE_URL="http://127.0.0.1:8080"
ARCHIVEORG_BACKUPS_SAVE_STATUS_BASE_URL="http://127.0.0.1:8080"
```

Defaults:

- `ARCHIVEORG_BACKUPS_SAVE_BASE_URL` -> `https://web.archive.org`
- `ARCHIVEORG_BACKUPS_SAVE_STATUS_BASE_URL` -> `https://web-wp.archive.org`

The snapshot viewing URL and the external-snapshot probe always hit
`https://web.archive.org`, and can only be redirected via the global
`ARCHIVEORG_BACKUPS_BASE_URL`.

## Production-only outbound traffic

As a hard safety net, the plugin only ever talks to Archive.org when Craft is
running with `CRAFT_ENVIRONMENT=production`. On any other environment
(staging, dev, local clones) the plugin will not enqueue heartbeats, will not
submit URLs to Save Page Now, and will not probe Wayback for snapshots — even
on a fresh install or when the Craft control panel is opened.

Tracked targets are still discovered and shown in the dashboard, so you can
review which URLs would be archived, but nothing leaves the server until
`CRAFT_ENVIRONMENT` is set to `production`.

## Queue Execution

The plugin works with Craft's default HTTP queue runner, so it can operate without cron.
Timing remains best-effort on low-traffic sites.

For stronger timing guarantees, run a dedicated queue worker:

```bash
php craft queue/listen --verbose
```

## Console Commands

```bash
php craft archive-org-backups/sync-targets
php craft archive-org-backups/run-maintenance
```

## Development

Run the unit tests with:

```bash
vendor/bin/phpunit
```

## Copyright

Copyright (c) 2026 Daniel Abromeit (https://daniel-abromeit.de/)

Thank you to [KOCH ESSEN](https://koch-essen.de/) for providing the resources without which this project would not have been possible.

Released under the MIT License. [See LICENSE for details.](LICENSE.txt)
