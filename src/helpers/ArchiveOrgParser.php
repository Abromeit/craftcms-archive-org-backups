<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups\helpers;

final class ArchiveOrgParser
{
    public static function extractJobId(string $body): ?string
    {
        if (preg_match('/spn.watchJob\("([^"]+)"/', $body, $matches) !== 1) {
            return null;
        }

        return $matches[1] !== '' ? $matches[1] : null;
    }

    public static function detectDailyLimit(string $body): ?int
    {
        if (preg_match('/You cannot make more than \(([\d,]+)\) captures per day\./', $body, $matches) !== 1) {
            return null;
        }

        $limit = (int) str_replace(',', '', $matches[1]);

        return $limit > 0 ? $limit : null;
    }

    /**
     * Reads the snapshot timestamp from Wayback's `/web/9999/<url>` 302
     * response. Prefers the explicit `x-archive-redirect-reason` header,
     * falls back to parsing the timestamp out of the `location` URL.
     *
     * @param  string $reasonHeader   - Value of the `x-archive-redirect-reason` header.
     * @param  string $locationHeader - Value of the `location` header.
     *
     * @return ?string
     */
    public static function extractTimestampFromRedirect(
        string $reasonHeader,
        string $locationHeader
    ): ?string {
        if (
            $reasonHeader !== ''
            && preg_match('/found capture at (\d{14})/', $reasonHeader, $matches) === 1
        ) {
            return $matches[1];
        }

        if (
            $locationHeader !== ''
            && preg_match('#/web/(\d{14})/#', $locationHeader, $matches) === 1
        ) {
            return $matches[1];
        }

        return null;
    }
}
