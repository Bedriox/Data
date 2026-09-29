# Compatibility

| Component | Current policy |
|---|---|
| PHP | `^8.4` |
| Operating systems | Platform-neutral library; CI covers current Ubuntu and Windows runners |
| Data schemas | Admission manifests 1 and 2 and the `BedrockDataSet` runtime contract; schema 2 adds approved creative and physical block data bundles |
| Bedrock version | Minecraft Bedrock 1.26.52 / protocol 2193, backed by the active hash-verified admission manifest |
| Package stability | Pre-1.0; breaking changes require changelog and migration notes |

`BedrockVersion` is the single source of truth for the admitted game version, qualified client, and protocol identifier. Any other protocol must be rejected by the server.

The active entity contract contains 137 canonical definitions, 137 unique explicit network runtime IDs, 90 spawn-egg mappings, and 19 client properties owned by 13 entities. Its normalized semantic SHA-256 is `dbc19d3ccfdeeb21a5d36006cac31a43e5b412ac3c04856656d8f1caed2908ff`. This contract intentionally excludes dimensions, base attributes, persistence rules, gameplay categories, and AI behavior until authoritative inputs for those fields are admitted.
