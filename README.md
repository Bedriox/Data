# Bedriox Data

Bedriox Data contains versioned Minecraft: Bedrock Edition datasets generated for the Bedriox ecosystem from official Bedrock Dedicated Server runtime evidence.

These datasets are used by [Bedriox](https://github.com/Bedriox/Bedriox), the Bedriox server software.

Each release is immutable and identified by its Bedrock game version and network protocol. Manifests bind every published file to its exact size and SHA-256 digest.

## Included datasets

- Runtime item states and item components
- Creative inventory groups and entries
- Crafting, potion, and container recipes
- Canonical block palettes and block-to-item mappings
- Block properties, collision geometry, and voxel shapes
- Biome definitions
- Entity identifiers and actor properties
- Data-driven blocks and jigsaw structures
- Legacy block and item compatibility mappings
- Machine-readable and human-reviewed change information

## Current release

| Bedrock version | Protocol | Artifact directory | Manifest |
| --- | ---: | --- | --- |
| 1.26.52 | 2193 | `artifacts/bedrock-1.26.52-protocol-2193/` | `manifests/bedrock-1.26.52-protocol-2193-schema-2.json` |

The manifest records the source executable hash, generator revision, approval, artifact paths, sizes, and hashes. Consumers should verify the manifest before loading an artifact.

## Generation and releases

Datasets are generated, verified, and reviewed by the Bedriox Team, reproduced twice, and admitted as immutable release directories.

Future versions are added in new artifact directories; existing release paths are never replaced.

## Licensing and attribution

Bedriox's copyright and database rights in the dataset collection, selection, arrangement, manifests, and documentation are available under [Creative Commons Attribution 4.0 International](LICENSE). When sharing the licensed material, credit:

> Bedriox — https://github.com/Bedriox

The admission tool source code is licensed separately under [GPL-3.0-only](LICENSE-CODE). See [ATTRIBUTION.md](ATTRIBUTION.md) and [NOTICE](NOTICE) for scope and third-party-rights information.

Minecraft and related names and identifiers are trademarks or property of Microsoft Corporation and Mojang Studios. Bedriox is an independent project and is not affiliated with or endorsed by Microsoft or Mojang.
