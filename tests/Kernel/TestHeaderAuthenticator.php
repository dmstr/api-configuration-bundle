<?php
// file generated with AI assistance: Claude Code - 2026-10-28 07:35:37 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Tests\Kernel;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Authenticates test requests from plain headers, no credentials involved.
 *
 * - `X-Test-Roles: admin, reader` authenticates a user with these roles
 *   (comma-separated, trimmed, empty entries ignored).
 * - `X-Test-User: alice` sets the user identifier (default {@see self::DEFAULT_USER}).
 * - `X-Test-Auth: anonymous` skips authentication, whatever else is sent.
 *
 * A request with neither `X-Test-Roles` nor `X-Test-User` stays anonymous.
 * As entry point it answers 401 for anonymous requests that need a user.
 */
final class TestHeaderAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public const string HEADER_ROLES = 'X-Test-Roles';
    public const string HEADER_USER = 'X-Test-User';
    public const string HEADER_AUTH = 'X-Test-Auth';
    public const string ANONYMOUS = 'anonymous';
    public const string DEFAULT_USER = 'test-user';

    public function supports(Request $request): ?bool
    {
        if (strtolower(trim((string) $request->headers->get(self::HEADER_AUTH, ''))) === self::ANONYMOUS) {
            return false;
        }

        return $request->headers->has(self::HEADER_ROLES) || $request->headers->has(self::HEADER_USER);
    }

    public function authenticate(Request $request): Passport
    {
        $identifier = trim((string) $request->headers->get(self::HEADER_USER, ''));
        if ($identifier === '') {
            $identifier = self::DEFAULT_USER;
        }
        $roles = self::parseRoles((string) $request->headers->get(self::HEADER_ROLES, ''));

        return new SelfValidatingPassport(new UserBadge(
            $identifier,
            static fn (string $identifier): InMemoryUser => new InMemoryUser($identifier, null, $roles),
        ));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(['error' => $exception->getMessageKey()], Response::HTTP_UNAUTHORIZED);
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @return list<string>
     */
    public static function parseRoles(string $header): array
    {
        return array_values(array_unique(array_filter(
            array_map('trim', explode(',', $header)),
            static fn (string $role): bool => $role !== '',
        )));
    }
}
