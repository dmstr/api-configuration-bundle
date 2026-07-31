<?php

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\ApiClient;

/**
 * Base interface for all API clients (REST + File).
 *
 * Domain methods (getProjects, getTodos, ...) belong here because the data
 * shape is the same regardless of acquisition mechanism — HTTP, XML file,
 * CSV dump, etc. are implementation details of the concrete client.
 */
interface ApiClientInterface
{
    /**
     * Authenticate with the API
     *
     * @return bool True if authentication successful
     */
    public function authenticate(): bool;

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
     * Get all customers (groups/orgs/companies) from the source.
     * "Customer" maps to: BC2 Groups, BC4 Companies, GitHub Orgs, GitLab Groups,
     * Jira Project Categories (or empty for file-based sources without grouping).
     *
     * @return array Array of customer data (raw, not normalized)
     */
    public function getCustomers(): array;

    /**
     * Get todo lists for a specific project (Basecamp 2 specific concept).
     * Other sources may return an empty array.
     *
     * @param string $projectId The project identifier
     * @return array Array of todo list data
     */
    public function getTodoLists(string $projectId): array;

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

    /**
     * Check if the source has been modified since a given timestamp.
     * Used for intelligent sync scheduling (skip when nothing changed).
     *
     * Contract:
     *  - Must be *cheap*: one request at most. It is called to avoid a more
     *    expensive call, so it must not page or fan out.
     *  - Must **fail open**: on any error, or whenever the implementation cannot
     *    tell, return `true`. A `false` suppresses the scan entirely, so a broken
     *    or incomplete probe would silently stop data from being refreshed.
     *  - Returning a constant `true` is a valid implementation for sources
     *    without a reliable account-wide change feed.
     *
     * @param \DateTimeInterface $since Timestamp to check against
     * @return bool True if source has (or may have) changes since the timestamp
     */
    public function hasChanges(\DateTimeInterface $since): bool;
}
