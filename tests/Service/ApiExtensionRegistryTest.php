<?php
// file generated with AI assistance: Claude Code - 2026-06-13 23:14:54 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Tests\Service;

use Dmstr\ApiConfiguration\Extension\ApiExtensionInterface;
use Dmstr\ApiConfiguration\Service\ApiExtensionRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see ApiExtensionRegistry} — the in-memory map of pluggable
 * API extensions keyed by their {@see ApiExtensionInterface::getName()}.
 */
final class ApiExtensionRegistryTest extends TestCase
{
    public function testConstructorRegistersIterableExtensions(): void
    {
        // Mirrors the tagged_iterator wiring: all ApiExtensionInterface
        // implementations are collected at construction time.
        $registry = new ApiExtensionRegistry([
            $this->extension('gitlab'),
            $this->extension('basecamp2'),
        ]);

        self::assertSame(['gitlab', 'basecamp2'], $registry->getNames());
        self::assertSame('basecamp2', $registry->get('basecamp2')?->getName());
    }

    public function testRegisterAndGet(): void
    {
        $registry = new ApiExtensionRegistry();
        $github = $this->extension('github');

        $registry->register($github);

        self::assertSame($github, $registry->get('github'));
    }

    public function testGetUnknownReturnsNull(): void
    {
        self::assertNull((new ApiExtensionRegistry())->get('does-not-exist'));
    }

    public function testHasReflectsRegistration(): void
    {
        $registry = new ApiExtensionRegistry();
        self::assertFalse($registry->has('gitlab'));

        $registry->register($this->extension('gitlab'));
        self::assertTrue($registry->has('gitlab'));
    }

    public function testAllReturnsNameKeyedMap(): void
    {
        $registry = new ApiExtensionRegistry();
        $github = $this->extension('github');
        $gitlab = $this->extension('gitlab');
        $registry->register($github);
        $registry->register($gitlab);

        self::assertSame(['github' => $github, 'gitlab' => $gitlab], $registry->all());
    }

    public function testGetNamesListsRegisteredKeys(): void
    {
        $registry = new ApiExtensionRegistry();
        $registry->register($this->extension('github'));
        $registry->register($this->extension('basecamp4'));

        self::assertSame(['github', 'basecamp4'], $registry->getNames());
    }

    public function testRegisteringSameNameOverwrites(): void
    {
        $registry = new ApiExtensionRegistry();
        $first = $this->extension('github');
        $second = $this->extension('github');

        $registry->register($first);
        $registry->register($second);

        self::assertSame($second, $registry->get('github'));
        self::assertCount(1, $registry->all());
    }

    private function extension(string $name): ApiExtensionInterface
    {
        $extension = $this->createStub(ApiExtensionInterface::class);
        $extension->method('getName')->willReturn($name);

        return $extension;
    }
}
