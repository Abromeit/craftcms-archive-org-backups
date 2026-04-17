<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups\jobs;

use craft\queue\BaseJob;
use abromeit\archiveorgbackups\ArchiveOrgBackups;

final class SubmitDueTargetsJob extends BaseJob
{
    public function execute($queue): void
    {
        ArchiveOrgBackups::plugin()->getSubmission()->processDueTargets();
    }

    protected function defaultDescription(): ?string
    {
        return 'Submit due Archive.org Backups targets';
    }
}
