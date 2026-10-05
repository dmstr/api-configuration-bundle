<?php
// file generated with AI assistance: Claude Code - 2026-10-05 12:10:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\ApiClient;

/**
 * Thrown by {@see ApiClientInterface::authenticate()}. Carries the original
 * transport exception as `previous`, so the cause (401, DNS, TLS, ...)
 * reaches the caller instead of a bare "failed".
 */
class AuthenticationFailedException extends \RuntimeException
{
    public static function fromPrevious(string $apiName, \Throwable $previous): self
    {
        return new self(
            sprintf('Authentication against "%s" failed: %s', $apiName, $previous->getMessage()),
            0,
            $previous,
        );
    }
}
