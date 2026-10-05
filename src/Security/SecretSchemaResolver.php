<?php
// file generated with AI assistance: Claude Code - 2026-10-05 10:25:39 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Security;

use Dmstr\ApiConfiguration\Service\ApiExtensionRegistry;
use Dmstr\OpenApiJsonSchema\Service\SchemaRegistry;

/**
 * Finds the secret keys of an API configuration type.
 *
 * A type declares its secrets in its own `schema.json` by marking the
 * property with `"writeOnly": true` (standard JSON Schema annotation):
 *
 *     "password": { "type": "string", "writeOnly": true }
 *
 * The result is a list of paths into `configJson`; `*` stands for every
 * item of an array or every value of a map (`items`,
 * `additionalProperties`, `patternProperties`).
 */
final class SecretSchemaResolver
{
    public const string MARKER = 'writeOnly';
    public const string WILDCARD = '*';

    /** @var array<string, list<list<string>>> */
    private array $cache = [];

    public function __construct(
        private readonly SchemaRegistry $schemaRegistry,
        private readonly ApiExtensionRegistry $extensionRegistry,
    ) {
    }

    /**
     * Secret paths of the given type.
     *
     * The schema is looked up by provider name, then by extension name, then
     * by the `type` const/enum of every registered schema. An unknown type
     * gets the secret paths of all registered schemas, so that a missing
     * mapping over-masks instead of leaking.
     *
     * @return list<list<string>>
     */
    public function getSecretPaths(string $type): array
    {
        return $this->cache[$type] ??= $this->resolve($type);
    }

    /**
     * Collects the paths of all properties marked as secret in a schema.
     *
     * @return list<list<string>>
     */
    public static function collectSecretPaths(array $schema): array
    {
        $paths = [];
        self::walk($schema, [], $schema, [], $paths);

        return array_values($paths);
    }

    /**
     * @return list<list<string>>
     */
    private function resolve(string $type): array
    {
        $schemas = [];

        if ($type !== '' && ($provider = $this->schemaRegistry->getProvider($type)) !== null) {
            $schemas[] = $this->load($provider->getSchemaPath());
        } elseif ($type !== '' && ($extension = $this->extensionRegistry->get($type)) !== null) {
            $schemas[] = $this->load($extension->getSchemaPath());
        } else {
            $all = array_map(
                fn ($provider): array => $this->load($provider->getSchemaPath()),
                array_values($this->schemaRegistry->getProviders()),
            );
            $matching = array_filter($all, static fn (array $schema): bool => self::declaresType($schema, $type));
            $schemas = $matching !== [] ? $matching : $all;
        }

        $paths = [];
        foreach ($schemas as $schema) {
            foreach (self::collectSecretPaths($schema) as $path) {
                $paths[self::key($path)] = $path;
            }
        }

        return array_values($paths);
    }

    private function load(string $schemaPath): array
    {
        $content = is_file($schemaPath) ? file_get_contents($schemaPath) : false;
        if ($content === false) {
            throw new \RuntimeException(sprintf('Cannot read API configuration schema "%s"', $schemaPath));
        }

        $schema = json_decode($content, true);
        if (!is_array($schema)) {
            throw new \RuntimeException(sprintf('Invalid JSON in API configuration schema "%s"', $schemaPath));
        }

        return $schema;
    }

    private static function declaresType(array $schema, string $type): bool
    {
        $typeSchema = $schema['properties']['type'] ?? null;
        if (!is_array($typeSchema)) {
            return false;
        }

        return ($typeSchema['const'] ?? null) === $type
            || in_array($type, (array) ($typeSchema['enum'] ?? []), true);
    }

    /**
     * @param list<string> $path
     * @param array<string, true> $seenRefs
     * @param array<string, list<string>> $paths
     */
    private static function walk(mixed $node, array $path, array $root, array $seenRefs, array &$paths): void
    {
        if (!is_array($node)) {
            return;
        }

        if (isset($node['$ref']) && is_string($node['$ref']) && !isset($seenRefs[$node['$ref']])) {
            $seenRefs[$node['$ref']] = true;
            self::walk(self::resolveRef($root, $node['$ref']), $path, $root, $seenRefs, $paths);
        }

        if ($path !== [] && ($node[self::MARKER] ?? false) === true) {
            $paths[self::key($path)] = $path;

            return;
        }

        foreach ((array) ($node['properties'] ?? []) as $name => $property) {
            self::walk($property, [...$path, (string) $name], $root, $seenRefs, $paths);
        }

        foreach ((array) ($node['patternProperties'] ?? []) as $property) {
            self::walk($property, [...$path, self::WILDCARD], $root, $seenRefs, $paths);
        }

        foreach (['additionalProperties', 'items', 'additionalItems'] as $keyword) {
            $sub = $node[$keyword] ?? null;
            if (is_array($sub) && array_is_list($sub)) {
                // tuple form of `items`
                foreach ($sub as $item) {
                    self::walk($item, [...$path, self::WILDCARD], $root, $seenRefs, $paths);
                }
            } else {
                self::walk($sub, [...$path, self::WILDCARD], $root, $seenRefs, $paths);
            }
        }

        foreach ((array) ($node['prefixItems'] ?? []) as $item) {
            self::walk($item, [...$path, self::WILDCARD], $root, $seenRefs, $paths);
        }

        // Combinators and conditionals describe the same level
        foreach (['allOf', 'anyOf', 'oneOf'] as $keyword) {
            foreach ((array) ($node[$keyword] ?? []) as $sub) {
                self::walk($sub, $path, $root, $seenRefs, $paths);
            }
        }
        foreach (['if', 'then', 'else'] as $keyword) {
            self::walk($node[$keyword] ?? null, $path, $root, $seenRefs, $paths);
        }
        foreach (['dependentSchemas', 'dependencies'] as $keyword) {
            foreach ((array) ($node[$keyword] ?? []) as $sub) {
                self::walk($sub, $path, $root, $seenRefs, $paths);
            }
        }
    }

    /**
     * Resolves a local JSON pointer (`#/definitions/auth`); external
     * references are not followed.
     */
    private static function resolveRef(array $root, string $ref): mixed
    {
        if (!str_starts_with($ref, '#')) {
            return null;
        }

        $node = $root;
        foreach (array_filter(explode('/', substr($ref, 1)), static fn (string $s): bool => $s !== '') as $segment) {
            $segment = str_replace(['~1', '~0'], ['/', '~'], rawurldecode($segment));
            if (!is_array($node) || !array_key_exists($segment, $node)) {
                return null;
            }
            $node = $node[$segment];
        }

        return $node;
    }

    /**
     * @param list<string> $path
     */
    private static function key(array $path): string
    {
        return implode("\0", $path);
    }
}
