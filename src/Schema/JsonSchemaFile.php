<?php
// file generated with AI assistance: Claude Code - 2026-10-05 12:10:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Schema;

/**
 * Reads a type's `schema.json`.
 */
final class JsonSchemaFile
{
    /**
     * @throws \RuntimeException if the file is missing or not a JSON object
     */
    public static function load(string $path): array
    {
        $content = is_file($path) ? file_get_contents($path) : false;
        if ($content === false) {
            throw new \RuntimeException(sprintf('Cannot read API configuration schema "%s"', $path));
        }

        $schema = json_decode($content, true);
        if (!is_array($schema)) {
            throw new \RuntimeException(sprintf('Invalid JSON in API configuration schema "%s"', $path));
        }

        return $schema;
    }
}
