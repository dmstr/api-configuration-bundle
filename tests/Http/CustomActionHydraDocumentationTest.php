<?php
// file generated with AI assistance: Claude Code - 2026-11-26 15:49:29 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Tests\Http;

use Dmstr\ApiConfiguration\Tests\Kernel\TestKernel;

/**
 * `/api/docs.jsonld` announces `health` and `authorize` as custom actions
 * (`schema:Action`), which dmstr/api-platform-utils-bundle >= 0.8 only does
 * for operations marked with `extraProperties: ['custom_action' => true]`.
 * No CRUD operation is announced as an action.
 */
final class CustomActionHydraDocumentationTest extends ApiTestCase
{
    public function testHealthAndAuthorizeAreTheOnlyCustomActions(): void
    {
        $response = $this->requestAs('GET', TestKernel::API_PREFIX . '/docs.jsonld', [], headers: [
            'Accept' => 'application/ld+json',
        ]);
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $actions = [];
        $crudOperations = 0;
        foreach (self::operations(self::json($response)) as $operation) {
            if (\in_array('schema:Action', (array) ($operation['@type'] ?? []), true)) {
                $actions[$operation['hydra:title'] ?? ''] = $operation['hydra:uriTemplate'] ?? null;
            } else {
                ++$crudOperations;
            }
        }
        ksort($actions);

        self::assertSame([
            'api_configuration_authorize' => '/api/admin/api_configurations/{id}/authorize',
            'api_configuration_health' => '/api/admin/api_configurations/{id}/health',
        ], $actions);
        // The CRUD operations are documented, none of them as schema:Action
        self::assertGreaterThan(0, $crudOperations);
    }

    /**
     * Operations of the supported classes and of the entrypoint properties.
     * The test kernel disables `hydra_prefix`, so API Platform writes
     * `supportedOperation`; both spellings are accepted. A single operation
     * (the Entrypoint's own GET) is written as an object instead of a list.
     *
     * @param array<string, mixed> $documentation
     *
     * @return list<array<string, mixed>>
     */
    private static function operations(array $documentation): array
    {
        $operations = [];
        foreach ($documentation['supportedClass'] ?? $documentation['hydra:supportedClass'] ?? [] as $class) {
            array_push($operations, ...self::list($class['supportedOperation'] ?? $class['hydra:supportedOperation'] ?? []));
            foreach ($class['supportedProperty'] ?? $class['hydra:supportedProperty'] ?? [] as $property) {
                $carrier = $property['property'] ?? $property['hydra:property'] ?? [];
                array_push($operations, ...self::list($carrier['supportedOperation'] ?? $carrier['hydra:supportedOperation'] ?? []));
            }
        }

        return $operations;
    }

    /**
     * @param array<mixed> $operations a list of operations or a single one
     *
     * @return list<array<string, mixed>>
     */
    private static function list(array $operations): array
    {
        return array_is_list($operations) ? $operations : [$operations];
    }
}
