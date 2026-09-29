# Testing

Run the full local baseline with `composer check`. This runs PHPUnit and maximum-level PHPStan.

The bundled admitted-source tests load every artifact through its exact size/SHA-256 boundary. They validate all 22,091 ordered canonical block states and bidirectional network-runtime lookups, the four fixed-flat states, 98 data-driven block-property compounds, 2,076 unique item IDs and 78 non-empty component roots, all 89 biomes, and the actor registry. Persistent-state tests semantically encode and decode every admitted entry; synthetic golden roots cover air, a Byte bedrock property, mixed Int/Byte blue-candle properties, a String dark-oak property, an unknown identifier, and an unknown combination. They also prove concatenated-root offsets, exact opaque-byte preservation, root and property ordering, tag-type-sensitive classification, and malformed/truncated/oversized rejection. These synthetic golden bytes are test code, not new admitted artifacts.

Malformed, truncated, oversized, unknown, duplicate, and out-of-range inputs fail closed. Active-view tests cover the admitted 1.26.52 dataset and protocol-2193 declaration. Dataset preparation determinism and source normalization are tested in the private builder; this repository tests the approved release bundle and its public admission boundary.

Recipe tests parse all 4,768 admitted records, require contiguous source indexes, prove the exact 4,128 crafting-table / 592 workstation / 14 complex / 34 deprecated partition, and verify every record type and station count. They also prove typed exposure of all 2 container and 210 potion mixes. Synthetic failures cover unknown types and stations, unknown item references, duplicate identities and UUIDs, oversized ingredient grids, undefined or unused shape symbols, extra fields, malformed mixes, and invalid output data.

CI also validates Composer metadata, audits dependencies, and runs on current Ubuntu and Windows runners.

Creative-data tests use small original synthetic JSON fixtures. They cover repeated item identifiers with distinct creative variants, ordered groups and entries, NBT and block-state validation, cross-category group rejection, duplicate IDs, unknown item and block references, block-item bijection, property-to-palette validation, voxel bounds, and unknown lookups.

Entity-catalog tests pin 137 definitions, 112 summonable identities, 89 actor-advertised eggs, all 90 admitted spawn-egg item mappings, 13 property owners, 19 typed properties, and the normalized semantic hash. They prove that explicit runtime IDs and the hash remain independent of source-list order, every admitted egg resolves bijectively, and malformed, truncated, oversized, missing, unknown, duplicate, and out-of-range entity data fails closed.

Release-bundle tests build synthetic approved bundles in temporary directories. They cover affirmative approval binding, artifact hashes, traversal paths, required and unlisted files, deterministic logical-name ordering, aggregate bounds, create-only application, rollback boundaries, and refusal to overwrite an immutable admission. No test downloads upstream data.
