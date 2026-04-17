<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups\console\controllers;

use yii\console\Controller;
use yii\console\ExitCode;
use abromeit\archiveorgbackups\ArchiveOrgBackups;

final class DefaultController extends Controller
{
    public function actionSyncTargets(): int
    {
        $offset = 0;
        $limit = 100;

        do {
            $count = ArchiveOrgBackups::plugin()->getTargets()->syncManifestBatch($offset, $limit);
            $offset += $limit;
        } while ($count === $limit);

        ArchiveOrgBackups::plugin()->getTargets()->retireInvalidTargets();

        return ExitCode::OK;
    }

    public function actionRunMaintenance(): int
    {
        $this->actionSyncTargets();
        ArchiveOrgBackups::plugin()->getSubmission()->processDueTargets();

        return ExitCode::OK;
    }
}
