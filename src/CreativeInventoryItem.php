<?php

declare(strict_types=1);

namespace Bedriox\Data;

use InvalidArgumentException;
use RuntimeException;

/** One immutable item-stack presentation in the creative inventory dataset. */
final readonly class CreativeInventoryItem
{
    private function __construct(
        private string $identifier,
        private int $count,
        private int $damage,
        private ?CanonicalBlockState $blockState,
        private ?string $nbt,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     * @internal Constructed by CreativeInventoryRegistry from normalized admitted data.
     */
    public static function fromNormalized(
        array $data,
        ItemNetworkRegistry $items,
        NetworkBlockStateRegistry $blocks,
    ): self {
        if (!self::hasExactKeys($data, ['blockState', 'count', 'damage', 'identifier', 'nbt'])
            || !is_string($data['identifier']) || !is_int($data['count']) || !is_int($data['damage'])
            || $data['count'] < 1 || $data['count'] > 255
            || $data['damage'] < 0 || $data['damage'] > 32_767
            || ($data['nbt'] !== null && !is_string($data['nbt']))
            || ($data['blockState'] !== null && !is_array($data['blockState']))) {
            throw new RuntimeException('Creative inventory contains an invalid item shape.');
        }
        try {
            $items->definitionForIdentifier($data['identifier']);
        } catch (InvalidArgumentException $error) {
            throw new RuntimeException('Creative inventory item references an unknown item identifier.', previous: $error);
        }

        $blockState = null;
        if (is_array($data['blockState'])) {
            if (!self::hasExactKeys($data['blockState'], ['identifier', 'properties'])
                || !is_string($data['blockState']['identifier']) || !is_array($data['blockState']['properties'])) {
                throw new RuntimeException('Creative inventory item contains an invalid block state.');
            }
            try {
                $blockState = CanonicalBlockState::from($data['blockState']['identifier'], $data['blockState']['properties']);
                $blocks->networkRuntimeId($blockState);
            } catch (InvalidArgumentException $error) {
                throw new RuntimeException('Creative inventory item references an unknown block state.', previous: $error);
            }
        }

        $nbt = null;
        if (is_string($data['nbt'])) {
            $nbt = base64_decode($data['nbt'], true);
            if (!is_string($nbt)) {
                throw new RuntimeException('Creative inventory item NBT is not valid base64.');
            }
            LittleEndianNbtValidator::rootCompound($nbt, 2_048);
        }

        return new self($data['identifier'], $data['count'], $data['damage'], $blockState, $nbt);
    }

    public function identifier(): string
    {
        return $this->identifier;
    }

    public function count(): int
    {
        return $this->count;
    }

    public function damage(): int
    {
        return $this->damage;
    }

    public function blockState(): ?CanonicalBlockState
    {
        return $this->blockState;
    }

    /** Bounded little-endian named root compound. */
    public function nbt(): ?string
    {
        return $this->nbt;
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
