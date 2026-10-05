<?php
// file generated with AI assistance: Claude Code - 2026-10-05 12:10:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\ApiClient;

use Dmstr\ApiConfiguration\Extension\ApiExtensionInterface;
use Dmstr\ApiConfiguration\Schema\JsonSchemaFile;
use Dmstr\ApiConfiguration\Security\ConfigSecrets;
use Dmstr\ApiConfiguration\Service\ApiExtensionRegistry;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

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
     * The configuration is validated against the extension's schema
     * ({@see ApiExtensionInterface::getSchemaPath()}) before the client is
     * built, so extensions need no required-field checks of their own. Keys
     * starting with `_` (e.g. `_apiConfigurationId`) are internal and not
     * validated.
     *
     * @param string $apiName API name (basecamp2, github, gitlab, jira)
     * @param array $config Configuration array; encrypted secrets are decrypted here
     * @return ApiClientInterface
     * @throws \InvalidArgumentException If API name is not supported
     * @throws InvalidConfigurationException If config does not match the extension's schema
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

        $config = $this->secrets->decrypt($config);
        $this->validate($extension, $config);

        return $extension->createClient($config);
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

    /**
     * @throws InvalidConfigurationException
     */
    private function validate(ApiExtensionInterface $extension, array $config): void
    {
        $public = array_filter(
            $config,
            static fn (int|string $key): bool => !str_starts_with((string) $key, '_'),
            ARRAY_FILTER_USE_KEY,
        );
        $schema = json_decode(json_encode(JsonSchemaFile::load($extension->getSchemaPath())));

        $result = (new Validator())->validate(json_decode(json_encode((object) $public)), $schema);
        if (!$result->isValid()) {
            throw new InvalidConfigurationException(
                $extension->getName(),
                (new ErrorFormatter())->format($result->error(), true),
            );
        }
    }
}
