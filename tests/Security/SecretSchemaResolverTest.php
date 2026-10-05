<?php
// file generated with AI assistance: Claude Code - 2026-10-05 10:25:39 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Tests\Security;

use Dmstr\ApiConfiguration\Security\SecretSchemaResolver;
use Dmstr\ApiConfiguration\Service\ApiExtensionRegistry;
use Dmstr\ApiConfiguration\Tests\Fixtures\SecretsFixture;
use PHPUnit\Framework\TestCase;

final class SecretSchemaResolverTest extends TestCase
{
    public function testCollectsMarkedPropertiesIncludingRefsAndListItems(): void
    {
        $schema = json_decode(file_get_contents(__DIR__ . '/../Fixtures/demo.schema.json'), true);

        self::assertEqualsCanonicalizing(
            [['password'], ['token'], ['oauth', 'client_secret'], ['headers', '*', 'value']],
            SecretSchemaResolver::collectSecretPaths($schema),
        );
    }

    public function testCollectsFromCombinatorsAndMaps(): void
    {
        $schema = [
            'type' => 'object',
            'oneOf' => [
                ['properties' => ['password' => ['type' => 'string', 'writeOnly' => true]]],
                ['properties' => ['token' => ['type' => 'string', 'writeOnly' => true]]],
            ],
            'properties' => [
                'secret_headers' => [
                    'type' => 'object',
                    'additionalProperties' => ['type' => 'string', 'writeOnly' => true],
                ],
                'not_secret' => ['type' => 'string', 'writeOnly' => false],
            ],
        ];

        self::assertEqualsCanonicalizing(
            [['password'], ['token'], ['secret_headers', '*']],
            SecretSchemaResolver::collectSecretPaths($schema),
        );
    }

    public function testRecursiveRefTerminates(): void
    {
        $schema = [
            'properties' => ['node' => ['$ref' => '#/definitions/node']],
            'definitions' => [
                'node' => [
                    'properties' => [
                        'key' => ['type' => 'string', 'writeOnly' => true],
                        'child' => ['$ref' => '#/definitions/node'],
                    ],
                ],
            ],
        ];

        self::assertSame([['node', 'key']], SecretSchemaResolver::collectSecretPaths($schema));
    }

    public function testResolvesByProviderName(): void
    {
        $resolver = new SecretSchemaResolver(SecretsFixture::schemaRegistry(), new ApiExtensionRegistry());

        self::assertContains(['password'], $resolver->getSecretPaths('demo'));
    }

    public function testUnknownTypeFallsBackToAllSchemas(): void
    {
        $resolver = new SecretSchemaResolver(SecretsFixture::schemaRegistry(), new ApiExtensionRegistry());

        // Over-masking beats leaking when a type has no schema of its own
        self::assertContains(['password'], $resolver->getSecretPaths('unknown'));
        self::assertContains(['password'], $resolver->getSecretPaths(''));
    }
}
