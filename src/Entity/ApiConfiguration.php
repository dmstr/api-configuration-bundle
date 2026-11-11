<?php
// file generated with AI assistance: Claude Code - 2026-10-08 13:38:06 UTC

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
use Dmstr\ApiConfiguration\Security\ApiConfigurationPermissions;
use Dmstr\ApiConfiguration\State\ApiConfigurationAuthorizeProvider;
use Dmstr\ApiConfiguration\State\ApiConfigurationHealthProvider;
use Dmstr\ApiConfiguration\Validator\ApiConfigurationConstraint;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'dmstr_api_configuration')]
#[ORM\UniqueConstraint(name: 'uniq_dmstr_api_configuration_source_key', columns: ['source_key'])]
#[UniqueEntity(fields: ['sourceKey'], message: 'This source key is already used by another API configuration.')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new Get(security: self::SECURITY_READ),
        new GetCollection(security: self::SECURITY_READ),
        new Post(security: self::SECURITY_WRITE),
        new Put(security: self::SECURITY_WRITE),
        new Patch(security: self::SECURITY_WRITE),
        new Delete(security: self::SECURITY_WRITE),
        new Get(
            uriTemplate: '/api_configurations/{id}/health',
            name: 'api_configuration_health',
            security: self::SECURITY_READ,
            provider: ApiConfigurationHealthProvider::class,
            // Nested arrays (metadata, error) stay plain objects in JSON-LD
            // instead of becoming hydra:Collection (Issue #9)
            normalizationContext: ['api_sub_level' => true],
        ),
        new Get(
            uriTemplate: '/api_configurations/{id}/authorize',
            name: 'api_configuration_authorize',
            security: self::SECURITY_AUTHORIZE,
            provider: ApiConfigurationAuthorizeProvider::class,
        ),
    ],
    routePrefix: '/admin',
    // Every operation names its permission explicitly; this is only the
    // fallback for an operation added without one. Reads are included:
    // configJson holds upstream credentials (Issue #8). The attributes are
    // decided by the application's permission voter, without one every
    // operation is denied (see ApiConfigurationPermissions).
    security: self::SECURITY_READ,
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
#[ApiFilter(OrderFilter::class, properties: ['name', 'type', 'endpointType', 'active', 'createdAt', 'updatedAt'])]
class ApiConfiguration implements JsonSchemaProviderInterface
{
    // Security expressions of the operations above. They only check the
    // permission attribute, without `object`, so API Platform evaluates them
    // before reading the item: a denied caller gets 403, never 404.
    private const string SECURITY_READ = "is_granted('" . ApiConfigurationPermissions::READ . "')";
    private const string SECURITY_WRITE = "is_granted('" . ApiConfigurationPermissions::WRITE . "')";
    private const string SECURITY_AUTHORIZE = "is_granted('" . ApiConfigurationPermissions::AUTHORIZE . "')";

    public const string SOURCE_KEY_PATTERN = '/^[a-z0-9][a-z0-9.-]{1,40}$/';

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

    /**
     * Stable, operator-defined key of the upstream source. Applications derive
     * deterministic identifiers of imported records from it (e.g. a UUID v5 of
     * `<sourceKey>:<external id>`), so the same source yields the same ids on
     * every installation that uses the same key. Optional and unique.
     */
    #[ORM\Column(name: 'source_key', type: Types::STRING, length: 64, nullable: true)]
    #[Assert\Regex(pattern: self::SOURCE_KEY_PATTERN, message: 'The source key must start with a lowercase letter or digit and contain only lowercase letters, digits, "." and "-" (2-41 characters).')]
    #[ApiProperty(description: 'Optional stable key of the upstream source, unique across configurations (pattern ^[a-z0-9][a-z0-9.-]{1,40}$). Applications derive deterministic ids of imported records from it; changing it later may require re-keying those records.')]
    private ?string $sourceKey = null;

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
    #[ApiProperty(description: 'API configuration object. Must match one of the supported API types. Secret keys (marked "writeOnly" in the type schema) are stored encrypted and returned masked as "********"; send the mask or omit the key to keep the stored value, null to remove it.')]
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

    public function getSourceKey(): ?string
    {
        return $this->sourceKey;
    }

    public function setSourceKey(?string $sourceKey): self
    {
        // An empty string from a form means "no key"; the unique index allows
        // any number of NULLs but only one ''
        $this->sourceKey = $sourceKey === '' ? null : $sourceKey;
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
