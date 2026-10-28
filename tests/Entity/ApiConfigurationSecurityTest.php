<?php
// file generated with AI assistance: Claude Code - 2026-10-05 10:25:39 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Tests\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Dmstr\ApiConfiguration\Entity\ApiConfiguration;
use Dmstr\ApiConfiguration\Security\ApiConfigurationPermissions;
use PHPUnit\Framework\TestCase;

/**
 * Every operation of the resource names its permission attribute
 * explicitly; reads are included because configJson holds upstream
 * credentials (Issue #8). The resource-level expression is only a fallback.
 */
final class ApiConfigurationSecurityTest extends TestCase
{
    public function testResourceFallbackRequiresReadPermission(): void
    {
        self::assertSame(self::expression(ApiConfigurationPermissions::READ), self::resource()->getSecurity());
    }

    public function testEveryOperationHasExactlyTheExpectedExplicitExpression(): void
    {
        $expected = [
            Get::class . ' ' => ApiConfigurationPermissions::READ,
            GetCollection::class . ' ' => ApiConfigurationPermissions::READ,
            Post::class . ' ' => ApiConfigurationPermissions::WRITE,
            Put::class . ' ' => ApiConfigurationPermissions::WRITE,
            Patch::class . ' ' => ApiConfigurationPermissions::WRITE,
            Delete::class . ' ' => ApiConfigurationPermissions::WRITE,
            Get::class . ' /api_configurations/{id}/health' => ApiConfigurationPermissions::READ,
            Get::class . ' /api_configurations/{id}/authorize' => ApiConfigurationPermissions::AUTHORIZE,
        ];

        $actual = [];
        foreach (self::resource()->getOperations() ?? [] as $operation) {
            self::assertNotNull($operation->getSecurity(), self::label($operation) . ' has no own security');
            $actual[self::label($operation)] = $operation->getSecurity();
        }

        self::assertSame(array_map(self::expression(...), $expected), $actual);
    }

    public static function resource(): ApiResource
    {
        $attributes = (new \ReflectionClass(ApiConfiguration::class))->getAttributes(ApiResource::class);
        self::assertCount(1, $attributes);

        return $attributes[0]->newInstance();
    }

    private static function label(Operation $operation): string
    {
        return $operation::class . ' ' . ($operation->getUriTemplate() ?? '');
    }

    private static function expression(string $permission): string
    {
        return sprintf("is_granted('%s')", $permission);
    }
}
