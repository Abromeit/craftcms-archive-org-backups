<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\console\Application as ConsoleApplication;
use craft\events\ElementEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\UrlHelper;
use craft\helpers\ElementHelper;
use craft\services\Elements;
use craft\web\UrlManager;
use craft\web\View;
use yii\base\Event;
use abromeit\archiveorgbackups\jobs\SubmitDueTargetsJob;
use abromeit\archiveorgbackups\jobs\SyncTargetsJob;
use abromeit\archiveorgbackups\models\Settings;
use abromeit\archiveorgbackups\services\DashboardService;
use abromeit\archiveorgbackups\services\HeartbeatService;
use abromeit\archiveorgbackups\services\IndexingService;
use abromeit\archiveorgbackups\services\LiveWatchService;
use abromeit\archiveorgbackups\services\QuotaService;
use abromeit\archiveorgbackups\services\SchedulingService;
use abromeit\archiveorgbackups\services\SubmissionService;
use abromeit\archiveorgbackups\services\TargetService;
use abromeit\archiveorgbackups\services\UrlManifestService;

final class ArchiveOrgBackups extends Plugin
{
    public const HANDLE = 'archive-org-backups';

    public const TRANSLATION_CATEGORY = 'archive-org-backups';

    public const INDEXING_PENDING = 'pending';

    public const INDEXING_INDEXED = 'indexed';

    public const INDEXING_FAILED = 'failed';

    public const INDEXING_UNKNOWN = 'unknown';

    public const JOB_STATUS_PENDING = 'pending';

    public const JOB_STATUS_SUCCESS = 'success';

    public const JOB_STATUS_FAILED = 'failed';

    public const JOB_STATUS_QUOTA_EXHAUSTED = 'quota_exhausted';

    public const JOB_STATUS_RETRY = 'retry';

    public const PRIORITY_NEVER_SUBMITTED = 300;

    public const PRIORITY_CHANGED = 200;

    public const PRIORITY_REFRESH = 100;

    public string $schemaVersion = '1.0.0';

    public bool $hasCpSection = true;

    public bool $hasCpSettings = true;

    public static ?self $plugin = null;

    public static function plugin(): self
    {
        if (!self::$plugin instanceof self) {
            throw new \RuntimeException('Archive.org Backups plugin is not initialized.');
        }

        return self::$plugin;
    }

    public function init(): void
    {
        parent::init();

        self::$plugin = $this;

        $this->controllerNamespace = Craft::$app instanceof ConsoleApplication
            ? 'abromeit\\archiveorgbackups\\console\\controllers'
            : 'abromeit\\archiveorgbackups\\controllers';

        $this->setComponents([
            'dashboard' => DashboardService::class,
            'heartbeat' => HeartbeatService::class,
            'indexing' => IndexingService::class,
            'liveWatch' => LiveWatchService::class,
            'manifest' => UrlManifestService::class,
            'quota' => QuotaService::class,
            'scheduling' => SchedulingService::class,
            'submission' => SubmissionService::class,
            'targets' => TargetService::class,
        ]);

        $this->registerTemplateRoots();
        $this->registerRoutes();
        $this->registerElementHooks();

        Craft::$app->onInit(function(): void {
            if (Craft::$app instanceof ConsoleApplication) {
                return;
            }

            $this->getHeartbeat()->ensureScheduled();
        });
    }

    public function afterInstall(): void
    {
        parent::afterInstall();
        $this->bootstrapTargets();
        $this->queueMaintenance();
    }

    public function afterSaveSettings(): void
    {
        parent::afterSaveSettings();

        $this->bootstrapTargets();
        $this->queueMaintenance();
    }

    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();

        if ($item === null) {
            return null;
        }

        $item['label'] = Craft::t(self::TRANSLATION_CATEGORY, 'Archive.org Backups');
        $item['url'] = self::HANDLE;
        $item['subnav'] = [
            'dashboard' => [
                'label' => Craft::t(self::TRANSLATION_CATEGORY, 'Overview'),
                'url' => self::HANDLE,
            ],
            'settings' => [
                'label' => Craft::t(self::TRANSLATION_CATEGORY, 'Settings'),
                'url' => UrlHelper::cpUrl('settings/plugins/' . self::HANDLE),
            ],
        ];

        return $item;
    }

    public function getDashboard(): DashboardService
    {
        /** @var DashboardService */
        return $this->get('dashboard');
    }

    public function getHeartbeat(): HeartbeatService
    {
        /** @var HeartbeatService */
        return $this->get('heartbeat');
    }

    public function getIndexing(): IndexingService
    {
        /** @var IndexingService */
        return $this->get('indexing');
    }

    public function getLiveWatch(): LiveWatchService
    {
        /** @var LiveWatchService */
        return $this->get('liveWatch');
    }

    public function getManifest(): UrlManifestService
    {
        /** @var UrlManifestService */
        return $this->get('manifest');
    }

    public function getQuota(): QuotaService
    {
        /** @var QuotaService */
        return $this->get('quota');
    }

    public function getScheduling(): SchedulingService
    {
        /** @var SchedulingService */
        return $this->get('scheduling');
    }

    public function getSubmission(): SubmissionService
    {
        /** @var SubmissionService */
        return $this->get('submission');
    }

    public function getTargets(): TargetService
    {
        /** @var TargetService */
        return $this->get('targets');
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate(
            self::HANDLE . '/settings',
            [
                'settings' => $this->getSettings(),
                'sectionOptions' => $this->getManifest()->getSectionOptions(),
            ]
        );
    }

    private function registerTemplateRoots(): void
    {
        Event::on(
            View::class,
            View::EVENT_REGISTER_CP_TEMPLATE_ROOTS,
            function(RegisterTemplateRootsEvent $event): void {
                $event->roots[self::HANDLE] = __DIR__ . '/templates';
            }
        );
    }

    private function registerRoutes(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event): void {
                $event->rules[self::HANDLE] = self::HANDLE . '/dashboard/index';
                $event->rules[self::HANDLE . '/refresh'] = self::HANDLE . '/dashboard/refresh';
            }
        );
    }

    private function registerElementHooks(): void
    {
        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_SAVE_ELEMENT,
            function(ElementEvent $event): void {
                if (!$event->element instanceof \craft\elements\Entry) {
                    return;
                }

                if (ElementHelper::isDraftOrRevision($event->element)) {
                    return;
                }

                $this->getTargets()->syncEntry($event->element);
            }
        );

        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_DELETE_ELEMENT,
            function(ElementEvent $event): void {
                if (!$event->element instanceof \craft\elements\Entry) {
                    return;
                }

                $this->getTargets()->retireEntry($event->element->id);
            }
        );
    }

    private function queueMaintenance(): void
    {
        Craft::$app->getQueue()->push(new SyncTargetsJob());
        Craft::$app->getQueue()->push(new SubmitDueTargetsJob());
        $this->getHeartbeat()->ensureScheduled();
    }

    private function bootstrapTargets(): void
    {
        $this->getTargets()->retireInvalidTargets();
        $this->getTargets()->primeManifest(100);
    }
}
