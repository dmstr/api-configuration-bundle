<?php
// file generated with AI assistance: Claude Code - 2026-10-05 12:10:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Tests\ApiClient;

use Dmstr\ApiConfiguration\ApiClient\ApiClientFactory;
use Dmstr\ApiConfiguration\ApiClient\InvalidConfigurationException;
use Dmstr\ApiConfiguration\Entity\ApiConfiguration;
use Dmstr\ApiConfiguration\Service\ApiExtensionRegistry;
use Dmstr\ApiConfiguration\Tests\Fixtures\DemoExtension;
use Dmstr\ApiConfiguration\Tests\Fixtures\SecretsFixture;
use PHPUnit\Framework\TestCase;

final class ApiClientFactoryTest extends TestCase
{
    private DemoExtension $extension;
    private ApiClientFactory $factory;

    protected function setUp(): void
    {
        $this->extension = new DemoExtension();
        $this->factory = new ApiClientFactory(
            new ApiExtensionRegistry([$this->extension]),
            SecretsFixture::configSecrets(),
        );
    }

    public function testClientGetsDecryptedConfigWithInternalId(): void
    {
        $secrets = SecretsFixture::configSecrets();
        $entity = (new ApiConfiguration())
            ->setName('demo')
            ->setConfigJson($secrets->encrypt(SecretsFixture::config()));

        $this->factory->createFromEntity($entity);

        self::assertSame('s3cr3t-password', $this->extension->lastConfig['password']);
        self::assertSame((string) $entity->getId(), $this->extension->lastConfig['_apiConfigurationId']);
    }

    public function testRejectsConfigViolatingTheExtensionSchemaNamingTheField(): void
    {
        $config = SecretsFixture::config();
        unset($config['base_url']);

        try {
            $this->factory->create('demo', $config);
            self::fail('InvalidConfigurationException expected');
        } catch (InvalidConfigurationException $e) {
            self::assertStringContainsString('base_url', $e->getMessage());
            self::assertNotEmpty($e->getErrors());
            self::assertNull($this->extension->lastConfig, 'client must not be built');
        }
    }

    public function testValidatesTheDecryptedValue(): void
    {
        // client_secret has minLength 4: the clear value is checked, not the ciphertext
        $secrets = SecretsFixture::configSecrets();
        $config = $secrets->encrypt(SecretsFixture::config(['oauth' => ['client_secret' => 'abc']]));

        $this->expectException(InvalidConfigurationException::class);
        $this->factory->create('demo', $config);
    }

    public function testUnknownApiNameStillThrowsInvalidArgument(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported API name');
        $this->factory->create('nope', []);
    }
}
