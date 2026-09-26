# Bedriox Data agent guide

Read this file completely before changing this repository. Bedriox Data is the immutable publication repository for datasets generated, verified, and reviewed by the Bedriox Team.

## Repository purpose

This repository contains approved, versioned Minecraft: Bedrock Edition datasets used by [Bedriox](https://github.com/Bedriox/Bedriox). Dataset collection and generation happen outside this repository; this repository validates and publishes final release bundles.

The public contract is the schema-2 manifest under `manifests/` and its corresponding immutable directory under `artifacts/`.

## Published surface

- `artifacts/bedrock-VERSION-protocol-PROTOCOL/` contains the exact admitted payload.
- `manifests/bedrock-VERSION-protocol-PROTOCOL-schema-2.json` binds source, generator, approval, sizes, and SHA-256 values.
- `tools/admit-dataset.php` performs read-only validation or applies a new approved bundle.
- `src/` contains only the admission boundary and its immutable value objects.
- `tests/` covers admission success, rejection, containment, hashes, approval, namespace separation, and cleanup.

## Release procedure

1. Identify the target Bedrock version and protocol. Do not create a duplicate when that exact release already exists.
2. Collect and generate the complete dataset from current official server evidence. Never fill a missing value from a previous release or an external dataset.
3. Build twice and compare every relative filename, size, and SHA-256 digest. Nondeterminism blocks publication.
4. Review the machine-readable change report and human update report. Explain additions, removals, packet changes, and material property changes.
5. Bind the Bedriox Team's explicit review and redistribution approval to the exact content and bundle hashes.
6. Validate the approved bundle against a clean `Data/main` checkout with a dry run.
7. After explicit authorization, create a release branch. Never write a routine release directly to the default branch.
8. Review and merge the branch after CI passes. Confirm the remote manifest and artifact hashes match the approved bundle.
9. Create a tag or release only after the manifest is present on `main`.

## Admission commands

Read-only validation:

```powershell
php tools/admit-dataset.php --bundle=<approved-bundle-directory>
```

Apply an approved bundle only on its controlled release branch:

```powershell
php tools/admit-dataset.php --bundle=<approved-bundle-directory> --apply
```

Admission must start from a clean default branch, may create only new immutable paths, and must never overwrite an existing artifact or manifest.

## Required checks

```powershell
composer check
composer validate --strict
composer audit --locked
git diff --check
```

Also verify that every manifest path is a direct regular file beneath the repository, every size and hash matches, the complete tree is allowlisted, and no symlink is present.

## Safety and provenance

- Never commit BDS executables, game files, resource packs, worlds, observations, native dumps, decompiled material, authentication data, or secrets.
- Never hand-edit generated artifacts or their hashes. Correct the producer and regenerate.
- Never manufacture, guess, or backfill a missing packet, state, ID, property, recipe, actor, or geometry value.
- Preserve the distinction between the full BDS build, target Data version, advertised compatibility version, wire serializer version, and protocol number.
- The repository license grants only rights Bedriox is authorized to grant. Preserve `ATTRIBUTION.md` and `NOTICE`.
- Existing releases are immutable. A correction is a new versioned release with an explicit report.
- Routine releases never force-push `main`. History replacement is exceptional and requires explicit authorization plus a verified backup.

## Completion standard

A release is complete only when its source evidence is reproducible, the build is deterministic, the exact bundle is approved, the admission validator accepts it, all repository checks pass, the release-branch diff contains only intended new immutable paths, and the hashes on remote `main` match the approved bundle.
