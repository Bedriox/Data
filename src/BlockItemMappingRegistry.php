<?php

declare(strict_types=1);

namespace Bedriox\Data;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

/** Immutable item-to-default-block-state mappings from normalized admitted data. */
final readonly class BlockItemMappingRegistry
{
    public const int MAX_JSON_BYTES = 4_194_304;
    public const int MAX_MAPPINGS = 8_192;

    /** @var array<string, BlockItemMapping> */
    private array $byItemIdentifier;

    /** @var array<string, BlockItemMapping> */
    private array $byBlockStateKey;

    /**
     * @param array<string, BlockItemMapping> $byItemIdentifier
     * @param array<string, BlockItemMapping> $byBlockStateKey
     */
    private function __construct(array $byItemIdentifier, array $byBlockStateKey)
    {
        $this->byItemIdentifier = $byItemIdentifier;
        $this->byBlockStateKey = $byBlockStateKey;
    }

    public static function fromJson(
        string $json,
        ItemNetworkRegistry $items,
        NetworkBlockStateRegistry $blocks,
    ): self {
        if ($json === '' || strlen($json) > self::MAX_JSON_BYTES || preg_match('//u', $json) !== 1) {
            throw new RuntimeException('Block-item mapping JSON is empty, oversized, or not UTF-8.');
        }
        try {
            $document = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('Block-item mappings are not valid bounded JSON.', previous: $error);
        }
        if (!is_array($document) || array_is_list($document) || !self::hasExactKeys($document, ['mappings', 'schema'])
            || ($document['schema'] ?? null) !== 1 || !is_array($document['mappings']) || !array_is_list($document['mappings'])
            || count($document['mappings']) > self::MAX_MAPPINGS) {
            throw new RuntimeException('Block-item mappings have an invalid top-level shape or count.');
        }
        $byItem = [];
        $byState = [];
        foreach ($document['mappings'] as $data) {
            if (!is_array($data) || !self::hasExactKeys($data, ['blockState', 'itemIdentifier'])
                || !is_string($data['itemIdentifier']) || !is_array($data['blockState'])
                || !self::hasExactKeys($data['blockState'], ['identifier', 'properties'])
                || !is_string($data['blockState']['identifier']) || !is_array($data['blockState']['properties'])) {
                throw new RuntimeException('Block-item mappings contain an invalid entry.');
            }
            try {
                $items->definitionForIdentifier($data['itemIdentifier']);
                $state = CanonicalBlockState::from($data['blockState']['identifier'], $data['blockState']['properties']);
                $blocks->networkRuntimeId($state);
            } catch (InvalidArgumentException $error) {
                throw new RuntimeException('Block-item mapping references an unknown item or block state.', previous: $error);
            }
            $mapping = new BlockItemMapping($data['itemIdentifier'], $state);
            if (isset($byItem[$mapping->itemIdentifier()]) || isset($byState[$state->canonicalKey()])) {
                throw new RuntimeException('Block-item mappings contain a duplicate item or block state.');
            }
            $byItem[$mapping->itemIdentifier()] = $mapping;
            $byState[$state->canonicalKey()] = $mapping;
        }
        return new self($byItem, $byState);
    }

    /** @return array<string, BlockItemMapping> */
    public function mappings(): array
    {
        return $this->byItemIdentifier;
    }

    public function mappingForItem(string $identifier): BlockItemMapping
    {
        return $this->byItemIdentifier[$identifier]
            ?? throw new InvalidArgumentException('Item identifier has no admitted block mapping.');
    }

    public function mappingForBlockState(CanonicalBlockState $state): BlockItemMapping
    {
        return $this->byBlockStateKey[$state->canonicalKey()]
            ?? throw new InvalidArgumentException('Block state has no admitted item mapping.');
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
