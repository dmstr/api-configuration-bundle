<?php
// file generated with AI assistance: Claude Code - 2026-10-05 12:10:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Dmstr\ApiConfiguration\Entity\ApiConfiguration;
use Dmstr\ApiConfiguration\Health\ApiConfigurationHealthChecker;
use Dmstr\ApiPlatformUtils\Service\UuidResolver;

/**
 * Provides health check information for an ApiConfiguration
 */
class ApiConfigurationHealthProvider implements ProviderInterface
{
    public function __construct(
        private readonly UuidResolver $uuidResolver,
        private readonly ApiConfigurationHealthChecker $healthChecker,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        // Get the ApiConfiguration by ID (supports partial UUID matching)
        $id = $uriVariables['id'] ?? null;

        if ($id === null) {
            throw new \InvalidArgumentException('Missing ID parameter');
        }

        // Convert Uuid object to string if needed
        if ($id instanceof \Symfony\Component\Uid\Uuid) {
            $id = $id->toRfc4122();
        }

        $apiConfiguration = $this->uuidResolver->findByPartialUuid(ApiConfiguration::class, $id);

        if ($apiConfiguration === null) {
            throw new \RuntimeException(sprintf('ApiConfiguration with ID "%s" not found', $id));
        }

        // stdClass: API Platform serializes it as the operation's output.
        // Nested arrays keep their keys thanks to `api_sub_level` on the
        // operation (see ApiConfiguration).
        return (object) $this->healthChecker->check($apiConfiguration);
    }
}
