<?php
// file generated with AI assistance: Claude Code - 2026-10-28 07:54:45 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Tests\Http;

use Dmstr\ApiConfiguration\Tests\Kernel\TestKernel;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Without a permission voter in the application nobody holds an
 * `apiconfig:*` permission: every operation is denied, even to a user
 * with roles that the voter of the other variant maps to everything.
 */
final class NoVoterDeniesEverythingTest extends ApiTestCase
{
    use OperationRequests;

    protected static function kernelEnvironment(): string
    {
        return TestKernel::ENV_NO_VOTER;
    }

    #[DataProvider('allOperations')]
    public function testAdminIsDenied(string $operation): void
    {
        $response = $this->requestOperation($operation, ['admin']);

        self::assertSame(403, $response->getStatusCode(), (string) $response->getContent());
    }
}
