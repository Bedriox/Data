<?php

declare(strict_types=1);

namespace Bedriox\Data;

use RuntimeException;

/** @internal Strict bounded decoder for the AvailableActorIdentifiers network-NBT shape. */
final class NetworkEntityIdentifierReader
{
    private const int MAXIMUM_ENTITIES = 1_024;
    private const int MAXIMUM_STRING_BYTES = 4_096;

    private int $offset = 0;

    private function __construct(private readonly string $bytes) {}

    /**
     * @return list<array{identifier: string, network_runtime_id: int, summonable: bool, has_spawn_egg: bool}>
     */
    public static function decode(string $bytes): array
    {
        if ($bytes === '' || strlen($bytes) > AdmittedRegistryDataSet::MAX_ARTIFACT_BYTES) {
            throw new RuntimeException('Entity-identifier network NBT is empty or oversized.');
        }
        $reader = new self($bytes);
        if ($reader->byte() !== 10 || $reader->string() !== '') {
            throw new RuntimeException('Entity-identifier network NBT must have an unnamed compound root.');
        }
        if ($reader->byte() !== 9 || $reader->string() !== 'idlist' || $reader->byte() !== 10) {
            throw new RuntimeException('Entity-identifier network NBT must contain exactly one idlist compound list.');
        }
        $count = $reader->signedVarInt();
        if ($count < 1 || $count > self::MAXIMUM_ENTITIES) {
            throw new RuntimeException('Entity-identifier list is empty or oversized.');
        }
        $records = [];
        for ($index = 0; $index < $count; ++$index) {
            $records[] = $reader->entity();
        }
        if ($reader->byte() !== 0 || !$reader->atEnd()) {
            throw new RuntimeException('Entity-identifier network NBT contains extra root data or trailing bytes.');
        }

        return $records;
    }

    /** @return array{identifier: string, network_runtime_id: int, summonable: bool, has_spawn_egg: bool} */
    private function entity(): array
    {
        $fields = [];
        while (($type = $this->byte()) !== 0) {
            $name = $this->string();
            if (isset($fields[$name])) {
                throw new RuntimeException('Entity identifier contains a duplicate field.');
            }
            $fields[$name] = match ([$name, $type]) {
                ['bid', 8] => $this->string(),
                ['hasspawnegg', 1], ['summonable', 1] => $this->boolean(),
                ['id', 8] => $this->string(),
                ['rid', 3] => $this->signedVarInt(),
                default => throw new RuntimeException('Entity identifier contains an unknown field or tag type.'),
            };
        }
        $names = array_keys($fields);
        sort($names, SORT_STRING);
        if ($names !== ['bid', 'hasspawnegg', 'id', 'rid', 'summonable']
            || !is_string($fields['id']) || !is_int($fields['rid'])
            || !is_bool($fields['summonable']) || !is_bool($fields['hasspawnegg'])) {
            throw new RuntimeException('Entity identifier is missing a required field.');
        }
        if (preg_match('/^minecraft:[a-z0-9_.-]+$/D', $fields['id']) !== 1 || strlen($fields['id']) > 256
            || $fields['rid'] < 0 || $fields['rid'] > 0x7fffffff) {
            throw new RuntimeException('Entity identifier contains an invalid canonical identity or runtime ID.');
        }

        return [
            'identifier' => $fields['id'],
            'network_runtime_id' => $fields['rid'],
            'summonable' => $fields['summonable'],
            'has_spawn_egg' => $fields['hasspawnegg'],
        ];
    }

    private function boolean(): bool
    {
        $value = $this->byte();
        if ($value !== 0 && $value !== 1) {
            throw new RuntimeException('Entity identifier boolean field is not zero or one.');
        }

        return $value === 1;
    }

    private function signedVarInt(): int
    {
        $value = $this->unsignedVarInt();

        return ($value >> 1) ^ (-($value & 1));
    }

    private function unsignedVarInt(): int
    {
        $value = 0;
        for ($index = 0; $index < 5; ++$index) {
            $byte = $this->byte();
            if ($index === 4 && ($byte & 0xf0) !== 0) {
                throw new RuntimeException('Entity-identifier network NBT contains an overflowing varint.');
            }
            $value |= ($byte & 0x7f) << ($index * 7);
            if (($byte & 0x80) === 0) {
                return $value;
            }
        }

        throw new RuntimeException('Entity-identifier network NBT contains an unterminated varint.');
    }

    /** @phpstan-impure */
    private function string(): string
    {
        $length = $this->unsignedVarInt();
        if ($length > self::MAXIMUM_STRING_BYTES) {
            throw new RuntimeException('Entity-identifier network NBT string is oversized.');
        }
        $value = $this->read($length);
        if (preg_match('//u', $value) !== 1) {
            throw new RuntimeException('Entity-identifier network NBT string is not UTF-8.');
        }

        return $value;
    }

    /** @phpstan-impure */
    private function byte(): int
    {
        return ord($this->read(1));
    }

    private function read(int $length): string
    {
        if ($length < 0 || $this->offset + $length > strlen($this->bytes)) {
            throw new RuntimeException('Entity-identifier network NBT is truncated.');
        }
        $value = substr($this->bytes, $this->offset, $length);
        $this->offset += $length;

        return $value;
    }

    private function atEnd(): bool
    {
        return $this->offset === strlen($this->bytes);
    }
}
