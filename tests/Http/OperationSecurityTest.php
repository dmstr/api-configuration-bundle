<?php
// file generated with AI assistance: Claude Code - 2026-10-28 07:54:45 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Tests\Http;

use Dmstr\ApiConfiguration\Security\ConfigSecrets;
use Dmstr\ApiConfiguration\Tests\Fixtures\AuthorizableDemoExtension;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Each operation is decided by its own permission attribute, through API
 * Platform on the JSON path, with the permission voter of the test kernel:
 * `admin` holds every permission, `reader` only read, `authorizer` only
 * authorize, `nobody` none.
 */
final class OperationSecurityTest extends ApiTestCase
{
    use OperationRequests;

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function grantedCases(): iterable
    {
        foreach (array_keys(self::SUCCESS_STATUS) as $operation) {
            yield "admin $operation" => [$operation, 'admin'];
        }
        foreach ([self::OP_GET, self::OP_COLLECTION, self::OP_HEALTH] as $operation) {
            yield "reader $operation" => [$operation, 'reader'];
        }
        yield 'authorizer authorize' => [self::OP_AUTHORIZE, 'authorizer'];
    }

    #[DataProvider('grantedCases')]
    public function testGranted(string $operation, string $role): void
    {
        $response = $this->requestOperation($operation, [$role]);

        self::assertSame(self::SUCCESS_STATUS[$operation], $response->getStatusCode(), (string) $response->getContent());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function deniedCases(): iterable
    {
        foreach ([self::OP_POST, self::OP_PUT, self::OP_PATCH, self::OP_DELETE, self::OP_AUTHORIZE] as $operation) {
            yield "reader $operation" => [$operation, 'reader'];
        }
        // The explicit per-operation attribute: authorize does not imply read
        foreach ([self::OP_GET, self::OP_COLLECTION, self::OP_HEALTH, self::OP_POST, self::OP_PUT, self::OP_PATCH, self::OP_DELETE] as $operation) {
            yield "authorizer $operation" => [$operation, 'authorizer'];
        }
    }

    #[DataProvider('deniedCases')]
    public function testDenied(string $operation, string $role): void
    {
        $response = $this->requestOperation($operation, [$role]);

        self::assertSame(403, $response->getStatusCode(), (string) $response->getContent());
    }

    #[DataProvider('allOperations')]
    public function testUnmappedRoleIsDeniedEverything(string $operation): void
    {
        self::assertSame(403, $this->requestOperation($operation, ['nobody'])->getStatusCode());
    }

    /**
     * The expressions do not use `object`, so API Platform checks them
     * before reading the item: a denied caller cannot probe ids by 404.
     *
     * @return iterable<string, array{string}>
     */
    public static function itemOperations(): iterable
    {
        foreach (self::ITEM_OPERATIONS as $operation) {
            yield $operation => [$operation];
        }
    }

    #[DataProvider('itemOperations')]
    public function testDenialDoesNotDependOnTheIdExisting(string $operation): void
    {
        $existing = $this->requestOperation($operation, ['nobody'], existing: true);
        $missing = $this->requestOperation($operation, ['nobody'], existing: false);

        self::assertSame(403, $existing->getStatusCode());
        self::assertSame(403, $missing->getStatusCode(), (string) $missing->getContent());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function jsonAcceptHeaders(): iterable
    {
        yield 'any' => ['*/*'];
        yield 'json-ld' => ['application/ld+json'];
        yield 'json with text fallback' => ['application/json, text/plain;q=0.5'];
    }

    #[DataProvider('jsonAcceptHeaders')]
    public function testAuthorizeOnTheJsonPathObeysTheOperationSecurity(string $accept): void
    {
        $headers = ['Accept' => $accept];

        $denied = $this->requestOperation(self::OP_AUTHORIZE, ['reader'], headers: $headers);
        self::assertSame(403, $denied->getStatusCode());

        $granted = $this->requestOperation(self::OP_AUTHORIZE, ['authorizer'], headers: $headers);
        self::assertSame(200, $granted->getStatusCode(), (string) $granted->getContent());
        self::assertStringStartsWith(AuthorizableDemoExtension::AUTHORIZE_ENDPOINT . '?', self::json($granted)['authorizeUrl']);
    }

    public function testReaderSeesCredentialsMasked(): void
    {
        $configuration = $this->createApiConfiguration();

        $item = $this->requestAs('GET', $this->itemUri($configuration), ['reader']);
        self::assertSame(200, $item->getStatusCode());
        self::assertSame(ConfigSecrets::MASK, self::json($item)['configJson']['password']);
        self::assertSame('alice', self::json($item)['configJson']['username']);

        $collection = $this->requestAs('GET', self::COLLECTION, ['reader']);
        self::assertSame(200, $collection->getStatusCode());
        $members = self::json($collection)['member'] ?? self::json($collection)['hydra:member'];
        self::assertSame(ConfigSecrets::MASK, $members[0]['configJson']['password']);
        self::assertStringNotContainsString('s3cr3t-password', (string) $collection->getContent());
    }

    /**
     * The `hydra:operation` list of item output (added by
     * dmstr/api-platform-utils-bundle's AddHydraOperationsSubscriber, not by
     * API Platform) is filtered by the operations' security expressions:
     * a reader is offered no write and no authorize operation. Collection
     * output carries no such list here (the subscriber only recognises the
     * `hydra:Collection` type, API Platform 4 emits `Collection`), and the
     * API documentation (`hydra:supportedOperation`) is not filtered.
     */
    public function testReaderIsOfferedOnlyReadOperations(): void
    {
        $configuration = $this->createApiConfiguration();
        $item = $this->itemUri($configuration);

        self::assertSame(
            [['GET', $item], ['GET', $item . '/health']],
            $this->offeredOperations($item, 'reader'),
        );
    }

    public function testAdminIsOfferedEveryOperation(): void
    {
        $configuration = $this->createApiConfiguration();
        $item = $this->itemUri($configuration);

        $offered = $this->offeredOperations($item, 'admin');
        foreach ([['PUT', $item], ['PATCH', $item], ['DELETE', $item], ['GET', $item . '/authorize']] as $operation) {
            self::assertContains($operation, $offered);
        }
    }

    /**
     * @return list<array{string, string}> method and URI of each offered operation
     */
    private function offeredOperations(string $uri, string $role): array
    {
        $response = $this->requestAs('GET', $uri, [$role]);
        self::assertSame(200, $response->getStatusCode());

        return array_map(
            static fn (array $operation): array => [$operation['hydra:method'], $operation['@id']],
            self::json($response)['hydra:operation'] ?? [],
        );
    }
}
