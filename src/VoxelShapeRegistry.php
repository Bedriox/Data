<?php

declare(strict_types=1);

namespace Bedriox\Data;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

/** Immutable deduplicated collision shapes and exact canonical block-state assignments. */
final readonly class VoxelShapeRegistry
{
    public const int MAX_JSON_BYTES = 16_777_216;
    public const int MAX_SHAPES = 65_536;
    public const int MAX_ASSIGNMENTS = 131_072;
    public const int MAX_BOXES_PER_SHAPE = 64;

    /** @var array<int, VoxelShape> */
    private array $shapes;

    /** @var array<string, VoxelShape> */
    private array $shapeByBlockState;

    /**
     * @param array<int, VoxelShape> $shapes
     * @param array<string, VoxelShape> $shapeByBlockState
     */
    private function __construct(array $shapes, array $shapeByBlockState)
    {
        $this->shapes = $shapes;
        $this->shapeByBlockState = $shapeByBlockState;
    }

    public static function fromJson(string $json, NetworkBlockStateRegistry $blocks): self
    {
        if ($json === '' || strlen($json) > self::MAX_JSON_BYTES || preg_match('//u', $json) !== 1) {
            throw new RuntimeException('Voxel-shape JSON is empty, oversized, or not UTF-8.');
        }
        try { $document = json_decode($json, true, 32, JSON_THROW_ON_ERROR); }
        catch (JsonException $error) { throw new RuntimeException('Voxel shapes are not valid bounded JSON.', previous: $error); }
        if (!is_array($document) || array_is_list($document)
            || !self::hasExactKeys($document, ['assignments', 'schema', 'shapes']) || ($document['schema'] ?? null) !== 1
            || !is_array($document['shapes']) || !array_is_list($document['shapes']) || count($document['shapes']) > self::MAX_SHAPES
            || !is_array($document['assignments']) || !array_is_list($document['assignments'])
            || count($document['assignments']) > self::MAX_ASSIGNMENTS) {
            throw new RuntimeException('Voxel shapes have an invalid top-level shape or count.');
        }
        $shapes = [];
        foreach ($document['shapes'] as $shapeData) {
            if (!is_array($shapeData) || !self::hasExactKeys($shapeData, ['boxes', 'id'])
                || !is_int($shapeData['id']) || $shapeData['id'] < 0 || $shapeData['id'] > 1_000_000
                || !is_array($shapeData['boxes']) || !array_is_list($shapeData['boxes'])
                || count($shapeData['boxes']) > self::MAX_BOXES_PER_SHAPE || isset($shapes[$shapeData['id']])) {
                throw new RuntimeException('Voxel shapes contain an invalid or duplicate shape.');
            }
            $boxes = [];
            foreach ($shapeData['boxes'] as $boxData) {
                if (!is_array($boxData) || !self::hasExactKeys($boxData, ['max', 'min'])
                    || !is_array($boxData['min']) || !array_is_list($boxData['min']) || count($boxData['min']) !== 3
                    || !is_array($boxData['max']) || !array_is_list($boxData['max']) || count($boxData['max']) !== 3) {
                    throw new RuntimeException('Voxel shape contains an invalid box.');
                }
                $minimum = self::coordinates($boxData['min']);
                $maximum = self::coordinates($boxData['max']);
                if ($minimum[0] >= $maximum[0] || $minimum[1] >= $maximum[1] || $minimum[2] >= $maximum[2]) {
                    throw new RuntimeException('Voxel box minimum must be smaller than its maximum.');
                }
                $boxes[] = new VoxelBox($minimum[0], $minimum[1], $minimum[2], $maximum[0], $maximum[1], $maximum[2]);
            }
            $shapes[$shapeData['id']] = new VoxelShape($shapeData['id'], $boxes);
        }

        $assignments = [];
        foreach ($document['assignments'] as $assignment) {
            if (!is_array($assignment) || !self::hasExactKeys($assignment, ['blockState', 'shapeId'])
                || !is_int($assignment['shapeId']) || !isset($shapes[$assignment['shapeId']])
                || !is_array($assignment['blockState']) || !self::hasExactKeys($assignment['blockState'], ['identifier', 'properties'])
                || !is_string($assignment['blockState']['identifier']) || !is_array($assignment['blockState']['properties'])) {
                throw new RuntimeException('Voxel shapes contain an invalid assignment.');
            }
            try {
                $state = CanonicalBlockState::from($assignment['blockState']['identifier'], $assignment['blockState']['properties']);
                $blocks->networkRuntimeId($state);
            } catch (InvalidArgumentException $error) {
                throw new RuntimeException('Voxel shape assignment references an unknown block state.', previous: $error);
            }
            if (isset($assignments[$state->canonicalKey()])) {
                throw new RuntimeException('Voxel shapes contain a duplicate block-state assignment.');
            }
            $assignments[$state->canonicalKey()] = $shapes[$assignment['shapeId']];
        }
        return new self($shapes, $assignments);
    }

    /** @return array<int, VoxelShape> */
    public function shapes(): array { return $this->shapes; }

    public function shape(int $id): VoxelShape
    {
        return $this->shapes[$id] ?? throw new InvalidArgumentException('Voxel shape ID is not admitted.');
    }

    public function shapeForBlockState(CanonicalBlockState $state): VoxelShape
    {
        return $this->shapeByBlockState[$state->canonicalKey()]
            ?? throw new InvalidArgumentException('Block state has no admitted voxel-shape assignment.');
    }

    public function assignmentCount(): int
    {
        return count($this->shapeByBlockState);
    }

    /**
     * @param array<array-key, mixed> $values
     * @return array{float, float, float}
     */
    private static function coordinates(array $values): array
    {
        $coordinates = [];
        foreach ($values as $value) {
            if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value)
                || (float) $value < -16.0 || (float) $value > 16.0) {
                throw new RuntimeException('Voxel coordinate is outside the bounded block-local range.');
            }
            $coordinates[] = (float) $value;
        }
        return [$coordinates[0], $coordinates[1], $coordinates[2]];
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
