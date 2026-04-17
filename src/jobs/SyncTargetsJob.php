<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups\jobs;

use craft\queue\BaseJob;
use abromeit\archiveorgbackups\ArchiveOrgBackups;

final class SyncTargetsJob extends BaseJob
{
    public function execute($queue): void
    {
        $offset = 0;
        $limit = 100;

        do {
            $count = ArchiveOrgBackups::plugin()->getTargets()->syncManifestBatch($offset, $limit);
            $offset += $limit;
        } while ($count === $limit);

        ArchiveOrgBackups::plugin()->getTargets()->retireInvalidTargets();
    }

    protected function defaultDescription(): ?string
    {
        return 'Sync Archive.org Backups targets';
    }
}
