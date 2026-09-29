# Troubleshooting

## An artifact is rejected

Check that every required source-record field is present, the path is relative and normalized, the SHA-256 contains 64 lowercase hexadecimal characters, and redistribution has received an explicit review decision.

## Minecraft data is missing

That is intentional during the foundation milestone. Do not work around the absence by committing extracted client/server assets. Add only inputs and generated outputs whose source, license, integrity, and redistribution rights have been reviewed.

## A generated hash changed

Treat nondeterministic output as a failing build. Record the exact generator revision and inputs, reproduce the generation in a clean environment, and review the change before updating a manifest.

## A persistent block state is opaque

The identifier, exact property tag types and values, and storage version did not match one admitted palette entry. Preserve the opaque bytes for untouched storage, but do not assign an internal or network ID or use the state for gameplay. Malformed roots are rejected separately. A legitimate new state requires a new reviewed data admission rather than a guessed conversion.

## A release bundle is rejected

Run the validator without `--apply` and address the first reported boundary. Common causes are a prepared bundle with `approval: null`, a changed artifact after approval, an unsorted or unsupported logical name, an unsafe path, a missing required creative artifact, an unlisted temporary file, or an existing immutable target path. Do not edit and re-sign public output manually; rebuild and approve the exact corrected bundle in the owning private tool.

## A creative registry accessor reports a missing artifact

The active manifest or installed package is stale. Do not fall back to hardcoded creative entries. Verify the schema-2 admission, run the full Data gate, and refresh the consumer package pin.
