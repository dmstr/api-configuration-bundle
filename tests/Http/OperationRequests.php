<?php
// file generated with AI assistance: Claude Code - 2026-10-28 07:54:45 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Tests\Http;

use Dmstr\ApiConfiguration\Tests\Fixtures\SecretsFixture;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

/**
 * Sends a request for each of the eight operations of the ApiConfiguration
 * resource, by name. For use in {@see ApiTestCase} subclasses.
 */
trait OperationRequests
{
    public const string OP_GET = 'get';
    public const string OP_COLLECTION = 'collection';
    public const string OP_HEALTH = 'health';
    public const string OP_POST = 'post';
    public const string OP_PUT = 'put';
    public const string OP_PATCH = 'patch';
    public const string OP_DELETE = 'delete';
    public const string OP_AUTHORIZE = 'authorize';

    /** Status of each operation when it is granted, on the JSON path */
    public const array SUCCESS_STATUS = [
        self::OP_GET => 200,
        self::OP_COLLECTION => 200,
        self::OP_HEALTH => 200,
        self::OP_POST => 201,
        self::OP_PUT => 200,
        self::OP_PATCH => 200,
        self::OP_DELETE => 204,
        self::OP_AUTHORIZE => 200,
    ];

    /** Operations with an `{id}` in the URI */
    public const array ITEM_OPERATIONS = [
        self::OP_GET,
        self::OP_HEALTH,
        self::OP_PUT,
        self::OP_PATCH,
        self::OP_DELETE,
        self::OP_AUTHORIZE,
    ];

    /**
     * Requests an operation as a user with the given roles. Item operations
     * address a freshly stored configuration (an authorizable one for
     * authorize), or a random id that does not exist.
     *
     * @param list<string> $roles
     * @param array<string, string> $headers
     */
    protected function requestOperation(
        string $operation,
        array $roles,
        bool $existing = true,
        array $headers = [],
    ): Response {
        $id = Uuid::v4()->toRfc4122();
        if ($existing && \in_array($operation, self::ITEM_OPERATIONS, true)) {
            $configuration = $operation === self::OP_AUTHORIZE
                ? $this->createAuthorizableApiConfiguration()
                : $this->createApiConfiguration();
            $id = $configuration->getId()->toRfc4122();
        }
        $item = self::COLLECTION . '/' . $id;

        return match ($operation) {
            self::OP_GET => $this->requestAs('GET', $item, $roles, headers: $headers),
            self::OP_COLLECTION => $this->requestAs('GET', self::COLLECTION, $roles, headers: $headers),
            self::OP_HEALTH => $this->requestAs('GET', $item . '/health', $roles, headers: $headers),
            self::OP_POST => $this->requestAs('POST', self::COLLECTION, $roles, self::validBody('Created'), $headers),
            self::OP_PUT => $this->requestAs('PUT', $item, $roles, self::validBody('Replaced'), $headers),
            self::OP_PATCH => $this->requestAs('PATCH', $item, $roles, ['name' => 'Renamed'], $headers),
            self::OP_DELETE => $this->requestAs('DELETE', $item, $roles, headers: $headers),
            self::OP_AUTHORIZE => $this->requestAs('GET', $item . '/authorize', $roles, headers: $headers),
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected static function validBody(string $name): array
    {
        return ['name' => $name, 'configJson' => SecretsFixture::config(), 'active' => true];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function allOperations(): iterable
    {
        foreach (array_keys(self::SUCCESS_STATUS) as $operation) {
            yield $operation => [$operation];
        }
    }
}
