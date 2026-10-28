<?php
// file generated with AI assistance: Claude Code - 2026-10-28 07:35:37 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Tests\Fixtures;

use Dmstr\ApiConfiguration\ApiClient\ApiClientInterface;
use Dmstr\ApiConfiguration\Entity\ApiConfiguration;
use Dmstr\ApiConfiguration\Extension\ApiExtensionInterface;
use Dmstr\ApiConfiguration\Extension\AuthorizableExtensionInterface;
use Dmstr\OpenApiJsonSchema\Interface\SchemaProviderInterface;

/**
 * Extension for the `authorizable-demo` type
 * (tests/Fixtures/authorizable-demo.schema.json) with an interactive
 * authorization flow: the authorize operation answers with an authorize URL
 * (JSON) or a 303 redirect (`Accept: text/html`) instead of the 409 of
 * {@see DemoExtension}.
 *
 * Also the schema provider of its type, so the configuration validator
 * accepts `authorizable-demo` configurations.
 */
final class AuthorizableDemoExtension implements ApiExtensionInterface, AuthorizableExtensionInterface, SchemaProviderInterface
{
    public const string TYPE = 'authorizable-demo';
    public const string AUTHORIZE_ENDPOINT = 'https://auth.example.com/authorize';

    public function getName(): string
    {
        return self::TYPE;
    }

    public function getType(): string
    {
        return 'rest';
    }

    public function getSchemaPath(): string
    {
        return __DIR__ . '/authorizable-demo.schema.json';
    }

    public function createClient(array $config): ApiClientInterface
    {
        return new DemoClient($config);
    }

    public function buildAuthorizeUrl(ApiConfiguration $config, string $redirectUri, ?string $state = null): string
    {
        return self::AUTHORIZE_ENDPOINT . '?' . http_build_query([
            'response_type' => 'code',
            'client_id' => (string) ($config->getConfigJson()['client_id'] ?? ''),
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ]);
    }

    public function exchangeAuthorizationCode(ApiConfiguration $config, string $code, string $redirectUri): array
    {
        return ['expiresAt' => null, 'accountId' => null, 'tokenType' => 'Bearer'];
    }

    /**
     * A valid `configJson` of this type.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function config(array $overrides = []): array
    {
        return array_replace([
            'type' => self::TYPE,
            'endpoint_type' => 'rest',
            'base_url' => 'https://api.example.com',
            'client_id' => 'demo-client',
            'client_secret' => 'demo-client-secret',
        ], $overrides);
    }
}
