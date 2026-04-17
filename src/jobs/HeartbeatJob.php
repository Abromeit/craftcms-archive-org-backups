<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups\jobs;

use craft\queue\BaseJob;
use abromeit\archiveorgbackups\ArchiveOrgBackups;

final class HeartbeatJob extends BaseJob
{
    public function execute($queue): void
    {
        ArchiveOrgBackups::plugin()->getHeartbeat()->runMaintenance();
    }

    protected function defaultDescription(): ?string
    {
        return 'Archive.org Backups heartbeat';
    }
}
