<?php

declare(strict_types=1);

namespace Bedriox\Data;

use InvalidArgumentException;
use RuntimeException;

/** @internal Single bounded parser authority shared by persistent-state APIs. */
final class LittleEndianBlockStateNbtRootReader
{
    private const int MAX_STRING_BYTES = 4_096;
    private const int MAX_PROPERTIES = 128;

    private int $offset;

    private function __construct(private readonly string $bytes, private readonly int $rootStart)
    {
        $this->offset = $rootStart;
    }

    public static function decode(string $bytes, int $offset = 0): PersistentBlockStateNbtRoot
    {
        if ($offset < 0 || $offset >= strlen($bytes)) {
            throw new InvalidArgumentException('Persistent block-state offset is outside the input.');
        }
        $reader = new self($bytes, $offset);
        if ($reader->byte() !== 10 || $reader->string(self::MAX_STRING_BYTES) !== '') {
            throw new RuntimeException('Persistent block-state NBT must begin with an unnamed root compound.');
        }
        $identifier = null;
        $storageVersion = null;
        $properties = null;
        $rootNames = [];
        while (($type = $reader->byte()) !== 0) {
            $name = $reader->string(256);
            if (isset($rootNames[$name])) {
                throw new RuntimeException('Persistent block-state root contains a duplicate field.');
            }
            $rootNames[$name] = true;
            if ($name === 'name' && $type === 8) {
                $identifier = $reader->string(256);
            } elseif ($name === 'version' && $type === 3) {
                $storageVersion = $reader->int32();
            } elseif ($name === 'states' && $type === 10) {
                $properties = $reader->properties();
            } else {
                throw new RuntimeException('Persistent block-state root contains an unknown field or wrong tag type.');
            }
        }
        if (!is_string($identifier) || preg_match('/^[a-z0-9_.-]+:[a-z0-9_.-]+$/D', $identifier) !== 1
            || strlen($identifier) > 256 || !is_int($storageVersion) || !is_array($properties)
            || count($rootNames) !== 3) {
            throw new RuntimeException('Persistent block-state root is missing or has an invalid vanilla field.');
        }
        return new PersistentBlockStateNbtRoot(
            $identifier,
            $storageVersion,
            $properties,
            substr($bytes, $offset, $reader->offset - $offset),
            $reader->offset,
        );
    }

    /** @return list<PersistentBlockStateProperty> */
    private function properties(): array
    {
        $properties = [];
        $names = [];
        while (($type = $this->byte()) !== 0) {
            if (count($properties) >= self::MAX_PROPERTIES) {
                throw new RuntimeException('Persistent block state has too many properties.');
            }
            $name = $this->string(256);
            if (isset($names[$name])) {
                throw new RuntimeException('Persistent block state contains a duplicate property.');
            }
            $names[$name] = true;
            try {
                $properties[] = match ($type) {
                    1 => PersistentBlockStateProperty::byte($name, $this->signedByte()),
                    3 => PersistentBlockStateProperty::int($name, $this->int32()),
                    8 => PersistentBlockStateProperty::string($name, $this->string(1_024)),
                    default => throw new RuntimeException('Persistent block state contains an unsupported property tag type.'),
                };
            } catch (InvalidArgumentException $error) {
                throw new RuntimeException('Persistent block state contains an invalid property.', previous: $error);
            }
        }
        return $properties;
    }

    private function string(int $maximumBytes): string
    {
        $decoded = unpack('vvalue', $this->read(2));
        $length = $decoded['value'] ?? null;
        if (!is_int($length) || $length > $maximumBytes) {
            throw new RuntimeException('Persistent block-state NBT string is oversized.');
        }
        $value = $this->read($length);
        if (preg_match('//u', $value) !== 1) {
            throw new RuntimeException('Persistent block-state NBT string is invalid UTF-8.');
        }
        return $value;
    }

    private function signedByte(): int
    {
        $value = $this->byte();
        return $value >= 128 ? $value - 256 : $value;
    }

    private function int32(): int
    {
        $decoded = unpack('Vvalue', $this->read(4));
        $value = $decoded['value'] ?? null;
        if (!is_int($value)) {
            throw new RuntimeException('Persistent block-state NBT int cannot be decoded.');
        }
        return $value > 0x7fff_ffff ? $value - 0x1_0000_0000 : $value;
    }

    /** @phpstan-impure */
    private function byte(): int
    {
        return ord($this->read(1));
    }

    private function read(int $length): string
    {
        if ($length < 0 || $this->offset + $length > strlen($this->bytes)
            || $this->offset + $length - $this->rootStart > LittleEndianBlockStateNbtCodec::MAX_ROOT_BYTES) {
            throw new RuntimeException('Persistent block-state NBT is truncated or exceeds its byte limit.');
        }
        $value = substr($this->bytes, $this->offset, $length);
        $this->offset += $length;
        return $value;
    }
}
