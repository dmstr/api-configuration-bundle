<?php
// file generated with AI assistance: Claude Code - 2026-10-05 10:25:39 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Security;

use Dmstr\ApiPlatformUtils\Service\CredentialEncryption;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;

/**
 * Encrypts, decrypts, masks and preserves the secret keys of an
 * ApiConfiguration's `configJson`.
 *
 * - Stored: every secret value is replaced by `enc:v1:<ciphertext>`
 *   ({@see CredentialEncryption}, key `CREDENTIALS_ENCRYPTION_KEY`).
 * - Read: secret values are replaced by {@see self::MASK}.
 * - Update: an omitted secret or the mask keeps the stored value, `null`
 *   removes it, any other value replaces it.
 *
 * Which keys are secret is declared by the type schema, see
 * {@see SecretSchemaResolver}.
 */
final class ConfigSecrets
{
    public const string MASK = '********';
    public const string PREFIX = 'enc:v1:';

    /**
     * @param \Closure(): CredentialEncryption $encryption resolved lazily, so a
     *        missing key only fails where a secret is actually encrypted or
     *        decrypted
     */
    public function __construct(
        private readonly SecretSchemaResolver $resolver,
        #[AutowireServiceClosure(CredentialEncryption::class)]
        private readonly \Closure $encryption,
    ) {
    }

    public static function isEncrypted(mixed $value): bool
    {
        return is_string($value) && str_starts_with($value, self::PREFIX);
    }

    /**
     * The `type` of a configuration, as used to look up its schema.
     */
    public static function typeOf(array $config): string
    {
        return is_string($config['type'] ?? null) ? $config['type'] : '';
    }

    /**
     * Encrypts all secret values that are not encrypted yet (idempotent).
     *
     * @throws SecretEncryptionException if a secret is present but no usable key is configured
     */
    public function encrypt(array $config): array
    {
        foreach ($this->resolver->getSecretPaths(self::typeOf($config)) as $path) {
            $config = $this->mapPath($config, $path, function (mixed $value): mixed {
                if ($value === null || $value === '' || self::isEncrypted($value)) {
                    return $value;
                }

                return self::PREFIX . $this->cipher()->encrypt(['v' => $value]);
            });
        }

        return $config;
    }

    /**
     * Decrypts every encrypted value, wherever it is. Only for building a
     * client, never for output.
     *
     * @throws SecretEncryptionException if a value cannot be decrypted
     */
    public function decrypt(array $config): array
    {
        array_walk_recursive($config, function (mixed &$value): void {
            if (!self::isEncrypted($value)) {
                return;
            }

            try {
                $value = $this->cipher()->decrypt(substr($value, strlen(self::PREFIX)))['v'] ?? null;
            } catch (SecretEncryptionException $e) {
                throw $e;
            } catch (\Throwable $e) {
                throw new SecretEncryptionException(
                    'Cannot decrypt an ApiConfiguration secret: wrong CREDENTIALS_ENCRYPTION_KEY or corrupted value.',
                    0,
                    $e,
                );
            }
        });

        return $config;
    }

    /**
     * Replaces secret values by {@see self::MASK}, for API output and logs.
     * Encrypted values are masked even where the schema no longer marks them.
     */
    public function mask(array $config): array
    {
        foreach ($this->resolver->getSecretPaths(self::typeOf($config)) as $path) {
            $config = $this->mapPath(
                $config,
                $path,
                static fn (mixed $value): mixed => $value === null || $value === '' ? $value : self::MASK,
            );
        }

        array_walk_recursive($config, static function (mixed &$value): void {
            if (self::isEncrypted($value)) {
                $value = self::MASK;
            }
        });

        return $config;
    }

    /**
     * Applies the update rules for secrets: an omitted secret or the mask
     * keeps the stored value, `null` removes the key, any other value
     * replaces it. Nothing is carried over when the type changes.
     */
    public function keepStored(array $incoming, array $stored): array
    {
        $type = self::typeOf($incoming);
        if ($type !== self::typeOf($stored)) {
            return $incoming;
        }

        foreach ($this->resolver->getSecretPaths($type) as $path) {
            $incoming = $this->keepPath($incoming, $stored, $path);
        }

        return $incoming;
    }

    /**
     * @param list<string> $path
     */
    private function keepPath(array $incoming, array $stored, array $path): array
    {
        $segment = array_shift($path);
        $isWildcard = $segment === SecretSchemaResolver::WILDCARD;

        foreach ($isWildcard ? array_keys($incoming) : [$segment] as $key) {
            if ($path !== []) {
                if (is_array($incoming[$key] ?? null) && is_array($stored[$key] ?? null)) {
                    $incoming[$key] = $this->keepPath($incoming[$key], $stored[$key], $path);
                }
                continue;
            }

            // Only fixed keys can be removed or omitted; for items of a list or
            // map only the mask counts, so their keys stay intact
            if (!$isWildcard && array_key_exists($key, $incoming) && $incoming[$key] === null) {
                unset($incoming[$key]);
                continue;
            }

            $sendsMask = ($incoming[$key] ?? null) === self::MASK;
            $omitted = !$isWildcard && !array_key_exists($key, $incoming);

            if (($sendsMask || $omitted) && array_key_exists($key, $stored)) {
                $incoming[$key] = $stored[$key];
            }
        }

        return $incoming;
    }

    /**
     * Applies $fn to every existing value at $path.
     *
     * @param list<string> $path
     */
    private function mapPath(array $data, array $path, \Closure $fn): array
    {
        $segment = array_shift($path);
        $keys = $segment === SecretSchemaResolver::WILDCARD ? array_keys($data) : [$segment];

        foreach ($keys as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            if ($path === []) {
                $data[$key] = $fn($data[$key]);
            } elseif (is_array($data[$key])) {
                $data[$key] = $this->mapPath($data[$key], $path, $fn);
            }
        }

        return $data;
    }

    private function cipher(): CredentialEncryption
    {
        try {
            return ($this->encryption)();
        } catch (\Throwable $e) {
            throw new SecretEncryptionException(
                'ApiConfiguration secrets cannot be encrypted or decrypted: the credential encryption service is not usable. '
                . 'Generate a key with "bin/console dmstr:generate-encryption-key", set CREDENTIALS_ENCRYPTION_KEY and configure '
                . 'dmstr_api_platform_utils.credential_encryption.key: "%env(base64:CREDENTIALS_ENCRYPTION_KEY)%". Reason: '
                . $e->getMessage(),
                0,
                $e,
            );
        }
    }
}
