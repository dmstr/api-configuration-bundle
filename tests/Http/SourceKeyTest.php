<?php
// file generated with AI assistance: Claude Code - 2026-11-11 11:05:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Tests\Http;

use Dmstr\ApiConfiguration\Entity\ApiConfiguration;

/**
 * The optional `sourceKey`: settable over the API, validated against its
 * pattern, unique across configurations, any number of configurations
 * without one.
 */
final class SourceKeyTest extends ApiTestCase
{
    private const string ROLE = 'admin';

    public function testSourceKeyCanBeSetAndIsReturned(): void
    {
        $configuration = $this->createApiConfiguration();

        $patch = $this->requestAs('PATCH', $this->itemUri($configuration), [self::ROLE], ['sourceKey' => 'gitlab-hrzg']);
        self::assertSame(200, $patch->getStatusCode(), (string) $patch->getContent());

        $get = $this->requestAs('GET', $this->itemUri($configuration), [self::ROLE]);
        self::assertSame('gitlab-hrzg', self::json($get)['sourceKey']);
    }

    public function testSourceKeyDefaultsToNull(): void
    {
        $configuration = $this->createApiConfiguration();

        $get = $this->requestAs('GET', $this->itemUri($configuration), [self::ROLE]);
        self::assertNull(self::json($get)['sourceKey'] ?? null);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidKeys(): iterable
    {
        yield 'uppercase' => ['GitLab'];
        yield 'leading dash' => ['-gitlab'];
        yield 'colon' => ['gitlab:hrzg'];
        yield 'single character' => ['g'];
        yield 'too long' => [str_repeat('a', 42)];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidKeys')]
    public function testInvalidSourceKeyIsRejected(string $key): void
    {
        $configuration = $this->createApiConfiguration();

        $response = $this->requestAs('PATCH', $this->itemUri($configuration), [self::ROLE], ['sourceKey' => $key]);

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testDuplicateSourceKeyIsRejected(): void
    {
        $first = $this->createApiConfiguration(name: 'First');
        $second = $this->createApiConfiguration(name: 'Second');

        self::assertSame(200, $this->requestAs('PATCH', $this->itemUri($first), [self::ROLE], ['sourceKey' => 'bc4-12345'])->getStatusCode());
        $response = $this->requestAs('PATCH', $this->itemUri($second), [self::ROLE], ['sourceKey' => 'bc4-12345']);

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testEmptyStringClearsTheKey(): void
    {
        $configuration = (new ApiConfiguration())->setSourceKey('');

        self::assertNull($configuration->getSourceKey());
    }
}
