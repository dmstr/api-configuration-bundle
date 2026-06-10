<?php
// file generated with AI assistance: Claude Code - 2025-11-10 23:52:00

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Dmstr\ApiConfiguration\ApiClient\ApiClientFactory;
use Dmstr\ApiConfiguration\Entity\ApiConfiguration;
use Dmstr\ApiConfiguration\Normalizer\HealthNormalizer;
use Dmstr\ApiPlatformUtils\Service\UuidResolver;

/**
 * Provides health check information for an ApiConfiguration
 */
class ApiConfigurationHealthProvider implements ProviderInterface
{
    public function __construct(
        private readonly UuidResolver $uuidResolver,
        private readonly ApiClientFactory $clientFactory,
        private readonly HealthNormalizer $healthNormalizer
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

        // Measure response time
        $startTime = microtime(true);
        $client = null;

        try {
            // Create API client
            $client = $this->clientFactory->createFromEntity($apiConfiguration);

            // Get health info from client
            $rawHealthInfo = $client->getHealthInfo();

            // Calculate response time
            $responseTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

            // Normalize health info — endpoint comes from the client itself
            $normalizedHealth = $this->healthNormalizer->normalize(
                $rawHealthInfo,
                $client->getEndpoint(),
                $responseTime
            );

            // Return as stdClass for proper API Platform serialization
            $result = new \stdClass();
            $result->status = $normalizedHealth['status'];
            $result->authenticated = $normalizedHealth['authenticated'];
            $result->endpoint = $normalizedHealth['endpoint'];
            $result->responseTime = $normalizedHealth['responseTime'];

            if (isset($normalizedHealth['message'])) {
                $result->message = $normalizedHealth['message'];
            }
            if (isset($normalizedHealth['metadata'])) {
                $result->metadata = $normalizedHealth['metadata'];
            }
            if (isset($normalizedHealth['error'])) {
                $result->error = $normalizedHealth['error'];
            }

            return $result;
        } catch (\Exception $e) {
            $responseTime = (microtime(true) - $startTime) * 1000;

            // If client failed to instantiate, fall back to a URN identifying the
            // ApiConfiguration — still a valid URI per RFC 8141 / `format: uri`.
            $endpoint = $client?->getEndpoint()
                ?? sprintf('urn:za7:api-configuration:%s', $apiConfiguration->getId());

            $normalizedHealth = $this->healthNormalizer->normalize(
                [
                    'status' => 'error',
                    'authenticated' => false,
                    'message' => 'Health check failed: ' . $e->getMessage(),
                    'error' => [
                        'code' => 'HEALTH_CHECK_ERROR',
                        'message' => $e->getMessage(),
                    ],
                ],
                $endpoint,
                $responseTime
            );

            // Return as stdClass for proper API Platform serialization
            $result = new \stdClass();
            $result->status = $normalizedHealth['status'];
            $result->authenticated = $normalizedHealth['authenticated'];
            $result->endpoint = $normalizedHealth['endpoint'];
            $result->responseTime = $normalizedHealth['responseTime'];

            if (isset($normalizedHealth['message'])) {
                $result->message = $normalizedHealth['message'];
            }
            if (isset($normalizedHealth['metadata'])) {
                $result->metadata = $normalizedHealth['metadata'];
            }
            if (isset($normalizedHealth['error'])) {
                $result->error = $normalizedHealth['error'];
            }

            return $result;
        }
    }
}
