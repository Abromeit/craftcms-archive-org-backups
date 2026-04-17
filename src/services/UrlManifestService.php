<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups\services;

use Craft;
use craft\base\Component;
use craft\elements\Entry;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\models\Section;
use abromeit\archiveorgbackups\ArchiveOrgBackups;

final class UrlManifestService extends Component
{
    /**
     * @return array<int, array{label:string, value:string}>
     */
    public function getSectionOptions(): array
    {
        $options = [];

        foreach (Craft::$app->getEntries()->getAllSections() as $section) {
            $options[] = [
                'label' => $section->name,
                'value' => $section->uid,
            ];
        }

        return $options;
    }

    /**
     * @return array<int, array{elementId:int, siteId:int, url:string, sourceDateUpdated:string}>
     */
    public function getEntryManifestById(int $entryId): array
    {
        $manifest = [];

        $entries = Entry::find()
            ->id($entryId)
            ->status(null)
            ->site('*')
            ->trashed(false)
            ->all();

        foreach ($entries as $entry) {
            if (!$this->isTrackableEntry($entry)) {
                continue;
            }

            $url = $entry->getUrl();

            if ($url === null || $url === '') {
                continue;
            }

            $manifest[] = [
                'elementId' => (int) $entry->id,
                'siteId' => (int) $entry->siteId,
                'url' => $url,
                'sourceDateUpdated' => DateTimeHelper::toDateTime($entry->dateUpdated)->format('Y-m-d H:i:s'),
            ];
        }

        return $manifest;
    }

    /**
     * @return array<int, int>
     */
    public function getTrackedEntryIds(int $offset, int $limit): array
    {
        $sectionIds = $this->getEnabledSectionIds();

        if ($sectionIds === []) {
            return [];
        }

        $ids = Entry::find()
            ->status(null)
            ->site('*')
            ->sectionId($sectionIds)
            ->select(['elements.id'])
            ->distinct()
            ->orderBy(['elements.id' => SORT_ASC])
            ->offset($offset)
            ->limit($limit)
            ->column();

        return array_values(array_unique(array_map('intval', $ids)));
    }

    public function isTrackableEntry(Entry $entry): bool
    {
        if ($entry->getIsDraft() || $entry->getIsRevision()) {
            return false;
        }

        if (!$entry->enabled || !$entry->enabledForSite) {
            return false;
        }

        if ($entry->uri === null || $entry->uri === '__home__') {
            return $entry->getUrl() !== null;
        }

        $section = $entry->getSection();

        if (!$section instanceof Section) {
            return false;
        }

        return in_array($section->uid, ArchiveOrgBackups::plugin()->getSettings()->enabledSectionUids, true);
    }

    /**
     * @return int[]
     */
    public function getEnabledSectionIds(): array
    {
        $uids = ArchiveOrgBackups::plugin()->getSettings()->enabledSectionUids;

        if ($uids === []) {
            return [];
        }

        $sections = Craft::$app->getEntries()->getAllSections();
        $sectionMap = ArrayHelper::index($sections, 'uid');
        $ids = [];

        foreach ($uids as $uid) {
            $section = $sectionMap[$uid] ?? null;

            if (!$section instanceof Section) {
                continue;
            }

            $ids[] = (int) $section->id;
        }

        return array_values(array_unique($ids));
    }
}
