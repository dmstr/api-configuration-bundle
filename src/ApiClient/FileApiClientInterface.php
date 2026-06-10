<?php

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\ApiClient;

/**
 * Interface for file-based API clients (Jira XML exports)
 */
interface FileApiClientInterface extends ApiClientInterface
{
    /**
     * Parse a file and return structured data
     *
     * @param string $filePath Path to the file to parse
     * @return array Parsed data structure
     * @throws \InvalidArgumentException If file doesn't exist or is invalid
     */
    public function parseFile(string $filePath): array;

    /**
     * Validate a file before parsing
     *
     * @param string $filePath Path to the file to validate
     * @return bool True if file is valid for this client
     */
    public function validateFile(string $filePath): bool;

    /**
     * Get supported file formats
     *
     * @return array Array of supported file extensions (e.g., ['xml', 'json'])
     */
    public function getSupportedFormats(): array;
}
