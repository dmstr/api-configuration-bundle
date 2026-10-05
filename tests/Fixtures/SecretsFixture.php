<?php
// file generated with AI assistance: Claude Code - 2026-10-05 10:25:39 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Tests\Fixtures;

use Dmstr\ApiConfiguration\Security\ConfigSecrets;
use Dmstr\ApiConfiguration\Security\SecretSchemaResolver;
use Dmstr\ApiConfiguration\Service\ApiExtensionRegistry;
use Dmstr\ApiPlatformUtils\Service\CredentialEncryption;
use Dmstr\OpenApiJsonSchema\Interface\SchemaProviderInterface;
use Dmstr\OpenApiJsonSchema\Service\SchemaRegistry;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Real ConfigSecrets wired with the `demo` type schema
 * (tests/Fixtures/demo.schema.json) and a fixed key.
 */
final class SecretsFixture
{
    /** 32 raw bytes, as CredentialEncryption expects them */
    public const string KEY = '0123456789abcdef0123456789abcdef';

    public static function schemaRegistry(): SchemaRegistry
    {
        $registry = new SchemaRegistry(new ArrayAdapter(), new NullLogger());
        $registry->register(new class implements SchemaProviderInterface {
            public function getName(): string
            {
                return 'demo';
            }

            public function getSchemaPath(): string
            {
                return __DIR__ . '/demo.schema.json';
            }
        });

        return $registry;
    }

    /**
     * @param (\Closure(): CredentialEncryption)|null $encryption defaults to a working key
     */
    public static function configSecrets(?\Closure $encryption = null): ConfigSecrets
    {
        return new ConfigSecrets(
            new SecretSchemaResolver(self::schemaRegistry(), new ApiExtensionRegistry()),
            $encryption ?? static fn (): CredentialEncryption => new CredentialEncryption(self::KEY),
        );
    }

    public static function config(array $overrides = []): array
    {
        return array_replace([
            'type' => 'demo',
            'endpoint_type' => 'rest',
            'base_url' => 'https://api.example.com',
            'auth_type' => 'basic',
            'username' => 'alice',
            'password' => 's3cr3t-password',
        ], $overrides);
    }
}
