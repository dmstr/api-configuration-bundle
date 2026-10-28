<?php
// file generated with AI assistance: Claude Code - 2026-10-28 07:53:47 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Tests\Security;

use Dmstr\ApiConfiguration\Security\ApiConfigurationPermissions;
use Dmstr\ApiConfiguration\Tests\Entity\ApiConfigurationSecurityTest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * The bundle registers every permission attribute its operations check as a
 * `za7_core.permission` tag, without depending on za7-core: the tags are
 * read from the bundle's own `config/services.yaml`.
 */
final class PermissionRegistrationTest extends TestCase
{
    private const string TAG = 'za7_core.permission';
    private const string PERMISSION_PATTERN = '/^[a-z][a-z0-9]*:[a-z][a-z0-9_]*$/';

    public function testTaggedPermissionsAreWellFormed(): void
    {
        $permissions = self::taggedPermissions();

        self::assertNotEmpty($permissions);
        foreach ($permissions as $permission) {
            self::assertMatchesRegularExpression(self::PERMISSION_PATTERN, $permission);
        }
        self::assertSame(array_values(array_unique($permissions)), $permissions, 'a permission is tagged twice');
    }

    public function testTaggedPermissionsAreExactlyTheCheckedAttributes(): void
    {
        $tagged = self::taggedPermissions();
        $checked = self::checkedAttributes();
        sort($tagged);
        sort($checked);

        self::assertSame($checked, $tagged);
    }

    public function testConstantsMatchTheTags(): void
    {
        $tagged = self::taggedPermissions();
        $constants = ApiConfigurationPermissions::all();
        sort($tagged);
        sort($constants);

        self::assertSame($constants, $tagged);
    }

    /**
     * @return list<string>
     */
    private static function taggedPermissions(): array
    {
        $container = new ContainerBuilder();
        (new YamlFileLoader($container, new FileLocator(\dirname(__DIR__, 2) . '/config')))->load('services.yaml');

        $permissions = [];
        foreach ($container->findTaggedServiceIds(self::TAG) as $tags) {
            foreach ($tags as $attributes) {
                self::assertArrayHasKey('permission', $attributes);
                $permissions[] = $attributes['permission'];
            }
        }

        return $permissions;
    }

    /**
     * Attributes of every `is_granted('…')` in the resource and operation
     * security expressions.
     *
     * @return list<string>
     */
    private static function checkedAttributes(): array
    {
        $resource = ApiConfigurationSecurityTest::resource();
        $expressions = [$resource->getSecurity()];
        foreach ($resource->getOperations() ?? [] as $operation) {
            $expressions[] = $operation->getSecurity();
        }

        $attributes = [];
        foreach (array_filter($expressions) as $expression) {
            preg_match_all("/is_granted\\(\\s*'([^']+)'/", $expression, $matches);
            array_push($attributes, ...$matches[1]);
        }

        return array_values(array_unique($attributes));
    }
}
