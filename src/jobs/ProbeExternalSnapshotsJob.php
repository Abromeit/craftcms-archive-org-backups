<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups\jobs;

use craft\queue\BaseJob;
use abromeit\archiveorgbackups\ArchiveOrgBackups;

final class ProbeExternalSnapshotsJob extends BaseJob
{
    public function execute($queue): void
    {
        ArchiveOrgBackups::plugin()->getIndexing()->probeExternalSnapshotBatch();
    }

    protected function defaultDescription(): ?string
    {
        return 'Probe Archive.org for pre-existing snapshots';
    }
}
