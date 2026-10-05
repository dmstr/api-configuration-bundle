<?php
// file generated with AI assistance: Claude Code - 2026-10-05 12:10:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Tests\Health;

use Dmstr\ApiConfiguration\ApiClient\ApiClientFactory;
use Dmstr\ApiConfiguration\Entity\ApiConfiguration;
use Dmstr\ApiConfiguration\Health\ApiConfigurationHealthChecker;
use Dmstr\ApiConfiguration\Health\HealthProbeInterface;
use Dmstr\ApiConfiguration\Normalizer\HealthNormalizer;
use Dmstr\ApiConfiguration\Service\ApiExtensionRegistry;
use Dmstr\ApiConfiguration\Tests\Fixtures\DemoExtension;
use Dmstr\ApiConfiguration\Tests\Fixtures\SecretsFixture;
use PHPUnit\Framework\TestCase;

final class ApiConfigurationHealthCheckerTest extends TestCase
{
    public function testFallsBackToTheClient(): void
    {
        $result = $this->checker([new DemoExtension()])->check($this->entity(SecretsFixture::config()));

        self::assertSame('ok', $result['status']);
        self::assertSame('https://api.example.com', $result['endpoint']);
        self::assertSame('demo', $result['metadata']['apiName']);
    }

    public function testSchemaOnlyTypeIsCheckedByItsProbeWithDecryptedConfig(): void
    {
        $probe = new class implements HealthProbeInterface {
            public ?array $config = null;

            public function getName(): string
            {
                return 'mcp';
            }

            public function getEndpoint(array $config): string
            {
                return $config['url'];
            }

            public function probe(array $config): array
            {
                $this->config = $config;

                return ['status' => 'ok', 'authenticated' => true, 'metadata' => ['custom' => ['toolCount' => 3]]];
            }
        };

        $secrets = SecretsFixture::configSecrets();
        // No extension for "mcp": without the probe this was "Unsupported API name"
        $entity = $this->entity(['type' => 'mcp', 'endpoint_type' => 'rest', 'url' => 'https://mcp.example.com', 'token' => $secrets->encrypt(['type' => 'demo', 'token' => 't0ken'])['token']]);

        $result = $this->checker([], [$probe])->check($entity);

        self::assertSame('ok', $result['status']);
        self::assertSame('https://mcp.example.com', $result['endpoint']);
        self::assertSame(3, $result['metadata']['custom']['toolCount']);
        self::assertSame('t0ken', $probe->config['token']);
    }

    public function testFailureBecomesAnErrorResult(): void
    {
        $entity = $this->entity(['type' => 'unknown', 'endpoint_type' => 'rest']);

        $result = $this->checker([])->check($entity);

        self::assertSame('error', $result['status']);
        self::assertSame('HEALTH_CHECK_ERROR', $result['error']['code']);
        self::assertStringStartsWith('urn:za7:api-configuration:', $result['endpoint']);
    }

    private function checker(array $extensions, array $probes = []): ApiConfigurationHealthChecker
    {
        $secrets = SecretsFixture::configSecrets();

        return new ApiConfigurationHealthChecker(
            new ApiClientFactory(new ApiExtensionRegistry($extensions), $secrets),
            new HealthNormalizer(),
            $secrets,
            $probes,
        );
    }

    private function entity(array $config): ApiConfiguration
    {
        return (new ApiConfiguration())->setName('test')->setConfigJson($config);
    }
}
