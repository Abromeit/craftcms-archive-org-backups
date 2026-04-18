# Archive.org Backups for Craft CMS

Archive.org Backups for Craft CMS is a Craft CMS 5 plugin that submits selected entry URLs to the
Internet Archive Save Page Now service, tracks submission history, and confirms when a
snapshot becomes visible through Archive.org.

## Features

- Archive selected Craft entry sections to archive.org automatically
- See all tracked URLs and their status in one control panel screen
- Prioritizes changed entries (pages) while still refreshing unchanged entries on a schedule
- Automatically stays within the public Save Page Now daily limit
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

## Configuration

Open `Settings -> Plugins -> Archive.org Backups` and configure:

- `Entry sections`
- `Public daily limit` (default `150`)
- `Changed resubmit window (hours)` (default `24`)
- `Unchanged refresh interval (days)` (default `7`)
- `Heartbeat interval (minutes)` (default `15`)

**Important: Configure this - only selected entry sections are tracked!**

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

The plugin only talks to Archive.org when `CRAFT_ENVIRONMENT=production` and
the primary site's base URL is not a local host (`localhost`, `*.local`,
`*.test`, or a loopback IP like `127.0.0.1` / `::1`). On local setups an
`ARCHIVEORG_BACKUPS_*_BASE_URL` override (see below) re-enables outbound
traffic so you can point it at a mock server.

## SEOmatic compatibility

If [SEOmatic](https://plugins.craftcms.com/seomatic?craft5) is installed and
an entry has a `SeoSettings` field whose `robots` directive contains `none`,
`noindex`, or `noarchive`, the entry is skipped and never submitted to
Archive.org. Only the per-entry SEOmatic field is evaluated; global or
section-level SEOmatic defaults are not inspected.

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

Thank you to https://wordpress.org/plugins/internet-archive-wayback-machine-link-fixer/ for the inspiration for this CraftCMS plugin. The submission mechanic is based on the original WordPress code by the Internet Archive.

Released under the MIT License. [See LICENSE for details.](LICENSE.txt)
