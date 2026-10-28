<?php
// file generated with AI assistance: Claude Code - 2026-10-28 07:52:48 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Security;

/**
 * Permission attributes of the ApiConfiguration resource, checked with
 * `is_granted()` on every operation.
 *
 * The bundle grants none of them itself: they are decided by a permission
 * voter of the consuming application. Without such a voter every
 * operation is denied.
 *
 * Registered as a service tagged `za7_core.permission` (see
 * `config/services.yaml`), so an application can collect the attributes.
 */
final class ApiConfigurationPermissions
{
    /** Read configurations (credentials masked) and their health */
    public const string READ = 'apiconfig:read';

    /** Create, replace, update and delete configurations */
    public const string WRITE = 'apiconfig:write';

    /** Start the interactive authorization flow of a configuration */
    public const string AUTHORIZE = 'apiconfig:authorize';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::READ, self::WRITE, self::AUTHORIZE];
    }
}
