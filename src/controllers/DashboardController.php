<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;
use abromeit\archiveorgbackups\ArchiveOrgBackups;
use abromeit\archiveorgbackups\assets\DashboardAsset;

final class DashboardController extends Controller
{
    protected array|bool|int $allowAnonymous = false;

    public function beforeAction($action): bool
    {
        $this->requireCpRequest();
        $this->requirePermission('accessPlugin-' . ArchiveOrgBackups::HANDLE);

        return parent::beforeAction($action);
    }

    public function actionIndex(): Response
    {
        Craft::$app->getView()->registerAssetBundle(DashboardAsset::class);

        $sort = (string) Craft::$app->getRequest()->getQueryParam('sort', 'nextSubmissionAt');
        $dir = (string) Craft::$app->getRequest()->getQueryParam('dir', 'asc');

        return $this->renderTemplate(ArchiveOrgBackups::HANDLE . '/index', [
            'dashboard' => ArchiveOrgBackups::plugin()->getDashboard()->getDashboardData($sort, $dir),
            'viewerToken' => Craft::$app->getSecurity()->generateRandomString(16),
        ]);
    }

    public function actionRefresh(): Response
    {
        $sort = (string) Craft::$app->getRequest()->getQueryParam('sort', 'nextSubmissionAt');
        $dir = (string) Craft::$app->getRequest()->getQueryParam('dir', 'asc');
        $viewerToken = (string) Craft::$app->getRequest()->getQueryParam('viewerToken', '');
        $visibleIds = array_filter(array_map(
            'intval',
            explode(',', (string) Craft::$app->getRequest()->getQueryParam('visibleTargetIds', ''))
        ));

        if ($viewerToken !== '') {
            ArchiveOrgBackups::plugin()->getLiveWatch()->registerHeartbeat($viewerToken);
        }

        ArchiveOrgBackups::plugin()->getLiveWatch()->tick($viewerToken, $visibleIds);

        return $this->asJson([
            'dashboardHtml' => ArchiveOrgBackups::plugin()->getDashboard()->renderDashboardHtml($sort, $dir),
        ]);
    }
}
