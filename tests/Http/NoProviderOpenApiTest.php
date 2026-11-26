<?php
// file generated with AI assistance: Claude Code - 2026-11-26 10:48:54 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Tests\Http;

use Dmstr\ApiConfiguration\Tests\Kernel\TestKernel;

/**
 * Without any configuration schema provider the OpenAPI document still builds
 * in debug mode, and documents `configJson` as accepting nothing (Issue #16).
 */
final class NoProviderOpenApiTest extends ApiTestCase
{
    protected static function kernelEnvironment(): string
    {
        return TestKernel::ENV_NO_PROVIDER;
    }

    public function testOpenApiDocumentDescribesConfigJsonAsAcceptingNothing(): void
    {
        // In debug mode the ErrorHandler turns "array offset on false" into an exception
        self::assertTrue(static::$kernel->isDebug());

        $response = $this->requestAs('GET', TestKernel::API_PREFIX . '/docs.jsonopenapi', [], headers: [
            'Accept' => 'application/vnd.openapi+json',
        ]);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $configJsonSchemas = [];
        foreach (self::json($response)['components']['schemas'] as $name => $schema) {
            if (isset($schema['properties']['configJson'])) {
                $configJsonSchemas[$name] = $schema['properties']['configJson'];
            }
        }

        self::assertNotEmpty($configJsonSchemas);
        foreach ($configJsonSchemas as $name => $configJson) {
            self::assertArrayNotHasKey('type', $configJson, $name);
            self::assertStringContainsString('No API configuration type is registered', $configJson['description'], $name);
            self::assertSame(
                [['not' => ['description' => 'Matches nothing: no configuration type is registered']]],
                $configJson['anyOf'],
                $name,
            );
        }
    }
}
