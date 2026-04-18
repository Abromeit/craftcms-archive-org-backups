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
     * @param array<int, array<int, string>> $payload
     * @return array{timestamp:string, original:string}|null
     */
    public static function extractLatestCdxCapture(array $payload): ?array
    {
        if (!isset($payload[1]) || !is_array($payload[1])) {
            return null;
        }

        $row = $payload[1];
        $timestamp = $row[0] ?? '';
        $original = $row[1] ?? '';

        if ($timestamp === '' || $original === '') {
            return null;
        }

        return [
            'timestamp' => $timestamp,
            'original' => $original,
        ];
    }
}
