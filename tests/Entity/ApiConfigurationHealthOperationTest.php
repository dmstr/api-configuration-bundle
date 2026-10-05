<?php
// file generated with AI assistance: Claude Code - 2026-10-05 12:10:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Tests\Entity;

use ApiPlatform\Metadata\ApiResource;
use Dmstr\ApiConfiguration\Entity\ApiConfiguration;
use PHPUnit\Framework\TestCase;

/**
 * Issue #9: without `api_sub_level`, JSON-LD turns the nested health arrays
 * (metadata, error) into hydra:Collection and drops their keys.
 */
final class ApiConfigurationHealthOperationTest extends TestCase
{
    public function testHealthOperationNormalizesNestedArraysRaw(): void
    {
        /** @var ApiResource $resource */
        $resource = (new \ReflectionClass(ApiConfiguration::class))
            ->getAttributes(ApiResource::class)[0]
            ->newInstance();

        foreach ($resource->getOperations() ?? [] as $operation) {
            if ($operation->getName() === 'api_configuration_health') {
                self::assertTrue($operation->getNormalizationContext()['api_sub_level'] ?? false);

                return;
            }
        }

        self::fail('health operation not found');
    }
}
