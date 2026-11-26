<?php
// file generated with AI assistance: Claude Code - 2026-11-26 10:48:54 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Tests\State;

use Dmstr\ApiConfiguration\State\ApiConfigurationSchemaProvider;
use Dmstr\ApiConfiguration\Tests\Fixtures\SecretsFixture;
use Dmstr\OpenApiJsonSchema\Service\SchemaRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class ApiConfigurationSchemaProviderTest extends TestCase
{
    public function testWithoutRegisteredTypeTheUnifiedSchemaIsFalse(): void
    {
        // Issue #16: since openapi-json-schema-bundle 0.6.0 an empty registry
        // returns the boolean schema false, which the old `array` return type
        // turned into a TypeError
        $provider = new ApiConfigurationSchemaProvider(new SchemaRegistry(new ArrayAdapter(), new NullLogger()));

        self::assertFalse($provider->getUnifiedSchema());
    }

    public function testRegisteredTypesYieldTheUnifiedAnyOfSchema(): void
    {
        $registry = SecretsFixture::schemaRegistry();
        $provider = new ApiConfigurationSchemaProvider($registry);

        $schema = $provider->getUnifiedSchema();

        self::assertIsArray($schema);
        self::assertCount(1, $schema['anyOf']);
        self::assertSame($registry->getUnifiedSchema(), $schema);
    }
}
