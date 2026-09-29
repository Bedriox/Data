# Registry contract

Block identity crosses three intentionally separate domains:

```text
canonical state in Bedriox Data
  -> process-local internal ID owned by Bedriox
  -> Bedrock network runtime ID translated at the packet boundary
```

`NetworkBlockStateRegistry` derives the final mapping exclusively from each admitted palette entry's explicit signed 32-bit `network_id`. It independently verifies that value against the FNV-1a hash of the entry's canonical little-endian block-state NBT and rejects missing, duplicate, or mismatched hashes. The sole exception is Bedrock's `minecraft:unknown` state, whose explicit `-2` sentinel is validated as such. Palette list order is deterministic iteration order only and never defines network identity. Lookup accepts either the canonical signed hash or its equivalent unsigned 32-bit projection. A network runtime ID must never be guessed, copied from another release, persisted as Bedriox world identity, or exposed as a stable plugin value. Bedriox internal IDs never enter this repository or the wire.

Persistent Bedrock state is a fourth, separate representation. It stores the canonical identifier, exact signed storage version, and ordered typed Byte, Int, or String properties in vanilla little-endian NBT. A state is known only when identifier, property names, property tag types, values, and version exactly match an admitted entry. Unknown identifiers, combinations, types, or versions remain bounded opaque roots so an untouched world cell can be written back byte-for-byte. Opaque roots never acquire a canonical, internal, or network ID and cannot authorize gameplay or packet translation.

The active dataset contract includes its exact version declaration, input hashes, ordered state count, required fixed-flat states, block properties, items, biomes, and actor identifiers. A change to any of these requires a new reviewed admission when bytes change, semantic assertions for required entries and indexes, deterministic generation, consumer interoperability tests, and synchronized source and license records.

Runtime loading performs bounded local reads and hash verification only. It never downloads, regenerates, repairs, or silently substitutes data.

Creative inventory identity is a separate versioned domain. A creative network ID identifies one ordered presentation entry, not an item type. Multiple entries may reference the same canonical item identifier while carrying different damage, NBT, group, or canonical block state. Consumers must resolve client creative selection through the admitted creative entry before constructing an authoritative stack.

Normalized block-item mappings and voxel assignments use canonical block states. They never persist or expose release-specific network runtime IDs as stable identity. Every item, block state, group, shape, and property reference must resolve during registry construction.
