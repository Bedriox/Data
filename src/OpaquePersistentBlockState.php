<?php

declare(strict_types=1);

namespace Bedriox\Data;

use InvalidArgumentException;

/** Valid vanilla state unknown to the admitted palette; only its bounded original bytes are exposed. */
final readonly class OpaquePersistentBlockState implements PersistentBlockState
{
    private function __construct(private string $encodedRoot)
    {
        try {
            LittleEndianNbtValidator::persistentBlockStateRoot($encodedRoot, LittleEndianBlockStateNbtCodec::MAX_ROOT_BYTES);
        } catch (\RuntimeException $error) {
            throw new InvalidArgumentException('Opaque persistent block-state root is malformed.', previous: $error);
        }
    }

    /** @internal Constructed only after strict block-state NBT validation. */
    public static function fromValidatedEncodedRoot(string $encodedRoot): self
    {
        return new self($encodedRoot);
    }

    public function encodedRoot(): string
    {
        return $this->encodedRoot;
    }
}
