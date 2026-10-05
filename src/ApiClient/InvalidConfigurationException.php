<?php
// file generated with AI assistance: Claude Code - 2026-10-05 12:10:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\ApiClient;

/**
 * The configuration handed to {@see ApiClientFactory::create()} does not match
 * the extension's schema. Extends \InvalidArgumentException, which the factory
 * has always declared.
 */
class InvalidConfigurationException extends \InvalidArgumentException
{
    /**
     * @param array<string, list<string>> $errors messages by JSON pointer
     */
    public function __construct(
        string $apiName,
        private readonly array $errors,
    ) {
        $lines = [];
        foreach ($errors as $pointer => $messages) {
            $lines[] = sprintf('%s: %s', $pointer === '' ? '/' : $pointer, implode(' ', $messages));
        }

        parent::__construct(sprintf('Invalid configuration for "%s": %s', $apiName, implode('; ', $lines)));
    }

    /**
     * @return array<string, list<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
