<?php

declare(strict_types=1);

namespace Bedriox\Data;

use JsonException;
use RuntimeException;

/** Strict public boundary for final, verified, reviewed, and explicitly approved release bundles. */
final class ReleaseBundleAdmissionValidator
{
    public const int MAX_BUNDLE_BYTES = 134_217_728;

    /** @var list<string> */
    private const array REQUIRED_ARTIFACTS = [
        'biome_definitions',
        'block_item_mappings',
        'block_palette',
        'block_properties',
        'change_report',
        'creative_inventory',
        'data_driven_blocks',
        'entity_identifiers',
        'item_components',
        'runtime_item_states',
        'voxel_shapes',
    ];

    /** @var list<string> */
    private const array OPTIONAL_ARTIFACTS = [
        'creative_contents',
        'entity_properties',
        'jigsaw_structures',
        'legacy_block_ids',
        'legacy_item_ids',
        'recipes',
        'stripped_biomes',
    ];

    public function validate(string $bundleDirectory): ApprovedReleaseBundle
    {
        if ($bundleDirectory === '' || str_contains($bundleDirectory, "\0")) {
            throw new RuntimeException('Release bundle directory is invalid.');
        }
        $root = realpath($bundleDirectory);
        if (!is_string($root) || !is_dir($root) || is_link($bundleDirectory)) {
            throw new RuntimeException('Release bundle must resolve to a direct directory.');
        }
        $manifestPath = $root . DIRECTORY_SEPARATOR . 'dataset-manifest.json';
        $manifestContents = self::readDirectFile($manifestPath, AdmittedDataManifest::MAX_MANIFEST_BYTES);
        try {
            $document = json_decode($manifestContents, true, 24, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('Release bundle manifest is not valid bounded JSON.', previous: $error);
        }
        if (!is_array($document) || array_is_list($document)
            || !self::hasExactKeys($document, ['approval', 'artifacts', 'edition', 'gameVersion', 'generator', 'protocolVersion', 'qualifiedClientVersion', 'schema', 'source'])
            || ($document['schema'] ?? null) !== 2 || ($document['edition'] ?? null) !== 'bedrock'
            || !is_string($document['gameVersion']) || !self::isVersion($document['gameVersion'])
            || !is_string($document['qualifiedClientVersion']) || !self::isVersion($document['qualifiedClientVersion'])
            || !is_int($document['protocolVersion']) || $document['protocolVersion'] < 1 || $document['protocolVersion'] > 100_000
            || !is_array($document['source']) || !is_array($document['generator']) || !is_array($document['approval'])
            || !is_array($document['artifacts']) || !array_is_list($document['artifacts'])
            || count($document['artifacts']) > AdmittedDataManifest::MAX_ARTIFACTS) {
            throw new RuntimeException('Release bundle manifest has an invalid top-level shape.');
        }
        self::validateSource($document['source']);
        self::validateGenerator($document['generator']);
        self::validateApproval($document['approval']);

        $allowed = array_fill_keys(array_merge(self::REQUIRED_ARTIFACTS, self::OPTIONAL_ARTIFACTS), true);
        $artifacts = [];
        $paths = [];
        $lastName = null;
        $totalBytes = strlen($manifestContents);
        $contentHashInput = '';
        $semanticArtifacts = [];
        foreach ($document['artifacts'] as $data) {
            if (!is_array($data) || !self::hasExactKeys($data, ['name', 'path', 'sha256', 'size'])
                || !is_string($data['name']) || !isset($allowed[$data['name']]) || !is_string($data['path'])
                || !is_int($data['size']) || !is_string($data['sha256'])) {
                throw new RuntimeException('Release bundle manifest contains an invalid or unsupported artifact.');
            }
            $record = new AdmittedArtifact($data['name'], $data['path'], $data['size'], $data['sha256']);
            if (!str_starts_with($record->path(), 'artifacts/') || substr_count($record->path(), '/') !== 2
                || isset($artifacts[$record->name()]) || isset($paths[$record->path()])
                || ($lastName !== null && strcmp($lastName, $record->name()) >= 0)) {
                throw new RuntimeException('Release bundle artifact paths, names, or ordering are invalid.');
            }
            $absolutePath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $record->path());
            $contents = self::readDirectFile($absolutePath, $record->size());
            if (strlen($contents) !== $record->size() || hash('sha256', $contents) !== $record->sha256()) {
                throw new RuntimeException('Release bundle artifact size or SHA-256 does not match its manifest.');
            }
            $totalBytes += $record->size();
            if ($totalBytes > self::MAX_BUNDLE_BYTES) {
                throw new RuntimeException('Release bundle exceeds the aggregate byte limit.');
            }
            $artifacts[$record->name()] = new ReleaseBundleArtifact($record, $absolutePath);
            if (in_array($record->name(), ['legacy_block_ids', 'legacy_item_ids', 'runtime_item_states'], true)) {
                $semanticArtifacts[$record->name()] = $contents;
            }
            $paths[$record->path()] = true;
            $lastName = $record->name();
            $contentHashInput .= $record->name() . "\0" . $record->path() . "\0" . $record->size() . "\0" . $record->sha256() . "\n";
        }
        foreach (self::REQUIRED_ARTIFACTS as $required) {
            if (!isset($artifacts[$required])) {
                throw new RuntimeException("Release bundle is missing required artifact {$required}.");
            }
        }
        $approvedHash = $document['approval']['contentSha256'];
        if (!is_string($approvedHash) || !hash_equals($approvedHash, hash('sha256', $contentHashInput))) {
            throw new RuntimeException('Release bundle approval does not match the artifact record set.');
        }
        self::validateLegacyArtifacts($semanticArtifacts);
        self::validateTree($root, array_keys($paths));

        return new ApprovedReleaseBundle($manifestContents, $document['gameVersion'], $document['protocolVersion'], $artifacts);
    }

    /** @param array<string, string> $artifacts */
    private static function validateLegacyArtifacts(array $artifacts): void
    {
        if (isset($artifacts['legacy_block_ids'])) {
            self::validateLegacyBlockIds(self::decodeJson($artifacts['legacy_block_ids'], 'legacy_block_ids'));
        }
        if (!isset($artifacts['legacy_item_ids'])) {
            return;
        }
        if (!isset($artifacts['runtime_item_states'])) {
            throw new RuntimeException('Legacy item validation requires runtime_item_states.');
        }
        $legacyItems = self::decodeJson($artifacts['legacy_item_ids'], 'legacy_item_ids');
        $runtimeItems = self::decodeJson($artifacts['runtime_item_states'], 'runtime_item_states');
        self::validateLegacyItemIds($legacyItems, $runtimeItems);
    }

    private static function validateLegacyBlockIds(mixed $value): void
    {
        if (!is_array($value) || array_is_list($value) || count($value) > 8_192) {
            throw new RuntimeException('legacy_block_ids must be a non-empty bounded identifier map.');
        }
        foreach ($value as $identifier => $id) {
            if (!is_string($identifier) || !self::isCanonicalIdentifier($identifier)
                || !is_int($id) || $id < 0 || $id > 32_767) {
                throw new RuntimeException('legacy_block_ids contains an invalid identifier or numeric ID.');
            }
        }
    }

    private static function validateLegacyItemIds(mixed $legacyItems, mixed $runtimeItems): void
    {
        if (!is_array($legacyItems) || array_is_list($legacyItems)
            || !self::hasExactKeys($legacyItems, ['complex', 'numericIds', 'schema', 'simple'])
            || ($legacyItems['schema'] ?? null) !== 1
            || !is_array($legacyItems['numericIds']) || $legacyItems['numericIds'] === [] || count($legacyItems['numericIds']) > 8_192
            || !is_array($legacyItems['simple']) || count($legacyItems['simple']) > 8_192
            || !is_array($legacyItems['complex']) || count($legacyItems['complex']) > 8_192) {
            throw new RuntimeException('legacy_item_ids must be a bounded schema-1 mapping document.');
        }

        $numericIds = [];
        foreach ($legacyItems['numericIds'] as $identifier => $id) {
            if (!is_string($identifier) || !self::isCanonicalIdentifier($identifier)
                || !is_int($id) || $id < -32_768 || $id > 32_767 || isset($numericIds[$id])) {
                throw new RuntimeException('legacy_item_ids contains an invalid or duplicate numeric ID.');
            }
            $numericIds[$id] = true;
        }
        foreach ($legacyItems['simple'] as $alias => $target) {
            if (!is_string($alias) || !self::isCanonicalIdentifier($alias)
                || !is_string($target) || !self::isCanonicalIdentifier($target)) {
                throw new RuntimeException('legacy_item_ids contains an invalid simple alias.');
            }
        }
        foreach ($legacyItems['complex'] as $alias => $mappings) {
            if (!is_string($alias) || !self::isCanonicalIdentifier($alias)
                || !is_array($mappings) || $mappings === [] || count($mappings) > 32_768) {
                throw new RuntimeException('legacy_item_ids contains an invalid complex alias.');
            }
            foreach ($mappings as $aux => $target) {
                if ((!is_int($aux) && preg_match('/^(?:0|[1-9][0-9]{0,4})$/D', $aux) !== 1)
                    || (int) $aux < 0 || (int) $aux > 32_767
                    || !is_string($target) || !self::isCanonicalIdentifier($target)) {
                    throw new RuntimeException('legacy_item_ids contains an invalid complex-alias mapping.');
                }
            }
        }

        if (!is_array($runtimeItems) || !array_is_list($runtimeItems) || $runtimeItems === [] || count($runtimeItems) > 8_192) {
            throw new RuntimeException('runtime_item_states must be a non-empty bounded list for legacy item validation.');
        }
        $runtimeByName = [];
        $runtimeIds = [];
        foreach ($runtimeItems as $item) {
            if (!is_array($item) || !self::hasExactKeys($item, ['componentBased', 'id', 'name', 'version'])
                || !is_bool($item['componentBased']) || !is_int($item['id']) || !is_int($item['version'])
                || !is_string($item['name']) || !self::isCanonicalIdentifier($item['name'])
                || isset($runtimeByName[$item['name']]) || isset($runtimeIds[$item['id']])) {
                throw new RuntimeException('runtime_item_states contains an invalid or duplicate entry for legacy item validation.');
            }
            $runtimeByName[$item['name']] = $item['id'];
            $runtimeIds[$item['id']] = true;
        }

        $shared = 0;
        $equal = 0;
        foreach ($legacyItems['numericIds'] as $identifier => $legacyId) {
            if (isset($runtimeByName[$identifier])) {
                ++$shared;
                if ($runtimeByName[$identifier] === $legacyId) {
                    ++$equal;
                }
            }
        }
        if ($shared >= 32 && $equal * 10 >= $shared * 9) {
            throw new RuntimeException(sprintf(
                'legacy_item_ids appears to contain runtime/network IDs (%d of %d shared identifiers are equal).',
                $equal,
                $shared,
            ));
        }
    }

    private static function decodeJson(string $contents, string $label): mixed
    {
        try {
            return json_decode($contents, true, 24, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException($label . ' is not valid bounded JSON.', previous: $error);
        }
    }

    private static function isCanonicalIdentifier(string $value): bool
    {
        return strlen($value) <= 256 && preg_match('/^[a-z0-9_.-]+:[a-z0-9_.-]+$/D', $value) === 1;
    }

    /** @param array<array-key, mixed> $source */
    private static function validateSource(array $source): void
    {
        if (!self::hasExactKeys($source, ['commit', 'license', 'retrievedAt', 'rightsHolder', 'uri'])
            || !is_string($source['uri']) || filter_var($source['uri'], FILTER_VALIDATE_URL) === false
            || !is_string($source['commit']) || preg_match('/^[a-f0-9]{40}$/D', $source['commit']) !== 1
            || !is_string($source['retrievedAt']) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D', $source['retrievedAt']) !== 1
            || !is_string($source['rightsHolder']) || trim($source['rightsHolder']) === '' || strlen($source['rightsHolder']) > 512
            || !is_string($source['license']) || trim($source['license']) === '' || strlen($source['license']) > 128) {
            throw new RuntimeException('Release bundle source metadata is invalid.');
        }
    }

    /** @param array<array-key, mixed> $generator */
    private static function validateGenerator(array $generator): void
    {
        if (!self::hasExactKeys($generator, ['name', 'revision', 'version'])
            || !is_string($generator['name']) || trim($generator['name']) === '' || strlen($generator['name']) > 128
            || !is_string($generator['version']) || !self::isVersion($generator['version'])
            || !is_string($generator['revision']) || preg_match('/^[a-f0-9]{40}$/D', $generator['revision']) !== 1) {
            throw new RuntimeException('Release bundle generator identity is invalid.');
        }
    }

    /** @param array<array-key, mixed> $approval */
    private static function validateApproval(array $approval): void
    {
        if (!self::hasExactKeys($approval, ['approvedAt', 'contentSha256', 'redistributionApproved', 'reviewedBy'])
            || !is_string($approval['reviewedBy']) || trim($approval['reviewedBy']) === '' || strlen($approval['reviewedBy']) > 256
            || !is_string($approval['approvedAt']) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D', $approval['approvedAt']) !== 1
            || ($approval['redistributionApproved'] ?? null) !== true
            || !is_string($approval['contentSha256']) || preg_match('/^[a-f0-9]{64}$/D', $approval['contentSha256']) !== 1) {
            throw new RuntimeException('Release bundle lacks a valid affirmative approval.');
        }
    }

    /** @param list<string> $expectedPaths */
    private static function validateTree(string $root, array $expectedPaths): void
    {
        $expected = array_fill_keys(array_merge(['dataset-manifest.json'], $expectedPaths), true);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iterator as $entry) {
            if (!$entry instanceof \SplFileInfo) {
                throw new RuntimeException('Release bundle traversal produced an invalid entry.');
            }
            if ($entry->isLink()) {
                throw new RuntimeException('Release bundle cannot contain symlinks.');
            }
            if ($entry->isFile()) {
                $relative = str_replace('\\', '/', substr($entry->getPathname(), strlen($root) + 1));
                if (!isset($expected[$relative])) {
                    throw new RuntimeException('Release bundle contains an unlisted file.');
                }
                unset($expected[$relative]);
            }
        }
        if ($expected !== []) {
            throw new RuntimeException('Release bundle tree is missing a manifest-listed file.');
        }
    }

    private static function readDirectFile(string $path, int $limit): string
    {
        $resolved = realpath($path);
        if (is_link($path) || !is_string($resolved) || !is_file($resolved)
            || self::normalize($resolved) !== self::normalize($path)) {
            throw new RuntimeException('Release bundle member must be a direct file inside the bundle.');
        }
        $size = @filesize($resolved);
        if (!is_int($size) || $size < 1 || $size > $limit) {
            throw new RuntimeException('Release bundle member size is outside its declared limit.');
        }
        $contents = @file_get_contents($resolved, false, null, 0, $limit + 1);
        if (!is_string($contents) || strlen($contents) !== $size) {
            throw new RuntimeException('Release bundle member could not be read completely.');
        }
        return $contents;
    }

    private static function isVersion(string $version): bool
    {
        return preg_match('/^\d+\.\d+\.\d+$/D', $version) === 1 && strlen($version) <= 32;
    }

    private static function normalize(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    /**
     * @param array<array-key, mixed> $data
     * @param list<string> $expected
     */
    private static function hasExactKeys(array $data, array $expected): bool
    {
        if (count($data) !== count($expected)) {
            return false;
        }
        foreach ($expected as $key) {
            if (!array_key_exists($key, $data)) {
                return false;
            }
        }
        return true;
    }
}
