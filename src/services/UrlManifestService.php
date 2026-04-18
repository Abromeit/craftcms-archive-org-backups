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
    private const SEO_SETTINGS_FIELD_CLASS = 'nystudio107\\seomatic\\fields\\SeoSettings';

    private const EXCLUDED_ROBOTS_DIRECTIVES = [
        'noarchive',
        'noindex',
        'none',
    ];

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

        $section = $entry->getSection();

        if (!$section instanceof Section) {
            return false;
        }

        if (!in_array($section->uid, ArchiveOrgBackups::plugin()->getSettings()->enabledSectionUids, true)) {
            return false;
        }

        if ($this->hasExcludedRobotsDirectives($entry)) {
            return false;
        }

        if ($entry->uri === null || $entry->uri === '__home__') {
            return $entry->getUrl() !== null;
        }

        return true;
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

    public static function containsExcludedRobotsDirectives(?string $robots): bool
    {
        if (!is_string($robots) || trim($robots) === '') {
            return false;
        }

        $directives = preg_split('/\s*,\s*/', strtolower($robots));

        if (!is_array($directives)) {
            return false;
        }

        foreach ($directives as $directive) {
            if (!in_array($directive, self::EXCLUDED_ROBOTS_DIRECTIVES, true)) {
                continue;
            }

            return true;
        }

        return false;
    }

    private function hasExcludedRobotsDirectives(Entry $entry): bool
    {
        $fieldLayout = $entry->getFieldLayout();

        if ($fieldLayout === null) {
            return false;
        }

        foreach ($fieldLayout->getCustomFields() as $field) {
            if ($field::class !== self::SEO_SETTINGS_FIELD_CLASS) {
                continue;
            }

            if (!$this->isSeoSettingsRobotsField($field)) {
                continue;
            }

            $robots = $this->extractSeoSettingsRobotsDirective($entry, $field->handle);

            if (!self::containsExcludedRobotsDirectives($robots)) {
                continue;
            }

            return true;
        }

        return false;
    }

    private function isSeoSettingsRobotsField(object $field): bool
    {
        if (empty($field->generalTabEnabled ?? null)) {
            return false;
        }

        $generalEnabledFields = (array) ($field->generalEnabledFields ?? []);

        return in_array('robots', $generalEnabledFields, false);
    }

    private function extractSeoSettingsRobotsDirective(Entry $entry, string $fieldHandle): ?string
    {
        $serializedFieldValues = $entry->getSerializedFieldValues([$fieldHandle]);
        $serializedValue = $serializedFieldValues[$fieldHandle] ?? null;
        $robots = $this->robotsDirectiveFromSerializedSeoSettings($serializedValue);

        if (is_string($robots)) {
            return $robots;
        }

        $value = $entry->getFieldValue($fieldHandle);
        $metaGlobalVars = $this->arrayValue($value, 'metaGlobalVars');
        $robots = $this->arrayValue($metaGlobalVars, 'robots');

        if (!is_string($robots)) {
            return null;
        }

        return $robots;
    }

    private function robotsDirectiveFromSerializedSeoSettings(mixed $value): ?string
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                $value = $decoded;
            }
        }

        $metaGlobalVars = $this->arrayValue($value, 'metaGlobalVars');
        $robots = $this->arrayValue($metaGlobalVars, 'robots');

        if (!is_string($robots)) {
            return null;
        }

        return $robots;
    }

    private function arrayValue(mixed $value, string $key): mixed
    {
        if (is_array($value)) {
            return $value[$key] ?? null;
        }

        if (!is_object($value)) {
            return null;
        }

        return $value->$key ?? null;
    }
}
