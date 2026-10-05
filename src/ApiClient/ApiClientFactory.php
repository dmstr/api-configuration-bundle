<?php
// file generated with AI assistance: Claude Code - 2025-11-01 00:00:00

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\ApiClient;

use Dmstr\ApiConfiguration\Security\ConfigSecrets;
use Dmstr\ApiConfiguration\Service\ApiExtensionRegistry;

/**
 * Factory for creating API clients using extension registry
 */
class ApiClientFactory
{
    public function __construct(
        private readonly ApiExtensionRegistry $registry,
        private readonly ConfigSecrets $secrets,
    ) {
    }

    /**
     * Create an API client based on configuration
     *
     * @param string $apiName API name (basecamp2, github, gitlab, jira)
     * @param array $config Configuration array; encrypted secrets are decrypted here
     * @return ApiClientInterface
     * @throws \InvalidArgumentException If API name is not supported or config is invalid
     * @throws \Dmstr\ApiConfiguration\Security\SecretEncryptionException If a secret cannot be decrypted
     */
    public function create(string $apiName, array $config): ApiClientInterface
    {
        $extension = $this->registry->get($apiName);

        if ($extension === null) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Unsupported API name: %s. Available extensions: %s',
                    $apiName,
                    implode(', ', $this->registry->getNames())
                )
            );
        }

        return $extension->createClient($this->secrets->decrypt($config));
    }

    /**
     * Create API client from ApiConfiguration entity
     *
     * @param \Dmstr\ApiConfiguration\Entity\ApiConfiguration $apiConfiguration
     * @return ApiClientInterface
     */
    public function createFromEntity(\Dmstr\ApiConfiguration\Entity\ApiConfiguration $apiConfiguration): ApiClientInterface
    {
        $config = $apiConfiguration->getConfigJson();
        $config['_apiConfigurationId'] = (string) $apiConfiguration->getId();

        return $this->create(
            $apiConfiguration->getType(),
            $config
        );
    }
}
