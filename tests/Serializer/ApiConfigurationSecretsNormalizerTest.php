<?php
// file generated with AI assistance: Claude Code - 2026-10-05 10:25:39 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Tests\Serializer;

use Dmstr\ApiConfiguration\Entity\ApiConfiguration;
use Dmstr\ApiConfiguration\Security\ConfigSecrets;
use Dmstr\ApiConfiguration\Serializer\ApiConfigurationSecretsNormalizer;
use Dmstr\ApiConfiguration\Tests\Fixtures\SecretsFixture;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class ApiConfigurationSecretsNormalizerTest extends TestCase
{
    private ConfigSecrets $secrets;
    private RequestStack $requestStack;
    private ApiConfigurationSecretsNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->secrets = SecretsFixture::configSecrets();
        $this->requestStack = new RequestStack();
        $this->normalizer = new ApiConfigurationSecretsNormalizer($this->secrets, $this->requestStack);
        $this->normalizer->setNormalizer(new InnerSerializer());
        $this->normalizer->setDenormalizer(new InnerSerializer());
    }

    public function testResponseNeverContainsSecrets(): void
    {
        $entity = $this->entity($this->secrets->encrypt(SecretsFixture::config()));

        self::assertTrue($this->normalizer->supportsNormalization($entity, 'jsonld'));
        $normalized = $this->normalizer->normalize($entity, 'jsonld');

        self::assertSame(ConfigSecrets::MASK, $normalized['configJson']['password']);
        self::assertSame('alice', $normalized['configJson']['username']);
        self::assertStringNotContainsString(ConfigSecrets::PREFIX, json_encode($normalized));
    }

    public function testPatchWithMaskKeepsStoredSecret(): void
    {
        $entity = $this->entity($this->secrets->encrypt(SecretsFixture::config()));
        $storedPassword = $entity->getConfigJson()['password'];

        $result = $this->normalizer->denormalize(
            ['configJson' => SecretsFixture::config(['password' => ConfigSecrets::MASK, 'username' => 'bob'])],
            ApiConfiguration::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $entity],
        );

        self::assertSame($storedPassword, $result->getConfigJson()['password']);
        self::assertSame('bob', $result->getConfigJson()['username']);
    }

    public function testStandardPutUsesPreviousData(): void
    {
        $previous = $this->entity($this->secrets->encrypt(SecretsFixture::config()));
        $request = new Request();
        $request->attributes->set('previous_data', $previous);
        $this->requestStack->push($request);

        $config = SecretsFixture::config();
        unset($config['password']);
        $result = $this->normalizer->denormalize(['configJson' => $config], ApiConfiguration::class, 'json');

        self::assertNotSame($previous, $result);
        self::assertSame($previous->getConfigJson()['password'], $result->getConfigJson()['password']);
    }

    public function testPostStoresNewValue(): void
    {
        $result = $this->normalizer->denormalize(
            ['configJson' => SecretsFixture::config()],
            ApiConfiguration::class,
            'json',
        );

        self::assertSame('s3cr3t-password', $result->getConfigJson()['password']);
    }

    private function entity(array $config): ApiConfiguration
    {
        return (new ApiConfiguration())->setName('demo')->setConfigJson($config);
    }
}

/**
 * Stands in for the API Platform item normalizer.
 */
final class InnerSerializer implements NormalizerInterface, DenormalizerInterface
{
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return ['@id' => '/api/admin/api_configurations/x', 'name' => $data->getName(), 'configJson' => $data->getConfigJson()];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return true;
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): ApiConfiguration
    {
        $object = $context[AbstractNormalizer::OBJECT_TO_POPULATE] ?? new ApiConfiguration();

        return $object->setConfigJson($data['configJson']);
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return true;
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['*' => false];
    }
}
