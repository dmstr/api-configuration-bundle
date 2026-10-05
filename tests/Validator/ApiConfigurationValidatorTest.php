<?php
// file generated with AI assistance: Claude Code - 2026-10-05 13:00:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Tests\Validator;

use Dmstr\ApiConfiguration\Tests\Fixtures\SecretsFixture;
use Dmstr\ApiConfiguration\Validator\ApiConfigurationConstraint;
use Dmstr\ApiConfiguration\Validator\ApiConfigurationValidator;
use Dmstr\OpenApiJsonSchema\Service\SchemaRegistry;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<ApiConfigurationValidator>
 */
final class ApiConfigurationValidatorTest extends ConstraintValidatorTestCase
{
    private ?SchemaRegistry $registry = null;

    protected function createValidator(): ApiConfigurationValidator
    {
        return new ApiConfigurationValidator(
            $this->registry ?? SecretsFixture::schemaRegistry(),
            new NullLogger(),
            SecretsFixture::configSecrets(),
        );
    }

    public function testNoRegisteredSchemaIsAViolationNotAnError(): void
    {
        // Issue #5: an empty registry yields `anyOf: []`, which opis rejects
        $this->registry = new SchemaRegistry(new ArrayAdapter(), new NullLogger());
        $this->validator = $this->createValidator();
        $this->validator->initialize($this->context);

        $this->validator->validate(['type' => 't', 'endpoint_type' => 'rest'], new ApiConfigurationConstraint());

        $this->buildViolation('Invalid API configuration: {{ error }}')
            ->setParameter('{{ error }}', 'No API configuration type is registered; install or enable an extension that provides a configuration schema')
            ->assertRaised();
    }

    public function testValidConfigurationPasses(): void
    {
        $this->validator->validate(SecretsFixture::config(), new ApiConfigurationConstraint());

        $this->assertNoViolation();
    }

    public function testKeptEncryptedSecretIsValidatedInClear(): void
    {
        // token has minLength 4; the ciphertext alone would pass. Not via the
        // $ref'd oauth.client_secret: the unified anyOf schema cannot resolve a
        // type schema's local `#/definitions/...` (SchemaRegistry limitation)
        $config = SecretsFixture::configSecrets()->encrypt(
            SecretsFixture::config(['auth_type' => 'bearer', 'token' => 'abc']),
        );

        $this->validator->validate($config, new ApiConfigurationConstraint());

        self::assertCount(1, $this->context->getViolations());
    }
}
