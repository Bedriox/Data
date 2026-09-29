<?php

declare(strict_types=1);

namespace Bedriox\Data;

use RuntimeException;

/** @internal Bounded reader for admitted gzip-compressed standard big-endian NBT. */
final class BigEndianNbtRegistry
{
    private const int MAX_DEPTH = 16;
    private const int MAX_ENTRIES = 1_000_000;
    private const int MAX_COLLECTION = 100_000;
    private const int MAX_STRING_BYTES = 4_096;

    private int $offset = 0;
    private int $entries = 0;

    private function __construct(private readonly string $bytes) {}

    /** @return list<CanonicalBlockState> */
    public static function blockPalette(string $compressed): array
    {
        return array_map(
            static fn(array $entry): CanonicalBlockState => $entry['state'],
            self::networkBlockPalette($compressed),
        );
    }

    /** @return list<array{state: CanonicalBlockState, network_id: int}> */
    public static function networkBlockPalette(string $compressed): array
    {
        $reader = new self(self::inflate($compressed, 8_000_000));
        if ($reader->byte() !== 10) {
            throw new RuntimeException('Block palette NBT root is not a compound.');
        }
        $reader->string();
        $states = null;
        $rootNames = [];
        while (($type = $reader->byte()) !== 0) {
            $name = $reader->string();
            if (isset($rootNames[$name])) {
                throw new RuntimeException('Block palette NBT contains a duplicate root name.');
            }
            $rootNames[$name] = true;
            if ($name !== 'blocks') {
                $reader->skipPayload($type, 1);
                continue;
            }
            if ($type !== 9 || $reader->byte() !== 10) {
                throw new RuntimeException('Block palette blocks field is not a compound list.');
            }
            $count = $reader->length();
            $states = [];
            for ($index = 0; $index < $count; ++$index) {
                $states[] = $reader->networkBlock(2);
            }
        }
        $reader->end();
        if ($states === null) {
            throw new RuntimeException('Block palette NBT is missing its blocks list.');
        }
        return $states;
    }

    /** @return list<KnownPersistentBlockState> */
    public static function persistentBlockPalette(string $compressed): array
    {
        $reader = new self(self::inflate($compressed, 8_000_000));
        if ($reader->byte() !== 10) {
            throw new RuntimeException('Block palette NBT root is not a compound.');
        }
        $reader->string();
        $states = null;
        $rootNames = [];
        while (($type = $reader->byte()) !== 0) {
            $name = $reader->string();
            if (isset($rootNames[$name])) {
                throw new RuntimeException('Block palette NBT contains a duplicate root name.');
            }
            $rootNames[$name] = true;
            if ($name !== 'blocks') {
                $reader->skipPayload($type, 1);
                continue;
            }
            if ($type !== 9 || $reader->byte() !== 10) {
                throw new RuntimeException('Block palette blocks field is not a compound list.');
            }
            $count = $reader->length();
            $states = [];
            for ($index = 0; $index < $count; ++$index) {
                $states[] = $reader->persistentBlock(2);
            }
        }
        $reader->end();
        if ($states === null) {
            throw new RuntimeException('Block palette NBT is missing its blocks list.');
        }
        return $states;
    }

    /** @return array{air: int, bedrock: int, dirt: int, grass_block: int} */
    public static function fixedFlatRuntimeIds(string $compressed): array
    {
        $states = self::networkBlockPalette($compressed);
        $matches = [];
        foreach ($states as $entry) {
            $state = $entry['state'];
            $runtimeId = $entry['network_id'];
            $properties = $state->properties();
            $key = match ($state->identifier()) {
                'minecraft:air' => $properties === [] ? 'air' : null,
                'minecraft:bedrock' => ($properties['infiniburn_bit'] ?? null) === 0 ? 'bedrock' : null,
                'minecraft:dirt' => $properties === [] ? 'dirt' : null,
                'minecraft:grass_block' => $properties === [] ? 'grass_block' : null,
                default => null,
            };
            if ($key !== null) {
                if (isset($matches[$key])) {
                    throw new RuntimeException("Block palette contains a duplicate required {$key} state.");
                }
                $matches[$key] = $runtimeId;
            }
        }
        foreach (['air', 'bedrock', 'dirt', 'grass_block'] as $required) {
            if (!isset($matches[$required])) {
                throw new RuntimeException("Block palette is missing the required {$required} state.");
            }
        }
        return [
            'air' => $matches['air'],
            'bedrock' => $matches['bedrock'],
            'dirt' => $matches['dirt'],
            'grass_block' => $matches['grass_block'],
        ];
    }

    /** @return array<string, string> item identifier => little-endian named root compound */
    public static function itemComponents(string $compressed): array
    {
        return self::namedCompoundRegistry($compressed, 'Item-component');
    }

    /** @return array<string, string> identifier => little-endian named root compound */
    public static function dataDrivenBlockProperties(string $compressed): array
    {
        return self::namedCompoundRegistry($compressed, 'Data-driven block-property');
    }

    /** @return array<string, list<EntityPropertyDefinition>> entity identifier => property definitions */
    public static function entityProperties(string $compressed): array
    {
        $reader = new self(self::inflate($compressed, 1_000_000));
        if ($reader->byte() !== 10) {
            throw new RuntimeException('Entity-property NBT root is not a compound.');
        }
        $reader->string();
        $result = [];
        while (($type = $reader->byte()) !== 0) {
            $identifier = $reader->string();
            if ($type !== 10 || isset($result[$identifier]) || count($result) >= 1_024) {
                throw new RuntimeException('Entity-property NBT contains a non-compound, duplicate, or excessive root entry.');
            }
            $entry = $reader->compound(1);
            if (!self::hasExactKeys($entry, ['properties', 'type']) || ($entry['type'] ?? null) !== $identifier
                || !is_array($entry['properties']) || !array_is_list($entry['properties'])
                || count($entry['properties']) > 256) {
                throw new RuntimeException('Entity-property NBT contains an invalid entity definition.');
            }
            $properties = [];
            $seen = [];
            foreach ($entry['properties'] as $property) {
                if (!is_array($property) || !is_string($property['name'] ?? null) || !is_int($property['type'] ?? null)
                    || isset($seen[$property['name']])) {
                    throw new RuntimeException('Entity-property NBT contains an invalid or duplicate property.');
                }
                $normalizedProperty = [];
                foreach ($property as $key => $value) {
                    if (!is_string($key)) {
                        throw new RuntimeException('Entity-property NBT property contains a non-string key.');
                    }
                    $normalizedProperty[$key] = $value;
                }
                try {
                    $propertyType = EntityPropertyType::from($property['type']);
                    $definition = match ($propertyType) {
                        EntityPropertyType::INTEGER, EntityPropertyType::FLOAT => self::boundedEntityProperty(
                            $normalizedProperty,
                            $propertyType,
                        ),
                        EntityPropertyType::BOOLEAN => self::booleanEntityProperty($normalizedProperty),
                        EntityPropertyType::ENUM => self::enumEntityProperty($normalizedProperty),
                    };
                } catch (\InvalidArgumentException|\ValueError $error) {
                    throw new RuntimeException('Entity-property NBT contains an invalid property definition.', previous: $error);
                }
                $seen[$definition->identifier()] = true;
                $properties[] = $definition;
            }
            $result[$identifier] = $properties;
        }
        $reader->end();
        ksort($result, SORT_STRING);

        return $result;
    }

    /** @return array<string, string> */
    private static function namedCompoundRegistry(string $compressed, string $label): array
    {
        $reader = new self(self::inflate($compressed, 1_000_000));
        if ($reader->byte() !== 10) {
            throw new RuntimeException("{$label} NBT root is not a compound.");
        }
        $reader->string();
        $components = [];
        $seen = [];
        while (($type = $reader->byte()) !== 0) {
            if (++$reader->entries > self::MAX_COLLECTION) {
                throw new RuntimeException("{$label} NBT has too many root entries.");
            }
            $name = $reader->string();
            if ($type !== 10 || isset($seen[$name])) {
                throw new RuntimeException("{$label} NBT has a non-compound or duplicate root entry.");
            }
            $seen[$name] = true;
            $payload = $reader->convertCompound(1);
            if ($payload !== "\x00") {
                $components[$name] = "\x0a" . pack('v', strlen($name)) . $name . $payload;
            }
        }
        $reader->end();
        return $components;
    }

    /** @param array<string, mixed> $property */
    private static function boundedEntityProperty(
        array $property,
        EntityPropertyType $type,
    ): EntityPropertyDefinition {
        if (!self::hasExactKeys($property, ['max', 'min', 'name', 'type'])) {
            throw new RuntimeException('Bounded entity property has an invalid shape.');
        }
        $identifier = $property['name'];
        $minimum = $property['min'];
        $maximum = $property['max'];
        if (!is_string($identifier)) {
            throw new RuntimeException('Bounded entity property has an invalid identifier.');
        }
        if ((!is_int($minimum) && !is_float($minimum)) || (!is_int($maximum) && !is_float($maximum))) {
            throw new RuntimeException('Bounded entity property has non-numeric bounds.');
        }
        if ($type === EntityPropertyType::INTEGER && (!is_int($minimum) || !is_int($maximum))) {
            throw new RuntimeException('Integer entity property has non-integer bounds.');
        }

        return EntityPropertyDefinition::fromAdmittedProperty(
            $identifier,
            $type,
            $minimum,
            $maximum,
        );
    }

    /** @param array<string, mixed> $property */
    private static function booleanEntityProperty(array $property): EntityPropertyDefinition
    {
        if (!self::hasExactKeys($property, ['name', 'type'])) {
            throw new RuntimeException('Boolean entity property has an invalid shape.');
        }
        $identifier = $property['name'];
        if (!is_string($identifier)) {
            throw new RuntimeException('Boolean entity property has an invalid identifier.');
        }

        return EntityPropertyDefinition::fromAdmittedProperty($identifier, EntityPropertyType::BOOLEAN);
    }

    /** @param array<string, mixed> $property */
    private static function enumEntityProperty(array $property): EntityPropertyDefinition
    {
        if (!self::hasExactKeys($property, ['enum', 'name', 'type']) || !is_array($property['enum'])
            || !array_is_list($property['enum'])) {
            throw new RuntimeException('Enum entity property has an invalid shape.');
        }
        $identifier = $property['name'];
        if (!is_string($identifier)) {
            throw new RuntimeException('Enum entity property has an invalid identifier.');
        }
        $values = [];
        foreach ($property['enum'] as $value) {
            if (!is_string($value)) {
                throw new RuntimeException('Enum entity property contains a non-string value.');
            }
            $values[] = $value;
        }

        return EntityPropertyDefinition::fromAdmittedProperty(
            $identifier,
            EntityPropertyType::ENUM,
            enumValues: $values,
        );
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $expected
     */
    private static function hasExactKeys(array $data, array $expected): bool
    {
        $actual = array_keys($data);
        sort($actual, SORT_STRING);
        sort($expected, SORT_STRING);

        return $actual === $expected;
    }

    private static function inflate(string $compressed, int $maximumBytes): string
    {
        if ($compressed === '' || strlen($compressed) > AdmittedRegistryDataSet::MAX_ARTIFACT_BYTES) {
            throw new RuntimeException('Compressed NBT artifact is empty or oversized.');
        }
        $context = inflate_init(ZLIB_ENCODING_GZIP);
        if ($context === false) {
            throw new RuntimeException('Unable to initialize the gzip decoder.');
        }
        $decoded = '';
        $length = strlen($compressed);
        for ($offset = 0; $offset < $length; $offset += 16_384) {
            $mode = $offset + 16_384 >= $length ? ZLIB_FINISH : ZLIB_SYNC_FLUSH;
            $part = @inflate_add($context, substr($compressed, $offset, 16_384), $mode);
            if (!is_string($part) || strlen($decoded) + strlen($part) > $maximumBytes) {
                throw new RuntimeException('Compressed NBT artifact is malformed or expands beyond its limit.');
            }
            $decoded .= $part;
        }
        if ($decoded === '' || inflate_get_status($context) !== ZLIB_STREAM_END) {
            throw new RuntimeException('Compressed NBT artifact is truncated.');
        }
        return $decoded;
    }

    /** @return array<string, mixed> */
    private function compound(int $depth): array
    {
        $this->depth($depth);
        $values = [];
        while (($type = $this->byte()) !== 0) {
            if (++$this->entries > self::MAX_ENTRIES) {
                throw new RuntimeException('NBT exceeds its entry limit.');
            }
            $name = $this->string();
            if (array_key_exists($name, $values)) {
                throw new RuntimeException('NBT compound contains a duplicate name.');
            }
            $values[$name] = $this->payload($type, $depth + 1);
        }
        return $values;
    }

    private function payload(int $type, int $depth): mixed
    {
        $this->depth($depth);
        return match ($type) {
            1 => ($value = $this->byte()) >= 128 ? $value - 256 : $value,
            2 => $this->signed($this->read(2)),
            3 => $this->signed($this->read(4)),
            4 => $this->read(8),
            5 => $this->float(),
            6 => $this->double(),
            7 => $this->read($this->length()),
            8 => $this->string(),
            9 => $this->list($depth + 1),
            10 => $this->compound($depth + 1),
            11 => $this->intArray(),
            12 => $this->longArray(),
            default => throw new RuntimeException('NBT contains an unknown tag type.'),
        };
    }

    private function persistentBlock(int $depth): KnownPersistentBlockState
    {
        $this->depth($depth);
        $identifier = null;
        $storageVersion = null;
        $properties = null;
        $names = [];
        while (($type = $this->byte()) !== 0) {
            if (++$this->entries > self::MAX_ENTRIES) {
                throw new RuntimeException('NBT exceeds its entry limit.');
            }
            $name = $this->string();
            if (isset($names[$name])) {
                throw new RuntimeException('Block palette entry contains a duplicate field.');
            }
            $names[$name] = true;
            if ($name === 'name' && $type === 8) {
                $identifier = $this->string();
            } elseif ($name === 'version' && $type === 3) {
                $storageVersion = $this->signed($this->read(4));
            } elseif ($name === 'states' && $type === 10) {
                $properties = $this->persistentProperties($depth + 1);
            } else {
                $this->skipPayload($type, $depth + 1);
            }
        }
        if (!is_string($identifier) || !is_int($storageVersion) || !is_array($properties)) {
            throw new RuntimeException('Block palette entry is missing its persistent name, version, or states.');
        }
        try {
            return KnownPersistentBlockState::from($identifier, $storageVersion, $properties);
        } catch (\InvalidArgumentException $error) {
            throw new RuntimeException('Block palette entry contains an invalid persistent state.', previous: $error);
        }
    }

    /** @return array{state: CanonicalBlockState, network_id: int} */
    private function networkBlock(int $depth): array
    {
        $this->depth($depth);
        $identifier = null;
        $networkId = null;
        $properties = null;
        $encodedProperties = null;
        $names = [];
        while (($type = $this->byte()) !== 0) {
            if (++$this->entries > self::MAX_ENTRIES) {
                throw new RuntimeException('NBT exceeds its entry limit.');
            }
            $name = $this->string();
            if (isset($names[$name])) {
                throw new RuntimeException('Block palette entry contains a duplicate field.');
            }
            $names[$name] = true;
            if ($name === 'name' && $type === 8) {
                $identifier = $this->string();
            } elseif ($name === 'network_id' && $type === 3) {
                $networkId = $this->signed($this->read(4));
            } elseif ($name === 'states' && $type === 10) {
                [$properties, $encodedProperties] = $this->networkProperties($depth + 1);
            } else {
                $this->skipPayload($type, $depth + 1);
            }
        }
        if (!is_string($identifier) || !is_int($networkId) || !is_array($properties) || !is_string($encodedProperties)) {
            throw new RuntimeException('Block palette entry is missing its name, states, or signed network ID.');
        }
        $expected = self::signedFNV1a32(
            "\x0a\x00\x00"
            . "\x08" . self::littleEndianString('name') . self::littleEndianString($identifier)
            . "\x0a" . self::littleEndianString('states') . $encodedProperties
            . "\x00",
        );
        $isUnknownSentinel = $identifier === 'minecraft:unknown' && $properties === [] && $networkId === -2;
        if (!$isUnknownSentinel && $networkId !== $expected) {
            throw new RuntimeException("Block palette entry network ID {$networkId} does not match canonical state hash {$expected} for {$identifier}.");
        }
        try {
            $state = CanonicalBlockState::from($identifier, $properties);
        } catch (\InvalidArgumentException $error) {
            throw new RuntimeException('Block palette entry contains an invalid canonical state.', previous: $error);
        }
        return ['state' => $state, 'network_id' => $networkId];
    }

    /** @return array{array<string, int|string>, string} */
    private function networkProperties(int $depth): array
    {
        $this->depth($depth);
        $properties = [];
        $encoded = '';
        while (($type = $this->byte()) !== 0) {
            if (++$this->entries > self::MAX_ENTRIES || count($properties) >= 128) {
                throw new RuntimeException('Block palette states compound is oversized.');
            }
            $name = $this->string();
            if (array_key_exists($name, $properties) || !in_array($type, [1, 3, 8], true)) {
                throw new RuntimeException('Block palette state contains a duplicate or unsupported property.');
            }
            $value = match ($type) {
                1 => ($byte = $this->byte()) >= 128 ? $byte - 256 : $byte,
                3 => $this->signed($this->read(4)),
                8 => $this->string(),
            };
            $properties[$name] = $value;
            $encoded .= chr($type) . self::littleEndianString($name);
            $encoded .= match ($type) {
                1 => chr($value & 0xff),
                3 => pack('V', $value & 0xffff_ffff),
                8 => self::littleEndianString($value),
            };
        }
        return [$properties, $encoded . "\x00"];
    }

    private static function littleEndianString(string $value): string
    {
        if (strlen($value) > self::MAX_STRING_BYTES || preg_match('//u', $value) !== 1) {
            throw new RuntimeException('Block palette canonical NBT string is invalid or oversized.');
        }
        return pack('v', strlen($value)) . $value;
    }

    private static function signedFNV1a32(string $bytes): int
    {
        $hash = 0x811c9dc5;
        for ($offset = 0, $length = strlen($bytes); $offset < $length; ++$offset) {
            $hash = (($hash ^ ord($bytes[$offset])) * 0x01000193) & 0xffff_ffff;
        }
        return $hash > 0x7fff_ffff ? $hash - 0x1_0000_0000 : $hash;
    }

    /** @return list<PersistentBlockStateProperty> */
    private function persistentProperties(int $depth): array
    {
        $this->depth($depth);
        $properties = [];
        $names = [];
        while (($type = $this->byte()) !== 0) {
            if (++$this->entries > self::MAX_ENTRIES || count($properties) >= 128) {
                throw new RuntimeException('Block palette states compound is oversized.');
            }
            $name = $this->string();
            if (isset($names[$name])) {
                throw new RuntimeException('Block palette states compound contains a duplicate property.');
            }
            $names[$name] = true;
            try {
                $properties[] = match ($type) {
                    1 => PersistentBlockStateProperty::byte($name, ($value = $this->byte()) >= 128 ? $value - 256 : $value),
                    3 => PersistentBlockStateProperty::int($name, $this->signed($this->read(4))),
                    8 => PersistentBlockStateProperty::string($name, $this->string()),
                    default => throw new RuntimeException('Block palette state contains an unsupported persistent property type.'),
                };
            } catch (\InvalidArgumentException $error) {
                throw new RuntimeException('Block palette state contains an invalid persistent property.', previous: $error);
            }
        }
        return $properties;
    }

    /** @return list<mixed> */
    private function list(int $depth): array
    {
        $type = $this->byte();
        $count = $this->length();
        if ($type === 0 && $count !== 0) {
            throw new RuntimeException('NBT has a non-empty end-tag list.');
        }
        $values = [];
        for ($i = 0; $i < $count; ++$i) {
            $values[] = $this->payload($type, $depth + 1);
        }
        return $values;
    }

    /** @return list<int> */
    private function intArray(): array
    {
        $values = [];
        for ($count = $this->length(), $i = 0; $i < $count; ++$i) {
            $values[] = $this->signed($this->read(4));
        }
        return $values;
    }

    /** @return list<string> */
    private function longArray(): array
    {
        $values = [];
        for ($count = $this->length(), $i = 0; $i < $count; ++$i) {
            $values[] = $this->read(8);
        }
        return $values;
    }

    private function skipPayload(int $type, int $depth): void
    {
        $this->payload($type, $depth);
    }

    private function convertCompound(int $depth): string
    {
        $this->depth($depth);
        $output = '';
        while (($type = $this->byte()) !== 0) {
            if (++$this->entries > self::MAX_ENTRIES) {
                throw new RuntimeException('NBT exceeds its entry limit.');
            }
            $name = $this->string();
            $output .= self::encodedTagType($type, false) . pack('v', strlen($name)) . $name . $this->convertPayload($type, $depth + 1);
        }
        return $output . "\x00";
    }

    private function convertPayload(int $type, int $depth): string
    {
        $this->depth($depth);
        return match ($type) {
            1 => $this->read(1),
            2 => strrev($this->read(2)),
            3, 5 => strrev($this->read(4)),
            4, 6 => strrev($this->read(8)),
            7 => pack('V', $length = $this->length()) . $this->read($length),
            8 => ($value = $this->string()) !== '' ? pack('v', strlen($value)) . $value : "\x00\x00",
            9 => $this->convertList($depth + 1),
            10 => $this->convertCompound($depth + 1),
            11 => $this->convertArray(4),
            12 => $this->convertArray(8),
            default => throw new RuntimeException('NBT contains an unknown tag type.'),
        };
    }

    private function convertList(int $depth): string
    {
        $type = $this->byte();
        $count = $this->length();
        if ($type === 0 && $count !== 0) {
            throw new RuntimeException('NBT has a non-empty end-tag list.');
        }
        $output = self::encodedTagType($type, $count === 0) . pack('V', $count);
        for ($i = 0; $i < $count; ++$i) {
            $output .= $this->convertPayload($type, $depth + 1);
        }
        return $output;
    }

    private function convertArray(int $width): string
    {
        $count = $this->length();
        $output = pack('V', $count);
        for ($i = 0; $i < $count; ++$i) {
            $output .= strrev($this->read($width));
        }
        return $output;
    }

    private function length(): int
    {
        $value = $this->signed($this->read(4));
        if ($value < 0 || $value > self::MAX_COLLECTION) {
            throw new RuntimeException('NBT collection length is invalid or oversized.');
        }
        return $value;
    }

    private function string(): string
    {
        $unpacked = unpack('nvalue', $this->read(2));
        $length = $unpacked['value'] ?? null;
        if (!is_int($length) || $length > self::MAX_STRING_BYTES) {
            throw new RuntimeException('NBT string is oversized.');
        }
        $value = $this->read($length);
        if (preg_match('//u', $value) !== 1) {
            throw new RuntimeException('NBT string is not valid UTF-8.');
        }
        return $value;
    }

    private function signed(string $bytes): int
    {
        $format = strlen($bytes) === 2 ? 'nvalue' : 'Nvalue';
        $unpacked = unpack($format, $bytes);
        $value = $unpacked['value'] ?? null;
        if (!is_int($value)) {
            throw new RuntimeException('NBT integer cannot be decoded.');
        }
        $bits = strlen($bytes) * 8;
        $threshold = 1 << ($bits - 1);
        return $value >= $threshold ? $value - (1 << $bits) : $value;
    }

    private function float(): float
    {
        $unpacked = unpack('Gvalue', $this->read(4));
        $value = $unpacked['value'] ?? null;
        if (!is_float($value) || !is_finite($value)) {
            throw new RuntimeException('NBT contains an invalid float.');
        }
        return $value;
    }

    private function double(): float
    {
        $unpacked = unpack('Evalue', $this->read(8));
        $value = $unpacked['value'] ?? null;
        if (!is_float($value) || !is_finite($value)) {
            throw new RuntimeException('NBT contains an invalid double.');
        }
        return $value;
    }

    private function depth(int $depth): void
    {
        if ($depth > self::MAX_DEPTH) {
            throw new RuntimeException('NBT exceeds its nesting-depth limit.');
        }
    }

    private static function encodedTagType(int $type, bool $allowEnd): string
    {
        $minimum = $allowEnd ? 0 : 1;
        if ($type < $minimum || $type > 12) {
            throw new RuntimeException('NBT contains an unknown tag type.');
        }
        return chr($type);
    }

    /** @phpstan-impure */
    private function byte(): int
    {
        return ord($this->read(1));
    }

    private function read(int $length): string
    {
        if ($length < 0 || $this->offset + $length > strlen($this->bytes)) {
            throw new RuntimeException('NBT is truncated.');
        }
        $value = substr($this->bytes, $this->offset, $length);
        $this->offset += $length;
        return $value;
    }

    private function end(): void
    {
        if ($this->offset !== strlen($this->bytes)) {
            throw new RuntimeException('NBT contains trailing bytes.');
        }
    }
}
