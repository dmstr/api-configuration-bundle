<?php
// file generated with AI assistance: Claude Code - 2026-10-28 07:56:49 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Tests\Http;

use Dmstr\ApiConfiguration\Tests\Fixtures\AuthorizableDemoExtension;
use Dmstr\ApiConfiguration\Tests\Kernel\TestKernel;
use Dmstr\ApiConfiguration\Tests\Kernel\TestPermissionVoter;
use Symfony\Component\Uid\Uuid;

/**
 * The browser path of authorize (`Accept: text/html`, answered with a 303
 * by AuthorizeRedirectListener before API Platform runs) checks the same
 * permission as the JSON path, before it looks up the configuration.
 */
final class AuthorizeBrowserPathSecurityTest extends ApiTestCase
{
    private const array HTML = ['Accept' => 'text/html'];

    /** The one test that runs against the kernel without a permission voter */
    private const string NO_VOTER_TEST = 'testWithoutVoterEvenAdminIsDenied';

    private static string $environment = TestKernel::ENV_WITH_VOTER;

    protected static function kernelEnvironment(): string
    {
        return self::$environment;
    }

    protected function setUp(): void
    {
        self::$environment = $this->name() === self::NO_VOTER_TEST
            ? TestKernel::ENV_NO_VOTER
            : TestKernel::ENV_WITH_VOTER;

        parent::setUp();
    }

    public function testAuthorizerIsRedirected(): void
    {
        $response = $this->requestAs('GET', $this->authorizeUri(), ['authorizer'], headers: self::HTML);

        self::assertSame(303, $response->getStatusCode(), (string) $response->getContent());
        self::assertStringStartsWith(AuthorizableDemoExtension::AUTHORIZE_ENDPOINT . '?', (string) $response->headers->get('Location'));
    }

    public function testReaderIsDenied(): void
    {
        $response = $this->requestAs('GET', $this->authorizeUri(), ['reader'], headers: self::HTML);

        self::assertSame(403, $response->getStatusCode());
        self::assertFalse($response->headers->has('Location'));
    }

    /**
     * No 404 for a missing id and no 409 for a type without authorization
     * flow before the permission check: the answer reveals nothing.
     */
    public function testUnmappedRoleIsDeniedWhetherOrNotTheIdExists(): void
    {
        $existing = $this->authorizeUri();
        $missing = self::COLLECTION . '/' . Uuid::v4()->toRfc4122() . '/authorize';
        $notAuthorizable = $this->itemUri($this->createApiConfiguration(), '/authorize');

        foreach ([$existing, $missing, $notAuthorizable] as $uri) {
            $response = $this->requestAs('GET', $uri, ['nobody'], headers: self::HTML);
            self::assertSame(403, $response->getStatusCode(), $uri);
        }
    }

    public function testAnonymousCallerMustAuthenticate(): void
    {
        $response = $this->requestAnonymous('GET', $this->authorizeUri(), headers: self::HTML);

        self::assertSame(401, $response->getStatusCode());
        self::assertFalse($response->headers->has('Location'));
    }

    public function testWithoutVoterEvenAdminIsDenied(): void
    {
        self::assertFalse(static::getContainer()->has(TestPermissionVoter::class));

        $response = $this->requestAs('GET', $this->authorizeUri(), ['admin'], headers: self::HTML);

        self::assertSame(403, $response->getStatusCode());
    }

    private function authorizeUri(): string
    {
        return $this->itemUri($this->createAuthorizableApiConfiguration(), '/authorize');
    }
}
