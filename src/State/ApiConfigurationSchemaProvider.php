<?php
// file generated with AI assistance: Claude Code - 2025-11-01 00:00:00

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Dmstr\OpenApiJsonSchema\Service\SchemaRegistry;

/**
 * Provides the dynamic schema for ApiConfiguration's config property
 * This is used to generate the OpenAPI documentation with anyOf schemas
 */
class ApiConfigurationSchemaProvider implements ProviderInterface
{
    public function __construct(
        private readonly SchemaRegistry $schemaRegistry
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        // This provider is used to inject schema information into OpenAPI generation
        // The actual schema decoration happens via the SchemaDecorator
        return null;
    }

    public function getUnifiedSchema(): array
    {
        return $this->schemaRegistry->getUnifiedSchema();
    }
}
