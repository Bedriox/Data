<?php

declare(strict_types=1);

namespace Bedriox\Data;

use InvalidArgumentException;
use JsonException;

/** Immutable canonical Bedrock block state, independent of network runtime IDs. */
final readonly class CanonicalBlockState
{
    /** @var array<string, int|string> */
    private array $properties;
    private string $canonicalKey;

    /** @param array<array-key, mixed> $properties */
    private function __construct(private string $identifier, array $properties)
    {
        if (preg_match('/^[a-z0-9_.-]+:[a-z0-9_.-]+$/D', $identifier) !== 1 || strlen($identifier) > 256) {
            throw new InvalidArgumentException('Canonical block-state identifier is invalid.');
        }
        if (count($properties) > 128) {
            throw new InvalidArgumentException('Canonical block state has too many properties.');
        }
        $validatedProperties = [];
        foreach ($properties as $name => $value) {
            if (!is_string($name) || preg_match('/^[a-z0-9_.:-]+$/D', $name) !== 1 || strlen($name) > 256
                || (!is_int($value) && !is_string($value)) || (is_string($value) && strlen($value) > 1_024)
                || (is_string($value) && preg_match('//u', $value) !== 1)) {
                throw new InvalidArgumentException('Canonical block state contains an invalid property.');
            }
            $validatedProperties[$name] = $value;
        }
        ksort($validatedProperties, SORT_STRING);
        $this->properties = $validatedProperties;
        try {
            $this->canonicalKey = json_encode([$identifier, $validatedProperties], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $error) {
            throw new InvalidArgumentException('Canonical block state cannot be encoded.', previous: $error);
        }
    }

    /** @param array<array-key, mixed> $properties */
    public static function from(string $identifier, array $properties = []): self
    {
        return new self($identifier, $properties);
    }

    public function identifier(): string
    {
        return $this->identifier;
    }

    /** @return array<string, int|string> */
    public function properties(): array
    {
        return $this->properties;
    }

    public function canonicalKey(): string
    {
        return $this->canonicalKey;
    }
}
