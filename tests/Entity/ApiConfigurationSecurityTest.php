<?php
// file generated with AI assistance: Claude Code - 2026-10-05 10:25:39 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Tests\Entity;

use ApiPlatform\Metadata\ApiResource;
use Dmstr\ApiConfiguration\Entity\ApiConfiguration;
use PHPUnit\Framework\TestCase;

/**
 * Issue #8: configJson holds upstream credentials, so every operation of the
 * resource — reads included — is restricted to ROLE_ADMIN.
 */
final class ApiConfigurationSecurityTest extends TestCase
{
    private const string ADMIN = "is_granted('ROLE_ADMIN')";

    public function testEveryOperationRequiresAdmin(): void
    {
        $attributes = (new \ReflectionClass(ApiConfiguration::class))->getAttributes(ApiResource::class);
        self::assertCount(1, $attributes);

        /** @var ApiResource $resource */
        $resource = $attributes[0]->newInstance();
        self::assertSame(self::ADMIN, $resource->getSecurity());

        foreach ($resource->getOperations() ?? [] as $operation) {
            // An operation without its own expression inherits the resource's
            self::assertContains(
                $operation->getSecurity(),
                [null, self::ADMIN],
                sprintf('%s %s', $operation->getMethod(), $operation->getUriTemplate() ?? $operation::class),
            );
        }
    }
}
