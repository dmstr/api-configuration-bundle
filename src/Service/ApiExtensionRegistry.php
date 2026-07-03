<?php
// file generated with AI assistance: Claude Code - 2025-11-01 00:00:00

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Service;

use Dmstr\ApiConfiguration\Extension\ApiExtensionInterface;

/**
 * Registry service for API extensions
 */
class ApiExtensionRegistry
{
    /**
     * @var array<string, ApiExtensionInterface>
     */
    private array $extensions = [];

    /**
     * @param iterable<ApiExtensionInterface> $extensions Usually the services
     *        tagged with ApiConfigurationBundle::EXTENSION_TAG (autoconfigured
     *        for every ApiExtensionInterface implementation).
     */
    public function __construct(iterable $extensions = [])
    {
        foreach ($extensions as $extension) {
            $this->register($extension);
        }
    }

    /**
     * Register an API extension
     *
     * @param ApiExtensionInterface $extension
     * @return void
     */
    public function register(ApiExtensionInterface $extension): void
    {
        $this->extensions[$extension->getName()] = $extension;
    }

    /**
     * Get an extension by name
     *
     * @param string $name
     * @return ApiExtensionInterface|null
     */
    public function get(string $name): ?ApiExtensionInterface
    {
        return $this->extensions[$name] ?? null;
    }

    /**
     * Get all registered extensions
     *
     * @return array<string, ApiExtensionInterface>
     */
    public function all(): array
    {
        return $this->extensions;
    }

    /**
     * Check if an extension is registered
     *
     * @param string $name
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this->extensions[$name]);
    }

    /**
     * Get all registered extension names
     *
     * @return array<string>
     */
    public function getNames(): array
    {
        return array_keys($this->extensions);
    }
}
