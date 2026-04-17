<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups\jobs;

use craft\queue\BaseJob;
use abromeit\archiveorgbackups\ArchiveOrgBackups;

final class ConfirmIndexingJob extends BaseJob
{
    public int $targetId;

    public int $attempt = 0;

    public string $expectedJobId;

    public function execute($queue): void
    {
        ArchiveOrgBackups::plugin()->getIndexing()->confirmIndexing(
            $this->targetId,
            $this->attempt,
            false,
            $this->expectedJobId
        );
    }

    protected function defaultDescription(): ?string
    {
        return 'Confirm Archive.org indexing';
    }
}
