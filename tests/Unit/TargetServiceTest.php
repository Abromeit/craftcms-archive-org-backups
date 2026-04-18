<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups\tests\Unit;

use PHPUnit\Framework\TestCase;
use abromeit\archiveorgbackups\ArchiveOrgBackups;
use abromeit\archiveorgbackups\services\TargetService;

final class TargetServiceTest extends TestCase
{
    public function testAcceptedSubmissionUsesSubmittedLabel(): void
    {
        self::assertSame(
            'Submitted',
            TargetService::statusLabelKey(
                ArchiveOrgBackups::JOB_STATUS_PENDING,
                ArchiveOrgBackups::INDEXING_PENDING
            )
        );
    }

    public function testSuccessfulArchiveUsesSuccessfullyArchivedLabel(): void
    {
        self::assertSame(
            'Successfully archived',
            TargetService::statusLabelKey(
                ArchiveOrgBackups::JOB_STATUS_SUCCESS,
                ArchiveOrgBackups::INDEXING_INDEXED
            )
        );
    }

    public function testIndexedPendingAfterSaveStatusSuccessStillUsesSubmittedLabel(): void
    {
        self::assertSame(
            'Submitted',
            TargetService::statusLabelKey(
                ArchiveOrgBackups::JOB_STATUS_SUCCESS,
                ArchiveOrgBackups::INDEXING_PENDING
            )
        );
    }
}
