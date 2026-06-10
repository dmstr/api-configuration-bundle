<?php

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\ApiClient;

/**
 * Marker interface for REST-based API clients (Basecamp 2/4, GitHub, GitLab).
 *
 * All domain methods (getProjects, getTodos, ...) live on the parent
 * ApiClientInterface — see source-acquisition unification (D1, D8).
 *
 * This marker is kept so concrete REST clients can opt into REST-specific
 * extensions (e.g., rate-limit reporting) in the future without affecting
 * file-based clients.
 */
interface RestApiClientInterface extends ApiClientInterface
{
}
