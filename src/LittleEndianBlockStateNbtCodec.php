<?php

declare(strict_types=1);

namespace Bedriox\Data;

use InvalidArgumentException;
use RuntimeException;

/** Strict codec for concatenated vanilla little-endian persistent block-state roots. */
final class LittleEndianBlockStateNbtCodec
{
    public const int MAX_ROOT_BYTES = 65_536;

    public function __construct(private readonly PersistentBlockStateRegistry $registry) {}

    public function decode(string $bytes, int $offset = 0): PersistentBlockStateDecodeResult
    {
        $root = LittleEndianBlockStateNbtRootReader::decode($bytes, $offset);
        try {
            $state = $this->registry->classify(
                $root->identifier(),
                $root->storageVersion(),
                $root->properties(),
                $root->encodedRoot(),
            );
        } catch (InvalidArgumentException $error) {
            throw new RuntimeException('Persistent block-state root contains an invalid value.', previous: $error);
        }
        return new PersistentBlockStateDecodeResult($state, $root->nextOffset());
    }

    public function encode(PersistentBlockState $state): string
    {
        if ($state instanceof OpaquePersistentBlockState) {
            return $state->encodedRoot();
        }
        if (!$state instanceof KnownPersistentBlockState) {
            throw new InvalidArgumentException('Unsupported persistent block-state implementation.');
        }
        if ($state->originalEncodedRoot() !== null) {
            return $state->originalEncodedRoot();
        }

        $output = "\x0a\x00\x00";
        $output .= "\x08" . self::encodedString('name') . self::encodedString($state->identifier());
        $output .= "\x03" . self::encodedString('version') . pack('V', $state->storageVersion() & 0xffff_ffff);
        $output .= "\x0a" . self::encodedString('states');
        foreach ($state->properties() as $property) {
            $output .= chr($property->type()->value) . self::encodedString($property->name());
            $output .= match ($property->type()) {
                PersistentBlockStatePropertyType::Byte => chr(((int) $property->value()) & 0xff),
                PersistentBlockStatePropertyType::Int => pack('V', ((int) $property->value()) & 0xffff_ffff),
                PersistentBlockStatePropertyType::String => self::encodedString((string) $property->value()),
            };
        }
        $output .= "\x00\x00";
        if (strlen($output) > self::MAX_ROOT_BYTES) {
            throw new RuntimeException('Encoded persistent block-state root exceeds its byte limit.');
        }
        return $output;
    }

    private static function encodedString(string $value): string
    {
        $length = strlen($value);
        if ($length > 4_096 || preg_match('//u', $value) !== 1) {
            throw new InvalidArgumentException('Persistent NBT string is invalid or oversized.');
        }
        return pack('v', $length) . $value;
    }

}
