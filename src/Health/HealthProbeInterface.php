<?php
// file generated with AI assistance: Claude Code - 2026-10-05 12:10:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Health;

/**
 * Health check for one configuration type, without the project-management
 * methods of {@see \Dmstr\ApiConfiguration\ApiClient\ApiClientInterface}.
 *
 * Lets schema-only types (no ApiExtensionInterface, e.g. a workflow engine or
 * an MCP server) answer the health route, the health command and dashboards.
 * Implementations are tagged automatically; a probe takes precedence over the
 * client-based check for its type.
 */
interface HealthProbeInterface
{
    /**
     * The configuration type this probe checks (`configJson.type`).
     */
    public function getName(): string;

    /**
     * Endpoint URI reported in the health result (RFC 3986, `format: uri`).
     *
     * @param array $config decrypted configuration
     */
    public function getEndpoint(array $config): string;

    /**
     * Raw health information in the shape HealthNormalizer accepts:
     * `status`, `authenticated`, optional `message`, `metadata`
     * (`apiName`, `version`, `rateLimit`, `custom`) and `error`
     * (`code`, `message`, `details`).
     *
     * @param array $config decrypted configuration
     * @throws \Exception if the check itself fails; reported as an error result
     */
    public function probe(array $config): array;
}
