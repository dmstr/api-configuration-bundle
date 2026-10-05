<?php
// file generated with AI assistance: Claude Code - 2026-10-05 12:10:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\ApiClient;

/**
 * Base interface for all API clients (REST + File).
 *
 * Only what every source supports belongs here. Optional capabilities are
 * separate interfaces that callers check with `instanceof`, so "unsupported"
 * is no longer indistinguishable from "empty":
 * {@see CustomerAwareApiClientInterface}, {@see TodoListAwareApiClientInterface},
 * {@see ChangeProbeInterface}, {@see UserAwareApiClientInterface}.
 *
 * Domain methods (getProjects, getTodos, ...) belong here because the data
 * shape is the same regardless of acquisition mechanism — HTTP, XML file,
 * CSV dump, etc. are implementation details of the concrete client.
 */
interface ApiClientInterface
{
    /**
     * Authenticate with the API.
     *
     * @throws AuthenticationFailedException if authentication fails; the
     *         transport exception (401, DNS, TLS, ...) is passed as `previous`
     */
    public function authenticate(): void;

    /**
     * Get the client type (rest or file)
     *
     * @return string 'rest' or 'file'
     */
    public function getType(): string;

    /**
     * Get the API name (basecamp2, github, gitlab, jira)
     *
     * @return string
     */
    public function getApiName(): string;

    /**
     * Get health information from the API
     * Returns raw API-specific health data including version, rate limits, etc.
     * This data will be normalized by HealthNormalizer
     *
     * @return array Raw health information from the API
     * @throws \Exception If health check fails
     */
    public function getHealthInfo(): array;

    /**
     * Get the canonical endpoint URI for this client.
     * REST clients return their base URL; file clients return a file:// URI.
     * Must be a valid URI per RFC 3986 (validated as `format: uri` in health schema).
     */
    public function getEndpoint(): string;

    /**
     * Get all projects from the source.
     *
     * @return array Array of project data (raw, not normalized)
     */
    public function getProjects(): array;

    /**
     * Get todos/issues for a specific project.
     *
     * @param string $projectId The project identifier
     * @return array Array of todo/issue data (raw, not normalized)
     */
    public function getTodos(string $projectId): array;

    /**
     * Get a specific todo/issue by ID.
     *
     * @param string $projectId The project identifier
     * @param string $todoId The todo/issue identifier
     * @return array|null Todo/issue data or null if not found
     */
    public function getTodo(string $projectId, string $todoId): ?array;
}
