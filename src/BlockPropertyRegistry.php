<?php

declare(strict_types=1);

namespace Bedriox\Data;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

/** Immutable physical block metadata joined to the exact admitted canonical palette. */
final readonly class BlockPropertyRegistry
{
    public const int MAX_JSON_BYTES = 67_108_864;
    public const int MAX_STATES = 32_768;

    /** @var list<BlockStateProperties> */
    private array $properties;

    /** @var array<string, BlockStateProperties> */
    private array $byStateKey;

    /**
     * @param list<BlockStateProperties> $properties
     * @param array<string, BlockStateProperties> $byStateKey
     */
    private function __construct(array $properties, array $byStateKey)
    {
        $this->properties = $properties;
        $this->byStateKey = $byStateKey;
    }

    public static function fromJson(string $json, NetworkBlockStateRegistry $states): self
    {
        if ($json === '' || strlen($json) > self::MAX_JSON_BYTES || preg_match('//u', $json) !== 1) {
            throw new RuntimeException('Block-property JSON is empty, oversized, or not UTF-8.');
        }
        try {
            $document = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('Block properties are not valid bounded JSON.', previous: $error);
        }
        $records = is_array($document) ? ($document['states'] ?? null) : null;
        if (!is_array($document) || array_is_list($document) || !self::hasExactKeys($document, ['schema', 'states'])
            || ($document['schema'] ?? null) !== 1 || !is_array($records) || !array_is_list($records)
            || $records === [] || count($records) > self::MAX_STATES) {
            throw new RuntimeException('Block properties have an invalid top-level shape or count.');
        }

        $properties = [];
        $byStateKey = [];
        foreach ($records as $index => $record) {
            if (!is_array($record) || !self::hasExactKeys($record, [
                'blockState', 'burnOdds', 'canContainLiquidSource', 'collisionShape', 'explosionResistance', 'flameOdds',
                'friction', 'hardness', 'isSolid', 'lightDampening', 'lightEmission', 'liquidClipShape',
                'liquidReactionOnTouch', 'mapColor', 'outlineShape', 'requiresCorrectToolForDrops', 'thickness',
                'tintMethod', 'translationKey', 'translucency', 'uiShape', 'visualShape',
            ]) || !is_array($record['blockState'])) {
                throw new RuntimeException('Block properties contain an invalid state record.');
            }
            $state = self::state($record['blockState']);
            try { $states->networkRuntimeId($state); }
            catch (InvalidArgumentException $error) { throw new RuntimeException('Block properties reference an unknown canonical state.', previous: $error); }
            if (isset($byStateKey[$state->canonicalKey()])) {
                throw new RuntimeException('Block properties contain a duplicate canonical state.');
            }
            foreach (['isSolid', 'requiresCorrectToolForDrops', 'canContainLiquidSource'] as $field) {
                if (!is_bool($record[$field])) { throw new RuntimeException('Block properties contain an invalid boolean field.'); }
            }
            foreach (['lightEmission', 'lightDampening'] as $field) {
                if (!is_int($record[$field]) || $record[$field] < 0 || $record[$field] > 15) {
                    throw new RuntimeException('Block properties contain an invalid light field.');
                }
            }
            foreach (['burnOdds', 'flameOdds'] as $field) {
                if (!is_int($record[$field]) || $record[$field] < 0 || $record[$field] > 1_000_000) {
                    throw new RuntimeException('Block properties contain an invalid fire field.');
                }
            }
            foreach (['translationKey', 'tintMethod', 'liquidReactionOnTouch'] as $field) {
                if (!is_string($record[$field]) || $record[$field] === '' || strlen($record[$field]) > 512 || preg_match('//u', $record[$field]) !== 1) {
                    throw new RuntimeException('Block properties contain an invalid text field.');
                }
            }
            if (!is_string($record['mapColor']) || preg_match('/^#[a-fA-F0-9]{8}$/D', $record['mapColor']) !== 1) {
                throw new RuntimeException('Block properties contain an invalid map colour.');
            }
            $numbers = [];
            foreach (['hardness', 'explosionResistance', 'friction', 'thickness', 'translucency'] as $field) {
                $numbers[$field] = self::number($record[$field]);
            }
            if (!is_array($record['collisionShape']) || !array_is_list($record['collisionShape']) || count($record['collisionShape']) > 64) {
                throw new RuntimeException('Block properties contain an invalid collision shape.');
            }
            $collision = [];
            foreach ($record['collisionShape'] as $box) {
                if (!is_array($box)) { throw new RuntimeException('Block properties contain an invalid collision box.'); }
                $coordinates = self::shape($box);
                if ($coordinates[0] >= $coordinates[3] || $coordinates[1] >= $coordinates[4] || $coordinates[2] >= $coordinates[5]) {
                    throw new RuntimeException('Block properties contain a collision box with inverted bounds.');
                }
                $collision[] = new VoxelBox(...$coordinates);
            }
            $value = new BlockStateProperties(
                $state,
                $record['translationKey'],
                $record['isSolid'],
                $numbers['hardness'],
                $numbers['explosionResistance'],
                $numbers['friction'],
                $numbers['thickness'],
                $record['requiresCorrectToolForDrops'],
                $numbers['translucency'],
                strtolower($record['mapColor']),
                $record['tintMethod'],
                $record['lightEmission'],
                $record['lightDampening'],
                $record['burnOdds'],
                $record['flameOdds'],
                $record['canContainLiquidSource'],
                $record['liquidReactionOnTouch'],
                $collision,
                self::shapeValue($record['outlineShape']),
                self::shapeValue($record['visualShape']),
                self::shapeValue($record['uiShape']),
                self::shapeValue($record['liquidClipShape']),
            );
            $properties[] = $value;
            $byStateKey[$state->canonicalKey()] = $value;
        }
        return new self($properties, $byStateKey);
    }

    /** @return list<BlockStateProperties> */
    public function properties(): array { return $this->properties; }

    public function propertiesForState(CanonicalBlockState $state): BlockStateProperties
    {
        return $this->byStateKey[$state->canonicalKey()]
            ?? throw new InvalidArgumentException('Block state has no admitted physical properties.');
    }

    /** @param array<array-key, mixed> $data */
    private static function state(array $data): CanonicalBlockState
    {
        if (!self::hasExactKeys($data, ['identifier', 'properties'])
            || !is_string($data['identifier']) || !is_array($data['properties'])) {
            throw new RuntimeException('Block properties contain an invalid canonical state.');
        }
        try { return CanonicalBlockState::from($data['identifier'], $data['properties']); }
        catch (InvalidArgumentException $error) { throw new RuntimeException('Block properties contain an invalid canonical state.', previous: $error); }
    }

    private static function number(mixed $value): float
    {
        if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value)
            || (float) $value < -1_000_000_000.0 || (float) $value > 1_000_000_000.0) {
            throw new RuntimeException('Block properties contain a numeric value outside the admitted range.');
        }
        return (float) $value;
    }

    /** @return array{float, float, float, float, float, float} */
    private static function shapeValue(mixed $value): array
    {
        if (!is_array($value)) { throw new RuntimeException('Block properties contain an invalid shape.'); }
        return self::shape($value);
    }

    /**
     * @param array<array-key, mixed> $values
     * @return array{float, float, float, float, float, float}
     */
    private static function shape(array $values): array
    {
        if (!array_is_list($values) || count($values) !== 6) { throw new RuntimeException('Block shape must contain exactly six coordinates.'); }
        $result = [];
        foreach ($values as $value) { $result[] = self::number($value); }
        return [$result[0], $result[1], $result[2], $result[3], $result[4], $result[5]];
    }

    /**
     * @param array<array-key, mixed> $data
     * @param list<string> $expected
     */
    private static function hasExactKeys(array $data, array $expected): bool
    {
        if (count($data) !== count($expected)) { return false; }
        foreach ($expected as $key) { if (!array_key_exists($key, $data)) { return false; } }
        return true;
    }
}
