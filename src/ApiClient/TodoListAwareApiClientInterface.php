<?php
// file generated with AI assistance: Claude Code - 2026-10-05 12:10:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\ApiClient;

/**
 * Clients whose source organises todos in lists (Basecamp 2 concept).
 * Other sources do not implement this interface; callers check instanceof
 * before calling getTodoLists().
 */
interface TodoListAwareApiClientInterface extends ApiClientInterface
{
    /**
     * Get todo lists for a specific project.
     *
     * @param string $projectId The project identifier
     * @return array Array of todo list data
     */
    public function getTodoLists(string $projectId): array;
}
