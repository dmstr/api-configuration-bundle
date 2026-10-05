<?php
// file generated with AI assistance: Claude Code - 2026-10-05 10:25:39 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Security;

/**
 * Secrets of an API configuration cannot be encrypted or decrypted, usually
 * because `CREDENTIALS_ENCRYPTION_KEY` is missing, invalid or was changed.
 */
final class SecretEncryptionException extends \RuntimeException
{
}
