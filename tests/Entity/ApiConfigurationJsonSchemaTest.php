<?php
// file generated with AI assistance: Claude Code - 2026-11-26 10:48:54 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Tests\Entity;

use Dmstr\ApiConfiguration\Entity\ApiConfiguration;
use Dmstr\ApiConfiguration\Tests\Fixtures\SecretsFixture;
use Dmstr\OpenApiJsonSchema\OpenApi\JsonFieldSchemaDecorator;
use Dmstr\OpenApiJsonSchema\Service\SchemaRegistry;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * The OpenAPI schema of the `config` field, as {@see ApiConfiguration::getJsonSchema()}
 * hands it to {@see JsonFieldSchemaDecorator}.
 */
final class ApiConfigurationJsonSchemaTest extends TestCase
{
    protected function tearDown(): void
    {
        // The registry is static global state shared with every other test
        self::setGlobalSchemaRegistry(null);

        parent::tearDown();
    }

    public function testWithoutRegisteredTypeTheConfigSchemaIsAnAnyOfThatRejects(): void
    {
        // Issue #16: the registry returns the boolean schema false; before the
        // fix `anyOf` came out as null (array offset on false)
        self::setGlobalSchemaRegistry(new SchemaRegistry(new ArrayAdapter(), new NullLogger()));

        $schema = ApiConfiguration::getJsonSchema('config');

        self::assertIsArray($schema);
        self::assertSame(['description', 'anyOf'], array_keys($schema));
        self::assertIsString($schema['description']);
        self::assertStringContainsString('No API configuration type is registered', $schema['description']);
        self::assertIsArray($schema['anyOf']);
        self::assertNotEmpty($schema['anyOf']);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function anyValue(): iterable
    {
        yield 'configuration object' => [(object) SecretsFixture::config()];
        yield 'empty object' => [new \stdClass()];
        yield 'string' => ['demo'];
        yield 'null' => [null];
    }

    #[DataProvider('anyValue')]
    public function testWithoutRegisteredTypeTheConfigSchemaAcceptsNothing(mixed $value): void
    {
        self::setGlobalSchemaRegistry(new SchemaRegistry(new ArrayAdapter(), new NullLogger()));

        $schema = json_decode(json_encode(ApiConfiguration::getJsonSchema('config'), JSON_THROW_ON_ERROR));

        self::assertFalse((new Validator())->validate($value, $schema)->isValid());
    }

    public function testRegisteredTypesKeepTheUnifiedAnyOfSchema(): void
    {
        $registry = SecretsFixture::schemaRegistry();
        self::setGlobalSchemaRegistry($registry);
        $unified = $registry->getUnifiedSchema();

        $schema = ApiConfiguration::getJsonSchema('config');

        // Same output as before Issue #16
        self::assertSame(
            json_encode(['description' => $unified['description'], 'anyOf' => $unified['anyOf']], JSON_THROW_ON_ERROR),
            json_encode($schema, JSON_THROW_ON_ERROR),
        );
        self::assertCount(1, $schema['anyOf']);
        self::assertSame('demo', $schema['anyOf'][0]['properties']['type']['const']);
    }

    public function testWithoutRegistryTheDecoratorFallsBackToTheSchemaFile(): void
    {
        // A kernel booted by an earlier test may have set the registry
        self::setGlobalSchemaRegistry(null);

        self::assertNull(ApiConfiguration::getJsonSchema('config'));
    }

    private static function setGlobalSchemaRegistry(?SchemaRegistry $registry): void
    {
        (new \ReflectionProperty(JsonFieldSchemaDecorator::class, 'globalSchemaRegistry'))->setValue(null, $registry);
    }
}
