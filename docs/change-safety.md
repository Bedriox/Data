# Change safety

Before editing, identify the exact source revision, artifact, manifest, parser, lookup, or active-version value involved. Record baseline tests and protected hashes, counts, canonical states, runtime indexes, and Protocol/Bedriox journeys.

Never modify admitted bytes in place. A corrected input is a new immutable admission with explicit source and license review. Generators run offline from pinned inputs, remain output-contained, and must produce byte-identical results twice. Tests must validate hashes and semantic content; file size alone is insufficient.

Run:

```shell
composer check
composer validate --strict
composer audit --locked
```

For a public dataset or version change, also run affected Protocol and Bedriox consumer tests plus the workspace verifier. Review every artifact and manifest diff, update documentation and notices as required, and record the prior Bedriox Data pin as the rollback point. A nearby Bedrock release or successful load is not proof of registry compatibility.
