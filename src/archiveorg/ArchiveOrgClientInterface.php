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
     * @return array{timestamp:string, original:string}|null
     */
    public function getLatestCdxCapture(string $url): ?array;
}
