<?php
// file generated with AI assistance: Claude Code - 2026-10-05 12:10:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Tests\Fixtures;

use Dmstr\ApiConfiguration\ApiClient\ApiClientInterface;

/**
 * Minimal client: implements only the slim base interface, none of the
 * optional capabilities.
 */
final class DemoClient implements ApiClientInterface
{
    public function __construct(private readonly array $config)
    {
    }

    public function authenticate(): void
    {
    }

    public function getType(): string
    {
        return 'rest';
    }

    public function getApiName(): string
    {
        return 'demo';
    }

    public function getHealthInfo(): array
    {
        return ['status' => 'ok', 'authenticated' => true, 'metadata' => ['apiName' => 'demo']];
    }

    public function getEndpoint(): string
    {
        return $this->config['base_url'];
    }

    public function getProjects(): array
    {
        return [];
    }

    public function getTodos(string $projectId): array
    {
        return [];
    }

    public function getTodo(string $projectId, string $todoId): ?array
    {
        return null;
    }
}
