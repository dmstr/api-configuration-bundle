<?php
// file generated with AI assistance: Claude Code - 2026-10-05 12:10:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Health;

use Dmstr\ApiConfiguration\ApiClient\ApiClientFactory;
use Dmstr\ApiConfiguration\ApiConfigurationBundle;
use Dmstr\ApiConfiguration\Entity\ApiConfiguration;
use Dmstr\ApiConfiguration\Normalizer\HealthNormalizer;
use Dmstr\ApiConfiguration\Security\ConfigSecrets;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Runs the health check of an ApiConfiguration and returns the normalized
 * result. Shared by the health route, the health command and dashboards.
 *
 * Uses the {@see HealthProbeInterface} registered for the type first and
 * falls back to the API client (`ApiClientInterface::getHealthInfo()`).
 * A failing check is a result with `status: error`, never an exception.
 */
final class ApiConfigurationHealthChecker
{
    /** @var array<string, HealthProbeInterface> */
    private array $probes = [];

    /**
     * @param iterable<HealthProbeInterface> $probes
     */
    public function __construct(
        private readonly ApiClientFactory $clientFactory,
        private readonly HealthNormalizer $healthNormalizer,
        private readonly ConfigSecrets $secrets,
        #[AutowireIterator(ApiConfigurationBundle::HEALTH_PROBE_TAG)]
        iterable $probes = [],
    ) {
        foreach ($probes as $probe) {
            $this->probes[$probe->getName()] = $probe;
        }
    }

    /**
     * @return array normalized health result, see health.json
     */
    public function check(ApiConfiguration $apiConfiguration): array
    {
        $startTime = microtime(true);
        $endpoint = null;

        try {
            $probe = $this->probes[$apiConfiguration->getType()] ?? null;
            if ($probe !== null) {
                $config = $this->secrets->decrypt($apiConfiguration->getConfigJson());
                $endpoint = $probe->getEndpoint($config);
                $rawHealthInfo = $probe->probe($config);
            } else {
                $client = $this->clientFactory->createFromEntity($apiConfiguration);
                $endpoint = $client->getEndpoint();
                $rawHealthInfo = $client->getHealthInfo();
            }

            return $this->healthNormalizer->normalize($rawHealthInfo, $endpoint, $this->elapsedMs($startTime));
        } catch (\Exception $e) {
            // Without an endpoint (client/probe failed early) fall back to a
            // URN identifying the ApiConfiguration — a valid URI per RFC 8141.
            return $this->healthNormalizer->normalize(
                [
                    'status' => 'error',
                    'authenticated' => false,
                    'message' => 'Health check failed: ' . $e->getMessage(),
                    'error' => [
                        'code' => 'HEALTH_CHECK_ERROR',
                        'message' => $e->getMessage(),
                    ],
                ],
                $endpoint ?? sprintf('urn:za7:api-configuration:%s', $apiConfiguration->getId()),
                $this->elapsedMs($startTime),
            );
        }
    }

    private function elapsedMs(float $startTime): float
    {
        return (microtime(true) - $startTime) * 1000;
    }
}
