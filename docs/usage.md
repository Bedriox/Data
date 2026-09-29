# Usage

Bedriox Data provides an unversioned runtime API for Bedriox's sole supported Bedrock release:

```php
use Bedriox\Data\BedrockDataSet;
use Bedriox\Data\BedrockVersion;

$data = BedrockDataSet::bundled();
$requiredItems = $data->requiredItems();
$itemNetworkRegistry = $data->itemNetworkRegistry();
$recipes = $data->recipeRegistry();
$flatBlockIds = $data->fixedFlatRuntimeIds();
$blockStates = $data->blockStateRegistry();
$persistentBlockStates = $data->persistentBlockStateRegistry();
$dataDrivenBlockProperties = $data->dataDrivenBlockProperties();
$plainsBiomeId = $data->plainsBiomeRuntimeId();
$biomes = $data->biomeDefinitions();
$biomeRuntimeIds = $data->biomeRuntimeIds();
$actorIdentifiers = $data->entityIdentifiersNetworkNbt();
$entities = $data->entityTypeRegistry();

$gameVersion = BedrockVersion::GAME_VERSION;
$qualifiedClientVersion = BedrockVersion::QUALIFIED_CLIENT_VERSION;
$protocolVersion = BedrockVersion::PROTOCOL;
```

Use the typed entity catalog for identity and spawn-egg translation instead of parsing actor bytes or assigning IDs from list positions:

```php
$zombie = $entities->definitionForIdentifier('minecraft:zombie');
$sameEntity = $entities->definitionForNetworkRuntimeId($zombie->networkRuntimeId());
$eggEntity = $entities->entityForSpawnEgg('minecraft:zombie_spawn_egg');

$summonable = $zombie->summonable();
$spawnEggItem = $zombie->spawnEggItemIdentifier();
$clientProperties = $zombie->properties();
$catalogHash = $entities->catalogSha256();
```

`definitions()` is sorted by canonical entity identifier. `spawnEggMappings()` is sorted by spawn-egg item identifier, contains every admitted `minecraft:*_spawn_egg` item exactly once, and may include an item even when the client actor record does not advertise its egg. `spawnEggAdvertised()` preserves that distinct actor-registry flag. Unknown identifiers, network runtime IDs, and spawn eggs fail closed.

The catalog exposes only behavior proven by the active artifacts: canonical identity, explicit network runtime ID, summonability, client spawn-egg advertisement, admitted spawn-egg item identity, and typed client properties. It does not supply hitbox dimensions, health or other base attributes, persistence, gameplay categories, or AI behavior.

The recipe catalog retains source order and provides complete, mutually exclusive classification:

```php
$coverage = $recipes->coverage();
$craftingRecipes = $recipes->craftingTableRecipes();
$deferredWorkstations = $recipes->workstationRecipes();
$complexRecipes = $recipes->complexRecipes();
$deprecatedRecords = $recipes->deprecatedRecipes();

$workbenchRecipes = $recipes->recipesForIdentifier('minecraft:WorkBench_recipeId');
```

`ShapedRecipe` exposes its rectangular shape and symbol map. `ShapelessRecipe` exposes ordered source ingredients and whether the result preserves user data. Exact-item ingredients are cross-checked against `ItemNetworkRegistry`; item-tag ingredients remain canonical typed tag references for the gameplay matcher. Outputs expose identifier, count, damage, legacy identity, and optional validated little-endian NBT. UUID-only transformations use `ComplexRecipe`, while smithing records remain typed and explicitly classified for their later workstation implementation.

After a schema-2 creative dataset is approved, admitted, and deliberately selected as the active manifest, the same unversioned view exposes:

```php
$creative = $data->creativeInventoryRegistry();
$blockItems = $data->blockItemMappingRegistry();
$blockProperties = $data->blockPropertyRegistry();
$voxelShapes = $data->voxelShapeRegistry();

$group = $creative->group(7);
$entry = $creative->entry(42);
$item = $entry->item();
```

Creative entries are looked up by their admitted creative network ID, not by item identifier. `entries()` and `groups()` retain dataset order. `CreativeInventoryItem` exposes its canonical item identifier, count, damage, optional canonical block state, and optional bounded little-endian NBT. Duplicate item identifiers are valid because variants remain distinct entries.

Block-item mappings are bidirectional between an item identifier and its default canonical block state. They are normalized from unambiguous block-state-bearing creative entries, not from legacy identifier/damage remapping tables. Block-property definitions expose admitted scalar values. Voxel shapes expose bounded boxes and canonical block-state assignments. Unknown IDs and references fail closed.

The bundled schema-2 manifest admits these registries as one reviewed, hash-verified dataset. Unknown or inconsistent entries fail closed instead of falling back to invented runtime data.

The recipe registry also exposes the admitted brewing transitions without requiring consumers to parse source JSON:

```php
$recipes = $dataSet->recipeRegistry();

foreach ($recipes->containerMixes() as $mix) {
    $outputIdentifier = $mix->outputItemIdentifier();
}

foreach ($recipes->potionMixes() as $mix) {
    $outputMetadata = $mix->outputMetadata();
}
```

Container mixes describe potion, splash-potion, and lingering-potion item conversions. Potion mixes preserve the input, reagent, and output metadata required to distinguish every admitted potion variant. Both collections retain dataset order and are immutable.

`biomeDefinitions()` preserves the validated definition-list order used by the current bootstrap packet. Its `id` field is an internal definition ordinal, not a chunk biome ID. `biomeRuntimeIds()` is the complete canonical-identifier-to-runtime-ID registry for `LevelChunk` biome palettes. Consumers must use that registry for chunk serialization instead of definition order or special-casing individual biomes.

The typed item registry provides immutable definitions by canonical identifier or signed network runtime ID:

```php
$stone = $itemNetworkRegistry->definitionForIdentifier('minecraft:stone');
$sameItem = $itemNetworkRegistry->definitionForNetworkRuntimeId($stone->networkRuntimeId());

$identifier = $stone->identifier();
$componentBased = $stone->componentBased();
$version = $stone->version();
$componentNbt = $stone->componentNbt(); // bounded little-endian named compound, or null
```

`definitions()` returns the complete identifier-keyed registry. Unknown identifiers and runtime IDs fail closed. `requiredItems()` remains available with its existing array shape for compatibility; the typed registry is derived exclusively from that validated projection.

Canonical block states remain independent of Bedrock's release-specific network IDs:

```php
use Bedriox\Data\CanonicalBlockState;

$grass = CanonicalBlockState::from('minecraft:grass_block');
$networkRuntimeId = $blockStates->networkRuntimeId($grass);
$sameState = $blockStates->stateForNetworkRuntimeId($networkRuntimeId);
```

`states()` returns the full ordered palette. `canonicalKey()` provides a deterministic lookup key after properties are validated and sorted. Unknown states and out-of-range runtime IDs fail closed. The server remains responsible for its own process-local internal IDs; Bedriox Data owns only canonical state data and the admitted Bedrock network mapping. Data-driven block properties are returned as identifier-keyed, bounded little-endian named root compounds ready for Protocol serialization.

Persistent world data uses a distinct typed registry and codec:

```php
use Bedriox\Data\KnownPersistentBlockState;
use Bedriox\Data\LittleEndianBlockStateNbtCodec;
use Bedriox\Data\OpaquePersistentBlockState;

$codec = new LittleEndianBlockStateNbtCodec($persistentBlockStates);
$decoded = $codec->decode($concatenatedLittleEndianNbt, $offset);
$state = $decoded->state();
$offset = $decoded->nextOffset();

if ($state instanceof KnownPersistentBlockState) {
    $canonical = $state->canonicalState();
    $version = $state->storageVersion();
} elseif ($state instanceof OpaquePersistentBlockState) {
    $unchangedBytes = $codec->encode($state);
}
```

Known states preserve ordered property tag types and optionally their original encoded root. Unknown identifiers, typed combinations, or versions are opaque and encode to their exact validated input bytes. Malformed roots are rejected rather than made opaque. Neither persistent state class exposes a network or Bedriox internal ID.

Consumers obtain decoded values through `LittleEndianBlockStateNbtCodec` and admitted values through `PersistentBlockStateRegistry`. The public `KnownPersistentBlockState::from()` and `fromDecoded()` methods are internal construction boundaries needed by the package: `from()` creates an admitted value without preserved bytes, while `fromDecoded()` reparses the complete preserved root and requires its identifier, exact storage version, and ordered typed properties to match the supplied values. `OpaquePersistentBlockState::fromValidatedEncodedRoot()` likewise reparses one complete bounded root, but does not classify it as admitted. Callers should not use these factories as substitutes for registry classification.

`BedrockVersion::GAME_VERSION` and `QUALIFIED_CLIENT_VERSION` identify the admitted `1.26.52` dataset on protocol 2193. Upgrades replace the single active profile instead of adding parallel versioned APIs.

The internal admitted-source adapter reads at most the recorded byte count and verifies both exact size and SHA-256 before returning data to the active projection. Typed methods validate bounded JSON, network NBT, and little-endian component NBT. Unknown names, changed bytes, excessive counts, malformed entries, non-canonical encodings, and truncated NBT fail closed. Runtime access performs no network request, generation, or write.

The general artifact source-record API remains available for future admissions:

```php
use Bedriox\Data\ArtifactRecord;

$record = new ArtifactRecord(
    path: 'example/file.bin',
    sha256: hash('sha256', $contents),
    sourceUri: 'https://example.invalid/source',
    retrievedAt: '2026-09-14',
    rightsHolder: 'Example rights holder',
    licenseExpression: 'Apache-2.0',
    generator: 'example-generator@1.0.0',
    reviewedBy: 'authorized-reviewer',
    redistributionApproved: true,
);
```

Repository licensing never overrides the individual license and redistribution status recorded for an artifact.

## Admitting an approved bundle

Validate without changing the repository:

```shell
php tools/admit-dataset.php --bundle=/path/to/final-approved-bundle
```

After reviewing the report and exact bundle, create the new immutable paths:

```shell
php tools/admit-dataset.php --bundle=/path/to/final-approved-bundle --apply
```

The bundle must already contain its final affirmative approval. Prepared or unapproved output is rejected. Admission does not commit, push, select the new manifest, or alter consumers; those remain explicit reviewed release steps.
