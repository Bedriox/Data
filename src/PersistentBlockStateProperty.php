<?php

declare(strict_types=1);

namespace Bedriox\Data;

use InvalidArgumentException;

final readonly class PersistentBlockStateProperty
{
    private function __construct(
        private string $name,
        private PersistentBlockStatePropertyType $type,
        private int|string $value,
    ) {
        if (preg_match('/^[a-z0-9_.:-]+$/D', $name) !== 1 || strlen($name) > 256) {
            throw new InvalidArgumentException('Persistent block-state property name is invalid.');
        }
        if ($type === PersistentBlockStatePropertyType::Byte && (!is_int($value) || $value < -128 || $value > 127)) {
            throw new InvalidArgumentException('Persistent byte property is outside its signed range.');
        }
        if ($type === PersistentBlockStatePropertyType::Int
            && (!is_int($value) || $value < -2_147_483_648 || $value > 2_147_483_647)) {
            throw new InvalidArgumentException('Persistent int property is outside its signed range.');
        }
        if ($type === PersistentBlockStatePropertyType::String
            && (!is_string($value) || strlen($value) > 1_024 || preg_match('//u', $value) !== 1)) {
            throw new InvalidArgumentException('Persistent string property is invalid or oversized.');
        }
    }

    public static function byte(string $name, int $value): self
    {
        return new self($name, PersistentBlockStatePropertyType::Byte, $value);
    }

    public static function int(string $name, int $value): self
    {
        return new self($name, PersistentBlockStatePropertyType::Int, $value);
    }

    public static function string(string $name, string $value): self
    {
        return new self($name, PersistentBlockStatePropertyType::String, $value);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function type(): PersistentBlockStatePropertyType
    {
        return $this->type;
    }

    public function value(): int|string
    {
        return $this->value;
    }
}
