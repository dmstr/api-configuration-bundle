<?php
// file generated with AI assistance: Claude Code - 2026-10-28 07:35:37 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Tests\Http;

use Dmstr\ApiConfiguration\Security\ConfigSecrets;
use Dmstr\ApiConfiguration\Tests\Fixtures\AuthorizableDemoExtension;
use Dmstr\ApiConfiguration\Tests\Kernel\TestKernel;
use Dmstr\ApiConfiguration\Tests\Kernel\TestPermissionVoter;

/**
 * Smoke test of the HTTP test infrastructure against the current
 * behaviour of the resource.
 */
final class CurrentBehaviourSmokeTest extends ApiTestCase
{
    /**
     * The role the resource's security expression requires today. Replaced
     * by permission attributes in the next change, together with this test.
     */
    private const string CURRENT_REQUIRED_ROLE = 'ROLE_ADMIN';

    public function testCollectionIsReadableWithTheRequiredRole(): void
    {
        $this->createApiConfiguration();

        $response = $this->requestAs('GET', self::COLLECTION, [self::CURRENT_REQUIRED_ROLE]);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(1, self::json($response)['totalItems']);
    }

    public function testCollectionIsForbiddenForAnotherRole(): void
    {
        $response = $this->requestAs('GET', self::COLLECTION, ['reader']);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testCollectionRequiresAuthentication(): void
    {
        self::assertSame(401, $this->requestAnonymous('GET', self::COLLECTION)->getStatusCode());
        // No test header at all is anonymous too
        self::assertSame(401, $this->request('GET', self::COLLECTION)->getStatusCode());
    }

    public function testItemMasksSecrets(): void
    {
        $configuration = $this->createApiConfiguration();

        $response = $this->requestAs('GET', $this->itemUri($configuration), [self::CURRENT_REQUIRED_ROLE]);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $config = self::json($response)['configJson'];
        self::assertSame(ConfigSecrets::MASK, $config['password']);
        self::assertSame('alice', $config['username']);
    }

    public function testAuthorizeRedirectsBrowsersAndAnswersJsonClients(): void
    {
        $configuration = $this->createAuthorizableApiConfiguration();
        $uri = $this->itemUri($configuration, '/authorize');

        $browser = $this->requestAs('GET', $uri, [self::CURRENT_REQUIRED_ROLE], headers: ['Accept' => 'text/html']);
        self::assertSame(303, $browser->getStatusCode());
        self::assertStringStartsWith(AuthorizableDemoExtension::AUTHORIZE_ENDPOINT . '?', (string) $browser->headers->get('Location'));

        $json = $this->requestAs('GET', $uri, [self::CURRENT_REQUIRED_ROLE]);
        self::assertSame(200, $json->getStatusCode(), (string) $json->getContent());
        $body = self::json($json);
        self::assertStringStartsWith(AuthorizableDemoExtension::AUTHORIZE_ENDPOINT . '?', $body['authorizeUrl']);
        self::assertSame(TestKernel::OAUTH_REDIRECT_URI, $body['redirectUri']);
        self::assertSame($configuration->getId()->toRfc4122(), $body['state']);
    }

    public function testAuthorizeConflictsForATypeWithoutAuthorizationFlow(): void
    {
        $configuration = $this->createApiConfiguration();

        $response = $this->requestAs('GET', $this->itemUri($configuration, '/authorize'), [self::CURRENT_REQUIRED_ROLE]);

        self::assertSame(409, $response->getStatusCode());
    }

    public function testPermissionVoterIsRegistered(): void
    {
        self::assertTrue(static::getContainer()->has(TestPermissionVoter::class));
    }
}
