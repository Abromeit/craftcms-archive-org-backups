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
     * @param array<string, mixed> $payload
     * @return array{timestamp:string, url:string, status:int}|null
     */
    public static function extractAvailabilitySnapshot(array $payload): ?array
    {
        $closest = $payload['archived_snapshots']['closest'] ?? null;

        if (!is_array($closest)) {
            return null;
        }

        if (($closest['available'] ?? false) !== true) {
            return null;
        }

        $timestamp = isset($closest['timestamp']) ? (string) $closest['timestamp'] : '';
        $url = isset($closest['url']) ? (string) $closest['url'] : '';
        $status = isset($closest['status']) ? (int) $closest['status'] : 0;

        if ($timestamp === '' || $url === '' || $status <= 0) {
            return null;
        }

        return [
            'timestamp' => $timestamp,
            'url' => $url,
            'status' => $status,
        ];
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
