# Security model

Approved bundles are untrusted input and must remain behind size, nesting, count, path, and resource limits. Admission must not follow unexpected symlinks, write outside its designated repository paths, execute bundle content, or access the network.

Runtime artifacts are verified by exact size and cryptographic hash before every bounded read. Canonical block identifiers, property names and scalar values are validated before they enter the immutable palette API; unknown states and runtime IDs fail closed. Gzip expansion, NBT nesting, collection sizes, entry counts, strings, and trailing data are bounded. A hash establishes integrity, not permission to redistribute. Report vulnerabilities privately as described in [`SECURITY.md`](../SECURITY.md).

Persistent block-state roots accept only the vanilla unnamed compound containing one String `name`, one Int `version`, and one Compound `states`. State properties accept only bounded Byte, Int, and UTF-8 String tags with unique validated names. Unknown but structurally valid states remain opaque bytes; malformed data is never treated as opaque, and opaque states cannot be used for canonical lookup, network translation, or gameplay authority.

Approved release bundles are untrusted input even when produced by private tooling. The public validator requires normalized relative paths, an allowlisted logical artifact set, exact sizes and SHA-256 hashes, bounded totals, direct non-symlink files, no unlisted files, immutable lexical artifact order, pinned source/generator identity, and an affirmative approval hash. Application is create-only and rolls back files created by a failed attempt. It never executes bundle content or uses bundle-provided paths outside the repository root.
