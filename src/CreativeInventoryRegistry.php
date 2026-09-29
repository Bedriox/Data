<?php

declare(strict_types=1);

namespace Bedriox\Data;

use InvalidArgumentException;
use JsonException;
use RuntimeException;
use ValueError;

/** Immutable, ordered creative groups and entries from one admitted normalized dataset. */
final readonly class CreativeInventoryRegistry
{
    public const int MAX_JSON_BYTES = 16_777_216;
    public const int MAX_GROUPS = 256;
    public const int MAX_ENTRIES = 8_192;

    /** @var list<CreativeInventoryGroup> */
    private array $groups;

    /** @var list<CreativeInventoryEntry> */
    private array $entries;

    /** @var array<int, CreativeInventoryGroup> */
    private array $groupsById;

    /** @var array<int, CreativeInventoryEntry> */
    private array $entriesById;

    /**
     * @param list<CreativeInventoryGroup> $groups
     * @param list<CreativeInventoryEntry> $entries
     * @param array<int, CreativeInventoryGroup> $groupsById
     * @param array<int, CreativeInventoryEntry> $entriesById
     */
    private function __construct(array $groups, array $entries, array $groupsById, array $entriesById)
    {
        $this->groups = $groups;
        $this->entries = $entries;
        $this->groupsById = $groupsById;
        $this->entriesById = $entriesById;
    }

    public static function fromJson(
        string $json,
        ItemNetworkRegistry $items,
        NetworkBlockStateRegistry $blocks,
    ): self {
        if ($json === '' || strlen($json) > self::MAX_JSON_BYTES || preg_match('//u', $json) !== 1) {
            throw new RuntimeException('Creative inventory JSON is empty, oversized, or not UTF-8.');
        }
        try {
            $document = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('Creative inventory is not valid bounded JSON.', previous: $error);
        }
        if (!is_array($document) || array_is_list($document) || !self::hasExactKeys($document, ['entries', 'groups', 'schema'])
            || ($document['schema'] ?? null) !== 1
            || !is_array($document['groups']) || !array_is_list($document['groups'])
            || !is_array($document['entries']) || !array_is_list($document['entries'])
            || count($document['groups']) > self::MAX_GROUPS
            || $document['entries'] === [] || count($document['entries']) > self::MAX_ENTRIES) {
            throw new RuntimeException('Creative inventory has an invalid top-level shape or count.');
        }

        $groups = [];
        $groupsById = [];
        foreach ($document['groups'] as $data) {
            if (!is_array($data) || !self::hasExactKeys($data, ['category', 'icon', 'id', 'name'])
                || !is_int($data['id']) || $data['id'] < 0 || $data['id'] > 65_535
                || !is_string($data['category']) || !is_string($data['name'])
                || strlen($data['name']) > 256 || preg_match('//u', $data['name']) !== 1
                || !is_array($data['icon']) || isset($groupsById[$data['id']])) {
                throw new RuntimeException('Creative inventory contains an invalid or duplicate group.');
            }
            try {
                $category = CreativeInventoryCategory::from($data['category']);
            } catch (ValueError $error) {
                throw new RuntimeException('Creative inventory group category is invalid.', previous: $error);
            }
            $group = CreativeInventoryGroup::fromNormalized(
                $data['id'],
                $category,
                $data['name'],
                CreativeInventoryItem::fromNormalized($data['icon'], $items, $blocks),
            );
            $groups[] = $group;
            $groupsById[$group->id()] = $group;
        }

        $entries = [];
        $entriesById = [];
        foreach ($document['entries'] as $data) {
            if (!is_array($data) || !self::hasExactKeys($data, ['category', 'creativeNetworkId', 'groupId', 'item'])
                || !is_int($data['creativeNetworkId']) || $data['creativeNetworkId'] < 1 || $data['creativeNetworkId'] > 1_000_000
                || !is_string($data['category']) || ($data['groupId'] !== null && !is_int($data['groupId']))
                || !is_array($data['item']) || isset($entriesById[$data['creativeNetworkId']])) {
                throw new RuntimeException('Creative inventory contains an invalid or duplicate entry.');
            }
            try {
                $category = CreativeInventoryCategory::from($data['category']);
            } catch (ValueError $error) {
                throw new RuntimeException('Creative inventory entry category is invalid.', previous: $error);
            }
            if (is_int($data['groupId'])) {
                $group = $groupsById[$data['groupId']] ?? null;
                if ($group === null || $group->category() !== $category) {
                    throw new RuntimeException('Creative inventory entry references an unknown or cross-category group.');
                }
            }
            $entry = CreativeInventoryEntry::fromNormalized(
                $data['creativeNetworkId'],
                $category,
                $data['groupId'],
                CreativeInventoryItem::fromNormalized($data['item'], $items, $blocks),
            );
            $entries[] = $entry;
            $entriesById[$entry->creativeNetworkId()] = $entry;
        }

        return new self($groups, $entries, $groupsById, $entriesById);
    }

    /** @return list<CreativeInventoryGroup> */
    public function groups(): array
    {
        return $this->groups;
    }

    /** @return list<CreativeInventoryEntry> */
    public function entries(): array
    {
        return $this->entries;
    }

    public function group(int $id): CreativeInventoryGroup
    {
        return $this->groupsById[$id] ?? throw new InvalidArgumentException('Creative group ID is not admitted.');
    }

    public function entry(int $creativeNetworkId): CreativeInventoryEntry
    {
        return $this->entriesById[$creativeNetworkId]
            ?? throw new InvalidArgumentException('Creative network ID is not admitted.');
    }

    /**
     * @param array<array-key, mixed> $data
     * @param list<string> $expected
     */
    private static function hasExactKeys(array $data, array $expected): bool
    {
        if (count($data) !== count($expected)) {
            return false;
        }
        foreach ($expected as $key) {
            if (!array_key_exists($key, $data)) {
                return false;
            }
        }
        return true;
    }
}
