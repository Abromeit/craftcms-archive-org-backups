<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups\jobs;

use craft\queue\BaseJob;
use abromeit\archiveorgbackups\ArchiveOrgBackups;

final class PollSubmissionStatusJob extends BaseJob
{
    public int $targetId;

    public int $attempt = 0;

    public string $expectedJobId;

    public function execute($queue): void
    {
        ArchiveOrgBackups::plugin()->getIndexing()->pollSubmissionStatus(
            $this->targetId,
            $this->attempt,
            $this->expectedJobId
        );
    }

    protected function defaultDescription(): ?string
    {
        return 'Poll Archive.org save status';
    }
}
