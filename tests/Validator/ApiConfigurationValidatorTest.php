<?php
// file generated with AI assistance: Claude Code - 2026-10-05 13:00:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Tests\Validator;

use Dmstr\ApiConfiguration\Tests\Fixtures\SecretsFixture;
use Dmstr\ApiConfiguration\Validator\ApiConfigurationConstraint;
use Dmstr\ApiConfiguration\Validator\ApiConfigurationValidator;
use Dmstr\OpenApiJsonSchema\Service\SchemaRegistry;
use Opis\JsonSchema\Exceptions\InvalidKeywordException;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<ApiConfigurationValidator>
 */
final class ApiConfigurationValidatorTest extends ConstraintValidatorTestCase
{
    /** Cache key of the unified schema in {@see SchemaRegistry} */
    private const string UNIFIED_SCHEMA_CACHE_KEY = 'dmstr_openapi_json_schema_unified';

    private ?SchemaRegistry $registry = null;

    private ?LoggerInterface $logger = null;

    protected function createValidator(): ApiConfigurationValidator
    {
        return new ApiConfigurationValidator(
            $this->registry ?? SecretsFixture::schemaRegistry(),
            $this->logger ?? new NullLogger(),
            SecretsFixture::configSecrets(),
        );
    }

    public function testNoRegisteredSchemaIsAViolationNotAnError(): void
    {
        // Issue #5, Issue #16: an empty registry yields the boolean schema
        // false; the validator reports it before opis is reached
        $this->registry = new SchemaRegistry(new ArrayAdapter(), new NullLogger());
        $this->logger = $logger = $this->recordingLogger();
        $this->validator = $this->createValidator();
        $this->validator->initialize($this->context);

        self::assertFalse($this->registry->getUnifiedSchema());

        $this->validator->validate(['type' => 't', 'endpoint_type' => 'rest'], new ApiConfigurationConstraint());

        $this->buildViolation('Invalid API configuration: {{ error }}')
            ->setParameter('{{ error }}', 'No API configuration type is registered; install or enable an extension that provides a configuration schema')
            ->assertRaised();
        self::assertSame(
            [['warning', 'API configuration rejected: no configuration schema is registered']],
            $logger->records,
        );
    }

    public function testWarmCacheWithTheOldEmptyAnyOfStillAcceptsNothing(): void
    {
        // Upgrade from openapi-json-schema-bundle 0.5: `cache.app` may still
        // hold the old `anyOf: []` until its TTL expires. There is no special
        // handling for it; opis rejects the invalid schema with an exception,
        // so the input is still not accepted (fail closed)
        $cache = new ArrayAdapter();
        $cache->save($cache->getItem(self::UNIFIED_SCHEMA_CACHE_KEY)->set(['anyOf' => []]));
        $this->registry = new SchemaRegistry($cache, new NullLogger());
        $this->validator = $this->createValidator();
        $this->validator->initialize($this->context);

        self::assertSame(['anyOf' => []], $this->registry->getUnifiedSchema());

        $this->expectException(InvalidKeywordException::class);
        $this->expectExceptionMessage('anyOf must have at least one element');

        $this->validator->validate(SecretsFixture::config(), new ApiConfigurationConstraint());
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

    /**
     * A logger that keeps level and message of every record in `$records`
     */
    private function recordingLogger(): AbstractLogger
    {
        return new class extends AbstractLogger {
            /** @var list<array{string, string}> */
            public array $records = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->records[] = [(string) $level, (string) $message];
            }
        };
    }
}
