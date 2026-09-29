# Architecture

Bedriox Data is a leaf package: it cannot depend on the server, protocol, or RakNet packages. Consumers read immutable artifacts through typed APIs. Generation is an offline development/release concern and must never download or transform remote inputs during normal server startup.

## Layout

- `src/`: manifest validation, approved-release admission, and immutable runtime lookup APIs.
- `tools/admit-dataset.php`: strict dry-run or create-only admission of a final approved release bundle.
- `artifacts/bedrock-1.26.52-protocol-2193/`: reviewed immutable runtime artifacts for the sole protocol-2193 target.
- `manifests/`: machine-readable per-artifact source records, hashes, and licensing decisions.

Artifacts are addressed by an explicit source game version and commit. "Latest" aliases are not permitted in reproducible builds. The unversioned `BedrockDataSet` is the sole active runtime view; `BedrockVersion` records the one current retail target, while `AdmittedRegistryDataSet` is an internal source adapter. `CanonicalBlockState` represents a validated identifier and sorted scalar properties. `NetworkBlockStateRegistry` preserves the admitted palette order and maps canonical states bidirectionally to Bedrock network runtime IDs; it deliberately does not own server-local IDs.

`PersistentBlockStateRegistry` is a separate projection of that same admitted palette. It retains the exact storage version and ordered Byte, Int, or String property types required by vanilla LevelDB block-state roots. `LittleEndianBlockStateNbtCodec` decodes one bounded root from a concatenated stream and reports its next offset. Exact admitted identifier, typed-property, value, and version matches become `KnownPersistentBlockState`; structurally valid but unknown roots become byte-preserving `OpaquePersistentBlockState`. Opaque states cannot enter the canonical network registry and expose no network or process-local IDs. Runtime code verifies hashes, bounded JSON/NBT structure, registry counts, and required semantic entries.

## Release-bundle boundary

Private acquisition and normalization tooling exports a self-contained directory containing `dataset-manifest.json` and only manifest-listed files under a versioned `artifacts/` directory. The public validator accepts schema 2 only after the final approval record is present and bound to the sorted artifact record set. It validates source and generator identity, paths, ordering, sizes, hashes, aggregate limits, required logical artifacts, approval, and absence of unlisted files or symlinks.

`--apply` revalidates the bundle, creates a new versioned manifest and artifact paths, and rolls back files created during a failed admission. It never overwrites an existing manifest or artifact. Normal server startup never invokes this path.

`AdmittedDataManifest` is the sole runtime authority for artifact names, paths, sizes, and hashes. Runtime readers consume that manifest instead of maintaining duplicate integrity tables.

Creative presentation data remains separate from network item identity. `CreativeInventoryRegistry` preserves ordered entries identified by creative network ID, so one item identifier may appear in multiple entries with different damage, NBT, or block state. Block-item, block-property, and voxel-shape registries cross-check canonical references against the admitted item and block registries.

`RecipeRegistry` parses the complete admitted recipe document behind a bounded immutable API. Every source row becomes one shaped, shapeless, complex, smithing-transform, or smithing-trim value and belongs to exactly one coverage category: crafting table, deferred workstation, complex, or deprecated. Container and potion mixing records become separate typed immutable values; potion metadata is retained exactly so consumers can build an indexed brewing graph without parsing artifact JSON. Exact ingredients and outputs are validated against the item registry; canonical item tags remain explicit because tag membership is owned by gameplay data. Source order and source indexes are preserved for deterministic network-ID assignment by consumers.

`EntityTypeRegistry` normalizes the admitted actor-identifier network NBT, entity-property standard NBT, and item registry into one immutable source-neutral catalog. The explicit actor runtime ID is the network authority; neither artifact order nor normalized sort order can become a runtime ID. Definitions are sorted by canonical identifier for stable iteration, while separate indexes provide identifier, runtime-ID, and spawn-egg lookups. Every admitted spawn egg must map bijectively to a known entity and every actor-advertised egg must exist in the item registry. The catalog hash covers normalized semantics and is stable when the source actor list is reordered.

Entity dimensions, base attributes, persistence behavior, gameplay categories, and AI definitions are deliberately absent. The current admitted artifacts do not establish those behaviors, so consumers must not infer them from identifiers, runtime IDs, spawn eggs, or list order. A future admission must provide reviewed authoritative inputs before those fields can join this contract.
