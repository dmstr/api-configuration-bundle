<?php

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Entity;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\OpenApi\Model\Operation;
use Dmstr\OpenApiJsonSchema\Attribute\JsonSchema;
use Dmstr\OpenApiJsonSchema\Interface\JsonSchemaProviderInterface;
use Dmstr\OpenApiJsonSchema\OpenApi\JsonFieldSchemaDecorator;
use Dmstr\ApiConfiguration\State\ApiConfigurationAuthorizeProvider;
use Dmstr\ApiConfiguration\State\ApiConfigurationHealthProvider;
use Dmstr\ApiConfiguration\Validator\ApiConfigurationConstraint;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'dmstr_api_configuration')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(security: "is_granted('ROLE_ADMIN')"),
        new Put(security: "is_granted('ROLE_ADMIN')"),
        new Patch(security: "is_granted('ROLE_ADMIN')"),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
        new Get(
            uriTemplate: '/api_configurations/{id}/health',
            name: 'api_configuration_health',
            provider: ApiConfigurationHealthProvider::class
        ),
        new Get(
            uriTemplate: '/api_configurations/{id}/authorize',
            name: 'api_configuration_authorize',
            security: "is_granted('ROLE_ADMIN')",
            provider: ApiConfigurationAuthorizeProvider::class,
        ),
    ],
    routePrefix: '/admin',
    security: "is_granted('ROLE_USER')",
    paginationEnabled: true,
    paginationItemsPerPage: 30,
    openapi: new Operation(tags: ['System'])
)]
#[ApiFilter(SearchFilter::class, properties: [
    'name' => 'partial',
    'type' => 'exact',
    'endpointType' => 'exact',
])]
#[ApiFilter(BooleanFilter::class, properties: ['active'])]
#[ApiFilter(DateFilter::class, properties: ['createdAt', 'updatedAt'])]
#[ApiFilter(OrderFilter::class, properties: ['id', 'name', 'type', 'endpointType', 'active', 'createdAt', 'updatedAt'])]
class ApiConfiguration implements JsonSchemaProviderInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ApiProperty(identifier: true, readable: true, writable: false, description: 'Unique identifier')]
    private Uuid $id;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[ApiProperty(description: 'Configuration name')]
    #[Groups(['api_configuration:ref'])]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Assert\NotBlank]
    #[ApiProperty(readable: true, writable: false, description: 'API type (extracted from configJson)')]
    private string $type;

    #[ORM\Column(type: Types::STRING, length: 10)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['rest', 'file'])]
    #[ApiProperty(readable: true, writable: false, description: 'Endpoint type: rest or file')]
    private string $endpointType;

    #[ORM\Column(name: 'config_json', type: Types::JSON)]
    #[JsonSchema]
    #[Assert\NotBlank]
    #[ApiConfigurationConstraint]
    #[ApiProperty(description: 'API configuration object. Must match one of the supported API types.')]
    private array $configJson = [];

    #[ORM\Column(type: Types::BOOLEAN)]
    #[ApiProperty(description: 'Whether this configuration is active')]
    private bool $active = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[ApiProperty(readable: true, writable: false, description: 'Creation timestamp')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[ApiProperty(readable: true, writable: false, description: 'Last update timestamp')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
        $this->extractTypeAndEndpointType();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->extractTypeAndEndpointType();
    }

    /**
     * Extract type and endpoint_type from config JSON
     */
    private function extractTypeAndEndpointType(): void
    {
        if (isset($this->configJson['type'])) {
            $this->type = $this->configJson['type'];
        }

        if (isset($this->configJson['endpoint_type'])) {
            $this->endpointType = $this->configJson['endpoint_type'];
        }
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getEndpointType(): string
    {
        return $this->endpointType;
    }

    public function setEndpointType(string $endpointType): self
    {
        $this->endpointType = $endpointType;
        return $this;
    }

    public function getConfigJson(): array
    {
        return $this->configJson;
    }

    public function setConfigJson(array $configJson): self
    {
        $this->configJson = $configJson;

        // Auto-extract type and endpointType when configJson is set
        // This ensures validation passes since these fields are required
        $this->extractTypeAndEndpointType();

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Provide JSON schema for fields
     * For 'config' field, returns the unified anyOf schema from SchemaRegistry
     */
    public static function getJsonSchema(string $fieldName): ?array
    {
        if ($fieldName === 'config') {
            // Get SchemaRegistry from the decorator
            $schemaRegistry = JsonFieldSchemaDecorator::getSchemaRegistry();

            if ($schemaRegistry === null) {
                // Fallback to null if registry not available
                // This will cause the decorator to try loading from file
                return null;
            }

            $unifiedSchema = $schemaRegistry->getUnifiedSchema();
            // Return only description and anyOf for clean schema generation
            // DO NOT include 'type', 'properties', 'required', or 'additionalProperties'
            return [
                'description' => $unifiedSchema['description'] ?? 'API configuration object. Must match one of the supported API types.',
                'anyOf' => $unifiedSchema['anyOf']
            ];
        }

        return null;
    }
}
