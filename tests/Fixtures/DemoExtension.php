<?php
// file generated with AI assistance: Claude Code - 2026-10-05 12:10:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Tests\Fixtures;

use Dmstr\ApiConfiguration\ApiClient\ApiClientInterface;
use Dmstr\ApiConfiguration\Extension\ApiExtensionInterface;

/**
 * Extension for the `demo` type (tests/Fixtures/demo.schema.json) that
 * records the configuration its client was built with.
 */
final class DemoExtension implements ApiExtensionInterface
{
    public ?array $lastConfig = null;

    public function getName(): string
    {
        return 'demo';
    }

    public function getType(): string
    {
        return 'rest';
    }

    public function getSchemaPath(): string
    {
        return __DIR__ . '/demo.schema.json';
    }

    public function createClient(array $config): ApiClientInterface
    {
        $this->lastConfig = $config;

        return new DemoClient($config);
    }
}
