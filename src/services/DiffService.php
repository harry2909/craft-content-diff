<?php

namespace roadsterworks\craftcontentdiff\services;

use Craft;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\helpers\Db;
use yii\base\Component;

/**
 * Builds entry data for the current site and compares it with remote data.
 * Use for “current site” data; remote data is fetched via fetchRemoteEntriesBySection().
 */
class DiffService extends Component
{
    private const BATCH_SIZE = 100;

    /** @var string|null Last fetch error message for UI when fetch fails */
    private ?string $lastFetchError = null;

    /**
     * Entries grouped by section handle (content only; no dateCreated/dateUpdated).
     *
     * @return array<string, array<int, array>>
     */
    public function getEntriesBySection(): array
    {
        $sections = Craft::$app->entries->getAllSections();
        $entriesBySection = [];

        foreach ($sections as $section) {
            $entriesBySection[$section->handle] = $this->getEntriesForSection($section->handle);
        }

        return $entriesBySection;
    }

    /**
     * Entries for one section, batched (content only).
     *
     * @return array<int, array>
     */
    public function getEntriesForSection(string $sectionHandle): array
    {
        $result = [];
        $query = Entry::find()
            ->section($sectionHandle)
            ->eagerly();

        foreach (Db::batch($query, self::BATCH_SIZE) as $entries) {
            foreach ($entries as $entry) {
                $result[] = $this->entryToDiffArray($entry);
            }
        }

        return $result;
    }

    /**
     * Converts an entry to a diff payload (content only; dates excluded).
     *
     * @return array<string, mixed>
     */
    public function entryToDiffArray(Entry $entry): array
    {
        $section = $entry->getSection();
        $type = $entry->getType();
        $core = [
            'id' => $entry->id,
            'uid' => $entry->uid,
            'title' => $entry->title,
            'slug' => $entry->slug,
            'uri' => $entry->uri,
            'status' => $entry->status,
            'sectionId' => $entry->sectionId,
            'sectionHandle' => $section ? $section->handle : null,
            'typeId' => $entry->typeId,
            'typeHandle' => $type ? $type->handle : null,
            'authorId' => $entry->authorId,
            'siteId' => $entry->siteId,
        ];

        return array_merge($core, [
            'fields' => $entry->getSerializedFieldValues(),
        ]);
    }

    /**
     * Fake compare result for testing the UI (one section, sample labels).
     *
     * @param array<string, array<int, array>> $currentBySection used to pick section handle
     * @return array<string, array{added: array, removed: array, changed: array}>
     */
    public function getFakeCompareResult(array $currentBySection): array
    {
        $sectionHandle = array_key_first($currentBySection);
        if ($sectionHandle === null) {
            $sectionHandle = 'content';
        }

        $deletedEntry = [
            'id' => 90001,
            'uid' => 'fake-deleted-001',
            'title' => 'Sample entry (deleted on other env)',
            'slug' => 'sample-deleted',
            'typeHandle' => 'sample',
            'sectionHandle' => $sectionHandle,
            'fields' => ['text' => 'This entry exists here but was removed on the other environment.'],
        ];

        $createdEntry = [
            'id' => 90002,
            'uid' => 'fake-created-001',
            'title' => 'Sample entry (created on other env)',
            'slug' => 'sample-created',
            'typeHandle' => 'sample',
            'sectionHandle' => $sectionHandle,
            'fields' => ['text' => 'This entry only exists on the other environment.'],
        ];

        $matrixCurrent = [
            'new1' => [
                'type' => 'paragraph',
                'title' => null,
                'slug' => null,
                'enabled' => true,
                'collapsed' => false,
                'fields' => [
                    'heading' => 'Intro paragraph (current)',
                    'text' => 'Body copy on this environment.',
                ],
            ],
            'new2' => [
                'type' => 'image',
                'title' => null,
                'slug' => null,
                'enabled' => true,
                'collapsed' => false,
                'fields' => [
                    'caption' => 'Caption on this side.',
                    'items' => [ // nested matrix
                        'new1' => [
                            'type' => 'item',
                            'title' => null,
                            'slug' => null,
                            'enabled' => true,
                            'collapsed' => false,
                            'fields' => ['heading' => 'Nested item (current)'],
                        ],
                    ],
                ],
            ],
        ];
        $matrixRemote = [
            'new1' => [
                'type' => 'paragraph',
                'title' => null,
                'slug' => null,
                'enabled' => true,
                'collapsed' => false,
                'fields' => [
                    'heading' => 'Intro paragraph (remote)',
                    'text' => 'Body copy on the other environment.',
                ],
            ],
            'new2' => [
                'type' => 'image',
                'title' => null,
                'slug' => null,
                'enabled' => true,
                'collapsed' => false,
                'fields' => [
                    'caption' => 'Caption on the other side.',
                    'items' => [
                        'new1' => [
                            'type' => 'item',
                            'title' => null,
                            'slug' => null,
                            'enabled' => true,
                            'collapsed' => false,
                            'fields' => ['heading' => 'Nested item (remote)'],
                        ],
                    ],
                ],
            ],
        ];

        $updatedCurrent = [
            'id' => 90003,
            'uid' => 'fake-updated-001',
            'title' => 'Sample entry (updated)',
            'slug' => 'sample-updated',
            'typeHandle' => 'sample',
            'sectionHandle' => $sectionHandle,
            'fields' => [
                'text' => 'Content on this environment (here).',
                'summary' => 'Short summary on this side.',
                'body' => $matrixCurrent,
            ],
        ];
        $updatedRemote = [
            'id' => 90003,
            'uid' => 'fake-updated-001',
            'title' => 'Sample entry (updated on other env)',
            'slug' => 'sample-updated-remote',
            'typeHandle' => 'sample',
            'sectionHandle' => $sectionHandle,
            'fields' => [
                'text' => 'Content on the other environment (there).',
                'summary' => 'Short summary on the other side.',
                'body' => $matrixRemote,
            ],
        ];

        $fakeCurrent = [$sectionHandle => [$deletedEntry, $updatedCurrent]];
        $fakeRemote = [$sectionHandle => [$updatedRemote, $createdEntry]];
        return $this->compare($fakeCurrent, $fakeRemote);
    }

    /**
     * Fetches entries from a remote Craft install’s diff endpoint.
     * Uses site action URL so token auth applies without CP login. Optional HTTP Basic for server-level auth.
     *
     * @param string $baseUrl Remote base URL (e.g. https://staging.example.com)
     * @param string $token API key from plugin Settings (same on both environments)
     * @param string|null $httpUser Optional HTTP Basic username (env: CONTENT_DIFF_*_HTTP_USER)
     * @param string|null $httpPassword Optional HTTP Basic password (env: CONTENT_DIFF_*_HTTP_PASSWORD)
     * @param string $environment Environment to request (local, staging, or production) so the remote’s response reflects it
     * @param string|null $environmentLabel Label for error messages (e.g. "Production", "Staging")
     * @return array<string, array<int, array>> entries by section, or empty on failure
     */
    public function fetchRemoteEntriesBySection(string $baseUrl, string $token, ?string $httpUser = null, ?string $httpPassword = null, string $environment = 'staging', ?string $environmentLabel = null): array
    {
        $this->lastFetchError = null;

        $baseUrl = rtrim($baseUrl, '/');
        $path = '/actions/craft-content-diff/diff?environment=' . rawurlencode($environment);
        $url = $baseUrl . $path;

        $headers = "User-Agent: Craft-Content-Diff/1.0\r\nX-Content-Diff-Token: " . str_replace(["\r", "\n"], '', $token) . "\r\n";
        $authUser = is_string($httpUser) ? $httpUser : '';
        $authPass = is_string($httpPassword) ? $httpPassword : '';
        if ($authUser !== '' && $authPass !== '') {
            $headers .= "Authorization: Basic " . base64_encode($authUser . ':' . $authPass) . "\r\n";
        }

        $ctx = stream_context_create([
            'http' => [
                'timeout' => 15,
                'ignore_errors' => true,
                'header' => $headers,
            ],
        ]);
        Craft::info('Content Diff: fetching remote. url=' . $url, 'craft-content-diff');
        $json = @file_get_contents($url, false, $ctx);
        $responseLine = isset($http_response_header[0]) ? $http_response_header[0] : null;
        $httpStatus = $this->parseHttpStatus($responseLine);

        if ($json === false) {
            $this->lastFetchError = $httpStatus !== null
                ? 'Remote returned HTTP ' . $httpStatus . '. Check the URL and API key on the remote environment. If the site is behind Cloudflare or a WAF, see the README (Cloudflare section).'
                : 'Connection failed (timeout, DNS, or URL unreachable). Check the URL and that this server can reach it. If the site is behind Cloudflare or a WAF, see the README (Cloudflare section).';
            // Second arg must be category string; passing an array causes Yii log Target strpos() to fail
            Craft::error('Content Diff: remote fetch failed. url=' . $url . ' httpStatus=' . ($httpStatus ?? 'null'), 'craft-content-diff');
            return [];
        }

        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['entriesBySection']) || !is_array($data['entriesBySection'])) {
            $remoteMessage = null;
            if (is_array($data) && (isset($data['message']) || isset($data['error']))) {
                $remoteMessage = $data['message'] ?? $data['error'];
                $remoteMessage = is_string($remoteMessage) ? $remoteMessage : null;
            }
            $prefix = $environmentLabel !== null && $environmentLabel !== '' ? $environmentLabel . ' returned: ' : 'Remote returned: ';
            $this->lastFetchError = $remoteMessage !== null
                ? $prefix . $remoteMessage
                : 'Remote returned invalid response (not JSON or missing data). Check the URL and API key on the remote environment. If the site is behind Cloudflare or a WAF, see the README (Cloudflare section).';
            Craft::error('Content Diff: remote returned invalid or error response. url=' . $url . ' httpStatus=' . ($httpStatus ?? 'null'), 'craft-content-diff');
            return [];
        }

        $this->lastFetchError = null;
        return $data['entriesBySection'];
    }

    /**
     * Returns the last fetch error message (for UI). Clears it after reading.
     */
    public function getLastFetchError(): ?string
    {
        $err = $this->lastFetchError;
        $this->lastFetchError = null;
        return $err;
    }

    /**
     * Parses HTTP status code from response line (e.g. "HTTP/1.1 401 Unauthorized" -> 401).
     */
    private function parseHttpStatus(?string $responseLine): ?int
    {
        if ($responseLine === null || $responseLine === '') {
            return null;
        }
        if (preg_match('/^HTTP\/\d\.\d\s+(\d+)/', $responseLine, $m)) {
            return (int) $m[1];
        }
        return null;
    }

    /**
     * Compares current and remote entries by section (by uid); returns added, removed, and changed (field-level).
     *
     * @param array<string, array<int, array>> $currentBySection section handle => list of entry arrays (with uid)
     * @param array<string, array<int, array>> $remoteBySection section handle => list of entry arrays (with uid)
     * @return array<string, array{added: array, removed: array, changed: array}>
     */
    public function compare(array $currentBySection, array $remoteBySection): array
    {
        $allSections = array_unique(array_merge(array_keys($currentBySection), array_keys($remoteBySection)));
        $result = [];

        foreach ($allSections as $sectionHandle) {
            $current = $currentBySection[$sectionHandle] ?? [];
            $remote = $remoteBySection[$sectionHandle] ?? [];
            $currentByUid = $this->indexByUid($current);
            $remoteByUid = $this->indexByUid($remote);

            $currentUids = array_keys($currentByUid);
            $remoteUids = array_keys($remoteByUid);
            $added = array_values(array_diff($remoteUids, $currentUids));
            $removed = array_values(array_diff($currentUids, $remoteUids));
            $common = array_intersect($currentUids, $remoteUids);

            $changed = [];
            foreach ($common as $uid) {
                $diff = $this->entryDiff($currentByUid[$uid], $remoteByUid[$uid]);
                if ($diff !== null) {
                    $changed[$uid] = $diff;
                }
            }

            $result[$sectionHandle] = [
                'added' => array_map(fn(string $uid) => $remoteByUid[$uid], $added),
                'removed' => array_map(fn(string $uid) => $currentByUid[$uid], $removed),
                'changed' => $changed,
            ];
        }

        return $result;
    }

    /**
     * Indexes entries by uid (skips entries without a string uid).
     *
     * @param array<int, array> $entries
     * @return array<string, array>
     */
    private function indexByUid(array $entries): array
    {
        $byUid = [];
        foreach ($entries as $entry) {
            $uid = $entry['uid'] ?? null;
            if (is_string($uid)) {
                $byUid[$uid] = $entry;
            }
        }
        return $byUid;
    }

    /**
     * Compares two entry arrays. Returns null if identical.
     *
     * @return array{current: array, remote: array, fieldDiffs: array}|null
     */
    private function entryDiff(array $current, array $remote): ?array
    {
        $fieldDiffs = [];
        $keys = array_unique(array_merge(array_keys($current), array_keys($remote)));

        foreach ($keys as $key) {
            $c = $current[$key] ?? null;
            $r = $remote[$key] ?? null;
            if ($key === 'fields' && is_array($c) && is_array($r)) {
                foreach (array_unique(array_merge(array_keys($c), array_keys($r))) as $fieldHandle) {
                    $fc = $c[$fieldHandle] ?? null;
                    $fr = $r[$fieldHandle] ?? null;
                    if (!$this->valueChanged($fc, $fr)) {
                        continue;
                    }
                    if ($this->isMatrixLike($fc) || $this->isMatrixLike($fr)) {
                        $expanded = $this->expandMatrixFieldDiffs($fieldHandle, $fc, $fr, 10);
                        foreach ($expanded as $subKey => $subPair) {
                            $fieldDiffs[$subKey] = $subPair;
                        }
                        if (empty($expanded)) {
                            $fieldDiffs[$fieldHandle] = ['current' => $fc, 'remote' => $fr];
                        }
                    } else {
                        $fieldDiffs[$fieldHandle] = ['current' => $fc, 'remote' => $fr];
                    }
                }
                continue;
            }
            if ($this->valueChanged($c, $r)) {
                $fieldDiffs[$key] = ['current' => $c, 'remote' => $r];
            }
        }

        if (empty($fieldDiffs)) {
            return null;
        }

        return [
            'current' => $current,
            'remote' => $remote,
            'fieldDiffs' => $fieldDiffs,
        ];
    }

    /**
     * Whether two values differ (deep comparison for arrays; array key order is ignored).
     */
    private function valueChanged(mixed $a, mixed $b): bool
    {
        if ($a === $b) {
            return false;
        }
        if (is_array($a) && is_array($b)) {
            return !$this->arraysEqualDeep($a, $b);
        }
        if (is_array($a) || is_array($b)) {
            return true;
        }
        return (string) $a !== (string) $b;
    }

    /**
     * Deep comparison of two arrays; key order does not matter.
     */
    private function arraysEqualDeep(array $a, array $b): bool
    {
        if (count($a) !== count($b)) {
            return false;
        }
        foreach ($a as $k => $v) {
            if (!array_key_exists($k, $b)) {
                return false;
            }
            $bv = $b[$k];
            if (is_array($v) && is_array($bv)) {
                if (!$this->arraysEqualDeep($v, $bv)) {
                    return false;
                }
            } elseif ($v !== $bv) {
                return false;
            }
        }
        return true;
    }

    /**
     * Whether the value looks like Craft’s Matrix/block serialisation (id => block with type, fields, etc.).
     */
    private function isMatrixLike(mixed $val): bool
    {
        if (!is_array($val)) {
            return false;
        }
        foreach ($val as $block) {
            if (!is_array($block)) {
                return false;
            }
            if (!isset($block['type'])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Expands matrix/block field diffs into labelled keys (fieldHandle — Block N (type): subfield).
     * Recurses into nested matrix up to $depth levels; blocks compared by position.
     *
     * @param int $depth Remaining recursion depth (stops at 0)
     * @return array<string, array{current: mixed, remote: mixed}>
     */
    private function expandMatrixFieldDiffs(string $fieldHandle, mixed $current, mixed $remote, int $depth = 10): array
    {
        $currentBlocks = is_array($current) ? array_values($current) : [];
        $remoteBlocks = is_array($remote) ? array_values($remote) : [];
        $out = [];
        $maxIndex = max(count($currentBlocks), count($remoteBlocks));
        for ($i = 0; $i < $maxIndex; $i++) {
            $cBlock = $currentBlocks[$i] ?? null;
            $rBlock = $remoteBlocks[$i] ?? null;
            $cTypeRaw = is_array($cBlock) ? ($cBlock['type'] ?? '?') : '?';
            $rTypeRaw = is_array($rBlock) ? ($rBlock['type'] ?? '?') : '?';
            $cType = is_string($cTypeRaw) ? $cTypeRaw : (is_scalar($cTypeRaw) ? (string) $cTypeRaw : '?');
            $rType = is_string($rTypeRaw) ? $rTypeRaw : (is_scalar($rTypeRaw) ? (string) $rTypeRaw : '?');
            $blockNum = $i + 1;
            if ($cType !== $rType) {
                $out["{$fieldHandle} — Block {$blockNum} (type)"] = [
                    'current' => $cTypeRaw,
                    'remote' => $rTypeRaw,
                ];
            }
            $cFields = is_array($cBlock) && isset($cBlock['fields']) && is_array($cBlock['fields']) ? $cBlock['fields'] : [];
            $rFields = is_array($rBlock) && isset($rBlock['fields']) && is_array($rBlock['fields']) ? $rBlock['fields'] : [];
            $typeLabel = $cType !== '?' ? $cType : $rType;
            $blockPrefix = "{$fieldHandle} — Block {$blockNum} ({$typeLabel})";
            $blockKeys = ['title', 'slug', 'enabled', 'collapsed'];
            foreach ($blockKeys as $bk) {
                $cv = (is_array($cBlock) && array_key_exists($bk, $cBlock)) ? $cBlock[$bk] : null;
                $rv = (is_array($rBlock) && array_key_exists($bk, $rBlock)) ? $rBlock[$bk] : null;
                if ($this->valueChanged($cv, $rv)) {
                    $out["{$blockPrefix}: {$bk}"] = ['current' => $cv, 'remote' => $rv];
                }
            }
            foreach (array_unique(array_merge(array_keys($cFields), array_keys($rFields))) as $subHandle) {
                $cv = $cFields[$subHandle] ?? null;
                $rv = $rFields[$subHandle] ?? null;
                if (!$this->valueChanged($cv, $rv)) {
                    continue;
                }
                $subKey = "{$blockPrefix}: {$subHandle}";
                if ($depth > 0 && ($this->isMatrixLike($cv) || $this->isMatrixLike($rv))) {
                    $nested = $this->expandMatrixFieldDiffs($subKey, $cv, $rv, $depth - 1);
                    foreach ($nested as $nestedKey => $nestedPair) {
                        $out[$nestedKey] = $nestedPair;
                    }
                    if (empty($nested)) {
                        $out[$subKey] = ['current' => $cv, 'remote' => $rv];
                    }
                } else {
                    $out[$subKey] = ['current' => $cv, 'remote' => $rv];
                }
            }
        }
        return $out;
    }

    /**
     * Enriches compare result so asset/relation IDs show as "filename (ID: x)" where possible.
     *
     * @param array<string, array{added: array, removed: array, changed: array}> $compareResult
     * @param int|null $siteId Site to resolve assets on (null = primary)
     * @return array<string, array{added: array, removed: array, changed: array}>
     */
    public function enrichCompareResultWithAssetLabels(array $compareResult, ?int $siteId = null): array
    {
        $siteId = $siteId ?? Craft::$app->getSites()->getPrimarySite()->id;
        foreach ($compareResult as $sectionHandle => $section) {
            if (empty($section['changed'])) {
                continue;
            }
            foreach ($section['changed'] as $uid => $diff) {
                if (empty($diff['fieldDiffs'])) {
                    continue;
                }
                foreach ($diff['fieldDiffs'] as $key => $pair) {
                    $current = $pair['current'] ?? null;
                    $remote = $pair['remote'] ?? null;
                    $currentDisplay = $this->formatIdsAsAssetLabels($current, $siteId);
                    $remoteDisplay = $this->formatIdsAsAssetLabels($remote, $siteId);
                    if ($currentDisplay !== null || $remoteDisplay !== null) {
                        $diff['fieldDiffs'][$key]['currentDisplay'] = $currentDisplay;
                        $diff['fieldDiffs'][$key]['remoteDisplay'] = $remoteDisplay;
                    }
                }
                $compareResult[$sectionHandle]['changed'][$uid] = $diff;
            }
        }
        return $compareResult;
    }

    /**
     * Formats element ID(s) as "filename (ID: x)" for assets, or "ID: x" for others.
     *
     * @return string|null Display string, or null if value is not ID-like
     */
    private function formatIdsAsAssetLabels(mixed $value, int $siteId): ?string
    {
        $ids = [];
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            $ids = [(int) $value];
        } elseif (is_array($value) && $this->isListOfIds($value)) {
            $ids = array_map('intval', $value);
        } else {
            return null;
        }
        if (empty($ids)) {
            return '';
        }
        $assets = Asset::find()->id($ids)->siteId($siteId)->all();
        $byId = [];
        foreach ($assets as $asset) {
            $name = $asset->filename ?? (string) $asset->id;
            $byId[$asset->id] = is_string($name) ? $name : (string) $asset->id;
        }
        $parts = [];
        foreach ($ids as $id) {
            $label = isset($byId[$id]) ? $byId[$id] : null;
            $parts[] = $label !== null ? $label . ' (ID: ' . $id . ')' : 'ID: ' . $id;
        }
        return implode(', ', $parts);
    }

    /**
     * Whether the value is a list of integer-like IDs.
     */
    private function isListOfIds(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $v) {
            if (!is_int($v) && !(is_string($v) && ctype_digit($v))) {
                return false;
            }
        }
        return true;
    }
}
