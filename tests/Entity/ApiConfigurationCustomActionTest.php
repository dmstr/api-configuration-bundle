<?php
// file generated with AI assistance: Claude Code - 2026-11-26 15:49:29 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Tests\Entity;

use ApiPlatform\Metadata\Operation;
use PHPUnit\Framework\TestCase;

/**
 * Exactly the `health` and `authorize` operations carry the custom action
 * marker read by dmstr/api-platform-utils-bundle >= 0.8. The marker sits on
 * the operations, never on the resource, because API Platform merges the
 * resource's `extraProperties` into every operation.
 */
final class ApiConfigurationCustomActionTest extends TestCase
{
    private const array CUSTOM_ACTIONS = ['api_configuration_health', 'api_configuration_authorize'];

    public function testOnlyHealthAndAuthorizeAreMarkedAsCustomActions(): void
    {
        $marked = [];
        foreach (ApiConfigurationSecurityTest::resource()->getOperations() ?? [] as $operation) {
            $extraProperties = $operation->getExtraProperties() ?? [];
            if (\in_array($operation->getName(), self::CUSTOM_ACTIONS, true)) {
                self::assertArrayHasKey('custom_action', $extraProperties, self::label($operation) . ' is not marked');
                self::assertSame(true, $extraProperties['custom_action'], self::label($operation) . ' has a non-boolean marker');
                $marked[] = $operation->getName();
            } else {
                self::assertArrayNotHasKey('custom_action', $extraProperties, self::label($operation) . ' must not be marked');
            }
        }

        self::assertSame(self::CUSTOM_ACTIONS, $marked);
    }

    public function testResourceCarriesNoMarker(): void
    {
        self::assertArrayNotHasKey('custom_action', ApiConfigurationSecurityTest::resource()->getExtraProperties() ?? []);
    }

    /** Same label as {@see ApiConfigurationSecurityTest}, whose helper is private. */
    private static function label(Operation $operation): string
    {
        return $operation::class . ' ' . ($operation->getUriTemplate() ?? '');
    }
}
