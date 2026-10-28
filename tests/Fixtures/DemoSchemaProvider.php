<?php
// file generated with AI assistance: Claude Code - 2026-10-28 07:35:37 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Tests\Fixtures;

use Dmstr\OpenApiJsonSchema\Interface\SchemaProviderInterface;

/**
 * Registers the `demo` type schema (tests/Fixtures/demo.schema.json) with
 * the SchemaRegistry of the test kernel, so `demo` configurations pass the
 * ApiConfigurationValidator.
 */
final class DemoSchemaProvider implements SchemaProviderInterface
{
    public function getName(): string
    {
        return 'demo';
    }

    public function getSchemaPath(): string
    {
        return __DIR__ . '/demo.schema.json';
    }
}
