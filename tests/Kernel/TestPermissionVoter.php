<?php
// file generated with AI assistance: Claude Code - 2026-10-28 07:35:37 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Tests\Kernel;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Example permission voter, as an application would provide it: grants
 * `apiconfig:*` attributes from a fixed role → permissions map
 * ({@see self::PERMISSIONS}).
 *
 * Votes only on attributes starting with {@see self::PREFIX} and abstains on
 * everything else. Accepts any subject (including null) and never throws.
 */
final class TestPermissionVoter implements VoterInterface
{
    public const string PREFIX = 'apiconfig:';

    public const string READ = 'apiconfig:read';
    public const string WRITE = 'apiconfig:write';
    public const string AUTHORIZE = 'apiconfig:authorize';

    /** Role → granted permissions */
    public const array PERMISSIONS = [
        'admin' => [self::READ, self::WRITE, self::AUTHORIZE],
        'reader' => [self::READ],
        'authorizer' => [self::AUTHORIZE],
    ];

    public function vote(TokenInterface $token, mixed $subject, array $attributes, ?Vote $vote = null): int
    {
        try {
            $result = self::ACCESS_ABSTAIN;
            $granted = self::grantedPermissions($token->getRoleNames());

            foreach ($attributes as $attribute) {
                if (!is_string($attribute) || !str_starts_with($attribute, self::PREFIX)) {
                    continue;
                }
                if (in_array($attribute, $granted, true)) {
                    return self::ACCESS_GRANTED;
                }
                $result = self::ACCESS_DENIED;
            }

            return $result;
        } catch (\Throwable) {
            return self::ACCESS_ABSTAIN;
        }
    }

    /**
     * @param list<string> $roles
     * @return list<string>
     */
    public static function grantedPermissions(array $roles): array
    {
        $granted = [];
        foreach ($roles as $role) {
            array_push($granted, ...(self::PERMISSIONS[$role] ?? []));
        }

        return array_values(array_unique($granted));
    }
}
