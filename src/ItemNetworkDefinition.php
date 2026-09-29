<?php

declare(strict_types=1);

namespace Bedriox\Data;

use InvalidArgumentException;

/** Immutable definition of one admitted item in the active Bedrock network registry. */
final readonly class ItemNetworkDefinition
{
    private function __construct(
        private string $identifier,
        private int $networkRuntimeId,
        private bool $componentBased,
        private int $version,
        private ?string $componentNbt,
    ) {
        if (preg_match('/^[a-z0-9_.-]+:[a-z0-9_.-]+$/D', $identifier) !== 1 || strlen($identifier) > 256) {
            throw new InvalidArgumentException('Item-network identifier is invalid.');
        }
        if ($networkRuntimeId < -32_768 || $networkRuntimeId > 32_767) {
            throw new InvalidArgumentException('Item network runtime ID is outside its signed 16-bit range.');
        }
        if ($version < 0 || $version > 2) {
            throw new InvalidArgumentException('Item-network version is outside its admitted range.');
        }
        if ($componentNbt !== null) {
            LittleEndianNbtValidator::rootCompound($componentNbt, 262_144);
        }
    }

    /** @internal Constructed by ItemNetworkRegistry from hash-verified admitted item data. */
    public static function fromAdmittedItem(
        string $identifier,
        int $networkRuntimeId,
        bool $componentBased,
        int $version,
        ?string $componentNbt,
    ): self {
        return new self($identifier, $networkRuntimeId, $componentBased, $version, $componentNbt);
    }

    public function identifier(): string
    {
        return $this->identifier;
    }

    public function networkRuntimeId(): int
    {
        return $this->networkRuntimeId;
    }

    public function componentBased(): bool
    {
        return $this->componentBased;
    }

    public function version(): int
    {
        return $this->version;
    }

    /** Little-endian named root compound, or null when the item has no admitted component payload. */
    public function componentNbt(): ?string
    {
        return $this->componentNbt;
    }
}
