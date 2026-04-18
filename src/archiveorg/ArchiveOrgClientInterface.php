<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups\archiveorg;

interface ArchiveOrgClientInterface
{
    /**
     * @return array{jobId:string, observedDailyLimit:int|null}
     */
    public function submitUrl(string $url): array;

    /**
     * @return array{status:string, message:string, statusExt:string|null}
     */
    public function getSaveStatus(string $jobId): array;

    /**
     * Fast "is this URL archived, and when?" lookup via Wayback's
     * `/web/9999/<url>` latest-capture redirect endpoint. Much faster than
     * `/cdx/search/cdx` for a one-shot probe.
     *
     * @return array{timestamp:string, original:string}|null
     */
    public function getLatestAvailableSnapshot(string $url): ?array;
}
