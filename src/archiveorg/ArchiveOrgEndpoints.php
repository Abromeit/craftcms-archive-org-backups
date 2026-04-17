<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups\archiveorg;

use craft\helpers\App;

final class ArchiveOrgEndpoints
{
    public const GLOBAL_BASE_URL_ENV = 'ARCHIVEORG_BACKUPS_BASE_URL';

    public const SAVE_BASE_URL_ENV = 'ARCHIVEORG_BACKUPS_SAVE_BASE_URL';

    public const SAVE_STATUS_BASE_URL_ENV = 'ARCHIVEORG_BACKUPS_SAVE_STATUS_BASE_URL';

    public const AVAILABILITY_BASE_URL_ENV = 'ARCHIVEORG_BACKUPS_AVAILABILITY_BASE_URL';

    public const CDX_BASE_URL_ENV = 'ARCHIVEORG_BACKUPS_CDX_BASE_URL';

    public static function saveUrl(): string
    {
        return self::baseUrl(self::SAVE_BASE_URL_ENV, 'https://web.archive.org') . '/save/';
    }

    public static function saveStatusUrl(string $jobId): string
    {
        return self::baseUrl(self::SAVE_STATUS_BASE_URL_ENV, 'https://web-wp.archive.org')
            . '/save/status/' . rawurlencode($jobId);
    }

    public static function availabilityUrl(string $url): string
    {
        return self::baseUrl(self::AVAILABILITY_BASE_URL_ENV, 'https://archive.org')
            . '/wayback/available/?url=' . rawurlencode($url);
    }

    public static function cdxUrl(array $query): string
    {
        return self::baseUrl(self::CDX_BASE_URL_ENV, 'https://web.archive.org')
            . '/cdx/search/cdx?' . http_build_query($query);
    }

    private static function baseUrl(string $specificEnv, string $default): string
    {
        $global = self::env(self::GLOBAL_BASE_URL_ENV);

        if ($global !== null) {
            return $global;
        }

        $specific = self::env($specificEnv);

        if ($specific !== null) {
            return $specific;
        }

        return rtrim($default, '/');
    }

    private static function env(string $name): ?string
    {
        $value = App::parseEnv('$' . $name);

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return rtrim($value, '/');
    }
}
