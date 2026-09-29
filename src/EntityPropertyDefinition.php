<?php

declare(strict_types=1);

namespace Bedriox\Data;

use InvalidArgumentException;

/** Immutable schema for one admitted client-visible entity property. */
final readonly class EntityPropertyDefinition
{
    private string $identifier;

    private EntityPropertyType $type;

    private int|float|null $minimum;

    private int|float|null $maximum;

    /** @var list<string> */
    private array $enumValues;

    /**
     * @param list<string> $enumValues
     */
    private function __construct(
        string $identifier,
        EntityPropertyType $type,
        int|float|null $minimum,
        int|float|null $maximum,
        array $enumValues,
    ) {
        if (preg_match('/^[a-z0-9_.-]+:[a-z0-9_.-]+$/D', $identifier) !== 1 || strlen($identifier) > 256) {
            throw new InvalidArgumentException('Entity property identifier is invalid.');
        }
        if ($type === EntityPropertyType::INTEGER) {
            if (!is_int($minimum) || !is_int($maximum) || $minimum > $maximum || $enumValues !== []) {
                throw new InvalidArgumentException('Integer entity property bounds are invalid.');
            }
        } elseif ($type === EntityPropertyType::FLOAT) {
            if ((!is_int($minimum) && !is_float($minimum)) || (!is_int($maximum) && !is_float($maximum))
                || !is_finite((float) $minimum) || !is_finite((float) $maximum)
                || (float) $minimum > (float) $maximum || $enumValues !== []) {
                throw new InvalidArgumentException('Float entity property bounds are invalid.');
            }
            $minimum = (float) $minimum;
            $maximum = (float) $maximum;
        } elseif ($type === EntityPropertyType::BOOLEAN) {
            if ($minimum !== null || $maximum !== null || $enumValues !== []) {
                throw new InvalidArgumentException('Boolean entity property cannot contain bounds or enum values.');
            }
        } elseif ($minimum !== null || $maximum !== null || $enumValues === [] || !array_is_list($enumValues)
            || count($enumValues) > 256 || count(array_unique($enumValues, SORT_STRING)) !== count($enumValues)) {
            throw new InvalidArgumentException('Enum entity property values are invalid.');
        }
        foreach ($enumValues as $value) {
            if ($value === '' || strlen($value) > 256 || preg_match('//u', $value) !== 1
                || preg_match('/[\x00-\x1f\x7f]/', $value) === 1) {
                throw new InvalidArgumentException('Enum entity property value is invalid.');
            }
        }
        $this->identifier = $identifier;
        $this->type = $type;
        $this->minimum = $minimum;
        $this->maximum = $maximum;
        $this->enumValues = $enumValues;
    }

    /**
     * @param list<string> $enumValues
     * @internal Constructed from a hash-verified admitted entity-property artifact.
     */
    public static function fromAdmittedProperty(
        string $identifier,
        EntityPropertyType $type,
        int|float|null $minimum = null,
        int|float|null $maximum = null,
        array $enumValues = [],
    ): self {
        return new self($identifier, $type, $minimum, $maximum, $enumValues);
    }

    public function identifier(): string
    {
        return $this->identifier;
    }

    public function type(): EntityPropertyType
    {
        return $this->type;
    }

    public function minimum(): int|float|null
    {
        return $this->minimum;
    }

    public function maximum(): int|float|null
    {
        return $this->maximum;
    }

    /** @return list<string> */
    public function enumValues(): array
    {
        return $this->enumValues;
    }
}
