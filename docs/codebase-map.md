# Codebase map

Bedriox Data is a read-only leaf package. It owns reviewed immutable inputs and typed lookup values, never networking or mutable server state.

| Area | Ownership |
|---|---|
| `BedrockVersion.php` | Sole active base game version, qualified client, protocol, source revision identity, and explicit active manifest path. |
| `BedrockDataSet.php` | Public hash-verifying entry point for the active runtime dataset. |
| `AdmittedDataManifest.php` and `AdmittedArtifact.php` | Single validated authority for admitted artifact paths, sizes, and hashes. |
| `AdmittedRegistryDataSet.php` | Internal adapter over manifest-authorized reviewed source files. |
| `ArtifactRecord.php` | Validated source metadata, paths, hashes, and redistribution decision. |
| `CanonicalBlockState.php` and `CanonicalBlockStateIndex.php` | Canonical `minecraft:*` identifiers, sorted properties, and dense lookup indexes. |
| `NetworkBlockStateRegistry.php` | Bidirectional canonical-state to Bedrock network-runtime-ID mapping from admitted palette order. |
| Persistent block-state types and codec | Typed/versioned vanilla LevelDB state roots, admitted known-state classification, concatenated decoding, and byte-exact opaque preservation without IDs. |
| NBT validators | Bounded parsing and structural validation for admitted registry data. |
| Creative inventory types and registry | Ordered groups and creative-ID entries with item, damage, NBT, and canonical block-state variants. |
| Recipe types and `RecipeRegistry.php` | Complete bounded classification of shaped, shapeless, complex, and smithing recipe records with item cross-reference validation. |
| Block item/property and voxel registries | Typed canonical metadata with item/palette cross-reference validation. |
| Release bundle admission types | Final approval, allowlist, path, hash, tree, and create-only publication boundary. |
| `tools/admit-dataset.php` | Public dry-run and `--apply` entry point for final approved schema-2 bundles. |
| `artifacts/` and `manifests/` | Immutable reviewed bytes and their source/hash authority. |
| `tests/` | Hash, parser, semantic registry, active-version, path-safety, and deterministic reproduction evidence. |

Bedriox owns process-local internal block IDs and authoritative recipe matching. Bedriox Protocol owns packet serialization. This repository owns canonical states, persistent vanilla state identity, creative and recipe source data, normalized block metadata, and Bedrock network mappings as separate contracts.
