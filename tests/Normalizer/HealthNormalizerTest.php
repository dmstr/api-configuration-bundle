<?php
// file generated with AI assistance: Claude Code - 2026-07-03 10:45:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Tests\Normalizer;

use Dmstr\ApiConfiguration\Normalizer\HealthNormalizer;
use PHPUnit\Framework\TestCase;

final class HealthNormalizerTest extends TestCase
{
    public function testResolvesBundledSchemaWithoutConstructorArguments(): void
    {
        $normalizer = new HealthNormalizer();

        $normalized = $normalizer->normalize(
            ['status' => 'ok', 'authenticated' => true],
            'https://api.example.com',
            12.345,
        );

        self::assertSame('ok', $normalized['status']);
        self::assertTrue($normalized['authenticated']);
        self::assertSame('https://api.example.com', $normalized['endpoint']);
        self::assertSame(12.35, $normalized['responseTime']);
    }

    public function testNormalizesMetadataAndError(): void
    {
        $normalized = (new HealthNormalizer())->normalize(
            [
                'status' => 'error',
                'authenticated' => false,
                'metadata' => [
                    'version' => 42,
                    'rateLimit' => ['limit' => '100', 'remaining' => '99', 'reset' => 1750000000],
                ],
                'error' => ['code' => 401, 'message' => 'Unauthorized'],
            ],
            'https://api.example.com',
            5.0,
        );

        self::assertSame('42', $normalized['metadata']['version']);
        self::assertSame(100, $normalized['metadata']['rateLimit']['limit']);
        self::assertSame('401', $normalized['error']['code']);
        self::assertSame('Unauthorized', $normalized['error']['message']);
    }

    public function testRejectsPayloadViolatingSchema(): void
    {
        $this->expectExceptionMessage('Health data validation failed');

        // "status" must be one of the schema's enum values
        (new HealthNormalizer())->normalize(
            ['status' => 'definitely-not-a-valid-status', 'authenticated' => true],
            'https://api.example.com',
            1.0,
        );
    }
}
