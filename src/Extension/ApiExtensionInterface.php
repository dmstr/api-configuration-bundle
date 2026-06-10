<?php
// file generated with AI assistance: Claude Code - 2025-11-01 00:00:00

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Extension;

use Dmstr\ApiConfiguration\ApiClient\ApiClientInterface;

/**
 * Interface for pluggable API client extensions
 */
interface ApiExtensionInterface
{
    /**
     * Get the extension name (e.g., 'basecamp2', 'github', 'gitlab')
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get the endpoint type (rest or file)
     *
     * @return string 'rest' or 'file'
     */
    public function getType(): string;

    /**
     * Get the path to the JSON schema file for this extension
     *
     * @return string Absolute path to schema.json
     */
    public function getSchemaPath(): string;

    /**
     * Create an API client instance with the provided configuration
     *
     * @param array $config Configuration array validated against schema
     * @return ApiClientInterface
     * @throws \InvalidArgumentException If configuration is invalid
     */
    public function createClient(array $config): ApiClientInterface;
}
