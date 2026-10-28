<?php
// file generated with AI assistance: Claude Code - 2026-10-28 07:35:37 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Tests\Http;

use Dmstr\ApiConfiguration\Tests\Kernel\TestKernel;
use Dmstr\ApiConfiguration\Tests\Kernel\TestPermissionVoter;

/**
 * The kernel variant without any custom voter boots and serves requests.
 */
final class NoVoterKernelSmokeTest extends ApiTestCase
{
    protected static function kernelEnvironment(): string
    {
        return TestKernel::ENV_NO_VOTER;
    }

    public function testNoPermissionVoterIsRegistered(): void
    {
        self::assertFalse(static::getContainer()->has(TestPermissionVoter::class));
    }

    public function testCollectionStillRequiresAuthentication(): void
    {
        self::assertSame(401, $this->requestAnonymous('GET', self::COLLECTION)->getStatusCode());
    }
}
