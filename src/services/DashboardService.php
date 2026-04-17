<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups\services;

use Craft;
use craft\base\Component;
use craft\helpers\UrlHelper;
use abromeit\archiveorgbackups\ArchiveOrgBackups;

final class DashboardService extends Component
{
    public function getDashboardData(string $sort = 'nextSubmissionAt', string $dir = 'asc'): array
    {
        $rowData = ArchiveOrgBackups::plugin()->getTargets()->getDashboardRows($sort, $dir);

        return [
            'progress' => ArchiveOrgBackups::plugin()->getQuota()->getProgressData(),
            'rows' => $rowData['rows'],
            'notice' => $rowData['notice'],
            'columns' => $this->getColumns($sort, $dir),
        ];
    }

    public function renderDashboardHtml(string $sort = 'nextSubmissionAt', string $dir = 'asc'): string
    {
        return Craft::$app->getView()->renderTemplate(
            ArchiveOrgBackups::HANDLE . '/_components/dashboard',
            $this->getDashboardData($sort, $dir)
        );
    }

    private function getColumns(string $sort, string $dir): array
    {
        return [
            $this->buildColumn('url', Craft::t(ArchiveOrgBackups::TRANSLATION_CATEGORY, 'URL'), $sort, $dir),
            $this->buildColumn(
                'lastSubmittedAt',
                Craft::t(ArchiveOrgBackups::TRANSLATION_CATEGORY, 'Last Submission'),
                $sort,
                $dir
            ),
            $this->buildColumn(
                'nextSubmissionAt',
                Craft::t(ArchiveOrgBackups::TRANSLATION_CATEGORY, 'Next Submission'),
                $sort,
                $dir
            ),
            [
                'label' => Craft::t(ArchiveOrgBackups::TRANSLATION_CATEGORY, 'Status'),
                'url' => UrlHelper::cpUrl(ArchiveOrgBackups::HANDLE),
            ],
        ];
    }

    private function buildColumn(string $column, string $label, string $sort, string $dir): array
    {
        $nextDir = $sort === $column && strtolower($dir) === 'asc' ? 'desc' : 'asc';

        return [
            'label' => $label,
            'url' => UrlHelper::cpUrl(ArchiveOrgBackups::HANDLE, [
                'sort' => $column,
                'dir' => $nextDir,
            ]),
        ];
    }
}
