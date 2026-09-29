<?php

declare(strict_types=1);

namespace Bedriox\Data;

use RuntimeException;

/** Bounded structural validator for component NBT stored by BedrockData's LittleEndianNbtSerializer. */
final class LittleEndianNbtValidator
{
    private int $offset = 0;
    private int $entries = 0;

    private function __construct(private readonly string $bytes) {}

    public static function rootCompound(string $bytes, int $maximumBytes): void
    {
        if ($bytes === '' || strlen($bytes) > $maximumBytes) { throw new RuntimeException('Little-endian NBT is empty or oversized.'); }
        $v = new self($bytes);
        if ($v->byte() !== 10) { throw new RuntimeException('Little-endian NBT root must be a compound.'); }
        $v->string();
        $v->compound(0);
        if ($v->offset !== strlen($bytes)) { throw new RuntimeException('Little-endian NBT contains trailing bytes.'); }
    }

    public static function persistentBlockStateRoot(string $bytes, int $maximumBytes): void
    {
        if ($bytes === '' || strlen($bytes) > $maximumBytes) {
            throw new RuntimeException('Persistent block-state NBT is empty or oversized.');
        }
        $root = LittleEndianBlockStateNbtRootReader::decode($bytes);
        if ($root->nextOffset() !== strlen($bytes)) {
            throw new RuntimeException('Persistent block-state NBT contains trailing bytes.');
        }
    }

    private function compound(int $depth): void
    {
        if ($depth > 16) { throw new RuntimeException('Little-endian NBT exceeds its depth limit.'); }
        $names = [];
        while (($type = $this->byte()) !== 0) {
            $name = $this->string();
            if (isset($names[$name])) { throw new RuntimeException('Little-endian NBT contains a duplicate compound key.'); }
            $names[$name] = true;
            $this->payload($type, $depth + 1);
        }
    }

    private function payload(int $type, int $depth): void
    {
        if (++$this->entries > 1_000_000) { throw new RuntimeException('Little-endian NBT exceeds its entry limit.'); }
        match ($type) {
            1 => $this->read(1), 2 => $this->read(2), 3, 5 => $this->read(4), 4, 6 => $this->read(8),
            7 => $this->read($this->length()), 8 => $this->string(), 9 => $this->list($depth),
            10 => $this->compound($depth), 11 => $this->read($this->checkedProduct($this->length(), 4)),
            12 => $this->read($this->checkedProduct($this->length(), 8)),
            default => throw new RuntimeException('Little-endian NBT contains an unknown tag type.'),
        };
    }

    private function list(int $depth): void
    {
        $type = $this->byte(); $count = $this->length();
        if ($type > 12) { throw new RuntimeException('Little-endian NBT list has an unknown element type.'); }
        if ($type === 0 && $count !== 0) { throw new RuntimeException('Little-endian NBT has a non-empty end-tag list.'); }
        for ($i = 0; $i < $count; ++$i) { $this->payload($type, $depth + 1); }
    }

    private function length(): int
    {
        $u = unpack('Vvalue', $this->read(4));
        if ($u === false || !is_int($u['value'])) { throw new RuntimeException('Little-endian NBT length cannot be decoded.'); }
        $value = $u['value'] > 0x7fffffff ? $u['value'] - 0x100000000 : $u['value'];
        if ($value < 0 || $value > 65_536) { throw new RuntimeException('Little-endian NBT collection is oversized.'); }
        return $value;
    }

    private function string(int $maximumBytes = 4_096): string
    {
        $u = unpack('vvalue', $this->read(2));
        if ($u === false || !is_int($u['value']) || $u['value'] > $maximumBytes) { throw new RuntimeException('Little-endian NBT string is oversized.'); }
        $value = $this->read($u['value']);
        if (preg_match('//u', $value) !== 1) { throw new RuntimeException('Little-endian NBT string is invalid UTF-8.'); }
        return $value;
    }

    private function checkedProduct(int $count, int $width): int
    {
        if ($count > intdiv(262_144, $width)) { throw new RuntimeException('Little-endian NBT array is oversized.'); }
        return $count * $width;
    }

    private function byte(): int { return ord($this->read(1)); }
    private function read(int $length): string
    {
        if ($this->offset + $length > strlen($this->bytes)) { throw new RuntimeException('Little-endian NBT is truncated.'); }
        $value = substr($this->bytes, $this->offset, $length); $this->offset += $length; return $value;
    }
}
