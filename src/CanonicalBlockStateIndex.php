<?php

declare(strict_types=1);

namespace Bedriox\Data;

use RuntimeException;

/** Bounded single-pass reader for the concatenated network-NBT block-state palette. */
final class CanonicalBlockStateIndex
{
    private const int MAX_ROOTS = 100_000;
    private const int MAX_DEPTH = 16;
    private const int MAX_COLLECTION_ENTRIES = 65_536;
    private const int MAX_TOTAL_ENTRIES = 1_000_000;
    private const int MAX_STRING_BYTES = 1_024;

    private int $offset = 0;
    private int $roots = 0;
    private int $entries = 0;

    /** @var array<string, int> */
    private array $matches = [];

    private function __construct(private readonly string $bytes)
    {
        if ($bytes === '' || strlen($bytes) > AdmittedRegistryDataSet::MAX_ARTIFACT_BYTES) {
            throw new RuntimeException('Canonical block-state NBT is empty or oversized.');
        }
    }

    /** @return array{air: int, bedrock: int, dirt: int, grass_block: int} */
    public static function fixedFlatRuntimeIds(string $bytes): array
    {
        $reader = new self($bytes);
        while ($reader->offset < strlen($bytes)) {
            if (++$reader->roots > self::MAX_ROOTS || $reader->readByte() !== 10) {
                throw new RuntimeException('Canonical block-state NBT contains too many roots or a non-compound root.');
            }
            $reader->readString(); // root name
            $root = $reader->readCompound(0);
            $reader->inspectRoot($root, $reader->roots - 1);
        }
        foreach (['air', 'bedrock', 'dirt', 'grass_block'] as $required) {
            if (!isset($reader->matches[$required])) {
                throw new RuntimeException("Canonical block-state NBT is missing the required {$required} state.");
            }
        }
        /** @var array{air: int, bedrock: int, dirt: int, grass_block: int} $matches */
        $matches = $reader->matches;
        return $matches;
    }

    public static function validateRootCompound(string $bytes, int $maximumBytes): void
    {
        if ($maximumBytes < 3 || strlen($bytes) > $maximumBytes) {
            throw new RuntimeException('Network-NBT root compound is empty or oversized.');
        }
        $reader = new self($bytes);
        if ($reader->readByte() !== 10) {
            throw new RuntimeException('Network-NBT value is not a root compound.');
        }
        $reader->readString();
        $reader->readCompound(0);
        if ($reader->offset !== strlen($bytes)) {
            throw new RuntimeException('Network-NBT root compound contains trailing bytes.');
        }
    }

    /** @param array<string, mixed> $root */
    private function inspectRoot(array $root, int $runtimeId): void
    {
        $name = $root['name'] ?? null;
        $states = $root['states'] ?? null;
        if (!is_string($name) || !is_array($states)) {
            throw new RuntimeException('Canonical block-state root is missing name or states.');
        }
        $key = match ($name) {
            'minecraft:air' => $states === [] ? 'air' : null,
            'minecraft:bedrock' => ($states['infiniburn_bit'] ?? null) === 0 ? 'bedrock' : null,
            'minecraft:dirt' => $states === [] ? 'dirt' : null,
            'minecraft:grass_block' => $states === [] ? 'grass_block' : null,
            default => null,
        };
        if ($key !== null) {
            if (isset($this->matches[$key])) {
                throw new RuntimeException("Canonical block-state NBT contains duplicate required {$key} state.");
            }
            $this->matches[$key] = $runtimeId;
        }
    }

    /** @return array<string, mixed> */
    private function readCompound(int $depth): array
    {
        if ($depth >= self::MAX_DEPTH) {
            throw new RuntimeException('Canonical block-state NBT exceeds the nesting-depth limit.');
        }
        $result = [];
        while (true) {
            $type = $this->readByte();
            if ($type === 0) {
                return $result;
            }
            if (++$this->entries > self::MAX_TOTAL_ENTRIES) {
                throw new RuntimeException('Canonical block-state NBT exceeds the entry limit.');
            }
            $name = $this->readString();
            if (array_key_exists($name, $result)) {
                throw new RuntimeException('Canonical block-state NBT contains a duplicate compound key.');
            }
            $result[$name] = $this->readPayload($type, $depth + 1);
        }
    }

    private function readPayload(int $type, int $depth): mixed
    {
        return match ($type) {
            1 => ($value = $this->readByte()) >= 128 ? $value - 256 : $value,
            2 => $this->readUnsignedShort(),
            3 => $this->zigZag($this->readUnsignedVarInt(5)),
            4 => $this->skipVarLong(),
            5 => $this->readFloat(),
            6 => $this->readDouble(),
            7 => $this->read($this->readLength()),
            8 => $this->readString(),
            9 => $this->readList($depth),
            10 => $this->readCompound($depth),
            11 => $this->readIntArray(),
            12 => $this->readLongArray(),
            default => throw new RuntimeException('Canonical block-state NBT contains an unknown tag type.'),
        };
    }

    /** @return list<mixed> */
    private function readList(int $depth): array
    {
        $type = $this->readByte();
        $count = $this->readLength();
        if ($type === 0 && $count !== 0) {
            throw new RuntimeException('Canonical block-state NBT has a non-empty end-tag list.');
        }
        $values = [];
        for ($i = 0; $i < $count; ++$i) {
            $values[] = $this->readPayload($type, $depth + 1);
        }
        return $values;
    }

    /** @return list<int> */
    private function readIntArray(): array
    {
        $count = $this->readLength();
        $values = [];
        for ($i = 0; $i < $count; ++$i) {
            $values[] = $this->zigZag($this->readUnsignedVarInt(5));
        }
        return $values;
    }

    /** @return list<null> */
    private function readLongArray(): array
    {
        $count = $this->readLength();
        $values = [];
        for ($i = 0; $i < $count; ++$i) {
            $this->skipVarLong();
            $values[] = null;
        }
        return $values;
    }

    private function readLength(): int
    {
        $length = $this->zigZag($this->readUnsignedVarInt(5));
        if ($length < 0 || $length > self::MAX_COLLECTION_ENTRIES) {
            throw new RuntimeException('Canonical block-state NBT collection length is invalid or oversized.');
        }
        return $length;
    }

    private function readString(): string
    {
        $length = $this->readUnsignedVarInt(5);
        if ($length > self::MAX_STRING_BYTES) {
            throw new RuntimeException('Canonical block-state NBT string is oversized.');
        }
        $value = $this->read($length);
        if (preg_match('//u', $value) !== 1) {
            throw new RuntimeException('Canonical block-state NBT string is not valid UTF-8.');
        }
        return $value;
    }

    private function skipVarLong(): null
    {
        for ($i = 0; $i < 10; ++$i) {
            $byte = $this->readByte();
            if ($i === 9 && $byte > 1) {
                throw new RuntimeException('Canonical block-state NBT varlong overflows 64 bits.');
            }
            if (($byte & 0x80) === 0) {
                if ($i > 0 && $byte === 0) {
                    throw new RuntimeException('Canonical block-state NBT contains a non-canonical varlong.');
                }
                return null;
            }
        }
        throw new RuntimeException('Canonical block-state NBT contains an overlong varlong.');
    }

    private function readUnsignedVarInt(int $maxBytes): int
    {
        $value = 0;
        for ($i = 0; $i < $maxBytes; ++$i) {
            $byte = $this->readByte();
            if ($i === 4 && $byte > 15) {
                throw new RuntimeException('Canonical block-state NBT varint overflows 32 bits.');
            }
            $value |= ($byte & 0x7f) << (7 * $i);
            if (($byte & 0x80) === 0) {
                if ($i > 0 && $byte === 0) {
                    throw new RuntimeException('Canonical block-state NBT contains a non-canonical varint.');
                }
                return $value;
            }
        }
        throw new RuntimeException('Canonical block-state NBT contains an overlong varint.');
    }

    private function zigZag(int $value): int
    {
        return ($value >> 1) ^ -($value & 1);
    }

    private function readByte(): int
    {
        return ord($this->read(1));
    }

    private function readUnsignedShort(): int
    {
        $value = unpack('vvalue', $this->read(2));
        if ($value === false || !is_int($value['value'] ?? null)) {
            throw new RuntimeException('Canonical block-state NBT short cannot be decoded.');
        }
        return $value['value'] >= 0x8000 ? $value['value'] - 0x10000 : $value['value'];
    }

    private function readFloat(): float
    {
        $value = unpack('gvalue', $this->read(4));
        if ($value === false || !is_float($value['value'] ?? null)) {
            throw new RuntimeException('Canonical block-state NBT float cannot be decoded.');
        }
        return $value['value'];
    }

    private function readDouble(): float
    {
        $value = unpack('evalue', $this->read(8));
        if ($value === false || !is_float($value['value'] ?? null)) {
            throw new RuntimeException('Canonical block-state NBT double cannot be decoded.');
        }
        return $value['value'];
    }

    private function read(int $length): string
    {
        if ($length < 0 || $this->offset + $length > strlen($this->bytes)) {
            throw new RuntimeException('Canonical block-state NBT is truncated.');
        }
        $value = substr($this->bytes, $this->offset, $length);
        $this->offset += $length;
        return $value;
    }
}
