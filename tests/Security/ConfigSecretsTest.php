<?php
// file generated with AI assistance: Claude Code - 2026-10-05 10:25:39 UTC

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Tests\Security;

use Dmstr\ApiConfiguration\Security\ConfigSecrets;
use Dmstr\ApiConfiguration\Security\SecretEncryptionException;
use Dmstr\ApiConfiguration\Tests\Fixtures\SecretsFixture;
use Dmstr\ApiPlatformUtils\Service\CredentialEncryption;
use PHPUnit\Framework\TestCase;

final class ConfigSecretsTest extends TestCase
{
    public function testEncryptsMarkedKeysOnlyAndDecryptsThemBack(): void
    {
        $secrets = SecretsFixture::configSecrets();
        $config = SecretsFixture::config([
            'oauth' => ['client_id' => 'app', 'client_secret' => 'oauth-secret'],
            'headers' => [['name' => 'X-Api-Key', 'value' => 'header-secret']],
        ]);

        $encrypted = $secrets->encrypt($config);
        $json = json_encode($encrypted);

        foreach (['s3cr3t-password', 'oauth-secret', 'header-secret'] as $clear) {
            self::assertStringNotContainsString($clear, $json);
        }
        self::assertTrue(ConfigSecrets::isEncrypted($encrypted['password']));
        self::assertTrue(ConfigSecrets::isEncrypted($encrypted['oauth']['client_secret']));
        self::assertTrue(ConfigSecrets::isEncrypted($encrypted['headers'][0]['value']));
        self::assertSame('alice', $encrypted['username']);
        self::assertSame('app', $encrypted['oauth']['client_id']);

        self::assertSame($config, $secrets->decrypt($encrypted));
    }

    public function testEncryptIsIdempotent(): void
    {
        $secrets = SecretsFixture::configSecrets();
        $once = $secrets->encrypt(SecretsFixture::config());

        self::assertSame($once, $secrets->encrypt($once));
    }

    public function testMasksSecretsInClearAndEncrypted(): void
    {
        $secrets = SecretsFixture::configSecrets();
        $clear = SecretsFixture::config(['token' => '']);

        $masked = $secrets->mask($clear);
        self::assertSame(ConfigSecrets::MASK, $masked['password']);
        self::assertSame('', $masked['token'], 'empty values carry no secret');
        self::assertSame('alice', $masked['username']);

        self::assertSame(ConfigSecrets::MASK, $secrets->mask($secrets->encrypt($clear))['password']);
    }

    public function testMasksEncryptedValuesTheSchemaNoLongerMarks(): void
    {
        $secrets = SecretsFixture::configSecrets();
        $config = SecretsFixture::config(['legacy' => ConfigSecrets::PREFIX . 'abc']);

        self::assertSame(ConfigSecrets::MASK, $secrets->mask($config)['legacy']);
    }

    public function testUpdateKeepsOmittedAndMaskedSecrets(): void
    {
        $secrets = SecretsFixture::configSecrets();
        $stored = $secrets->encrypt(SecretsFixture::config([
            'token' => 'stored-token',
            'headers' => [['name' => 'X-Api-Key', 'value' => 'header-secret']],
        ]));

        $incoming = SecretsFixture::config([
            'password' => ConfigSecrets::MASK,
            'headers' => [['name' => 'X-Api-Key', 'value' => ConfigSecrets::MASK]],
        ]);
        unset($incoming['token']);

        $merged = $secrets->keepStored($incoming, $stored);

        self::assertSame($stored['password'], $merged['password']);
        self::assertSame($stored['token'], $merged['token']);
        self::assertSame($stored['headers'][0]['value'], $merged['headers'][0]['value']);
    }

    public function testUpdateReplacesWithNewValueAndRemovesWithNull(): void
    {
        $secrets = SecretsFixture::configSecrets();
        $stored = $secrets->encrypt(SecretsFixture::config(['token' => 'stored-token']));

        $merged = $secrets->keepStored(
            SecretsFixture::config(['password' => 'new-password', 'token' => null]),
            $stored,
        );

        self::assertSame('new-password', $merged['password']);
        self::assertArrayNotHasKey('token', $merged);
    }

    public function testUpdateDoesNotResurrectRemovedParentOrCrossTypes(): void
    {
        $secrets = SecretsFixture::configSecrets();
        $stored = $secrets->encrypt(SecretsFixture::config([
            'oauth' => ['client_id' => 'app', 'client_secret' => 'oauth-secret'],
        ]));

        $withoutOauth = SecretsFixture::config(['password' => ConfigSecrets::MASK]);
        self::assertArrayNotHasKey('oauth', $secrets->keepStored($withoutOauth, $stored));

        $otherType = ['type' => 'other', 'endpoint_type' => 'rest'];
        self::assertSame($otherType, $secrets->keepStored($otherType, $stored));
    }

    public function testFailsLoudlyWithoutKey(): void
    {
        $secrets = SecretsFixture::configSecrets(
            static fn (): CredentialEncryption => new CredentialEncryption(''),
        );

        $this->expectException(SecretEncryptionException::class);
        $secrets->encrypt(SecretsFixture::config());
    }

    public function testConfigWithoutSecretsNeedsNoKey(): void
    {
        $secrets = SecretsFixture::configSecrets(
            static fn (): CredentialEncryption => throw new \LogicException('key must not be needed'),
        );
        $config = SecretsFixture::config();
        unset($config['password']);

        self::assertSame($config, $secrets->encrypt($config));
        self::assertSame($config, $secrets->decrypt($config));
        self::assertSame($config, $secrets->mask($config));
    }

    public function testDecryptWithWrongKeyFails(): void
    {
        $encrypted = SecretsFixture::configSecrets()->encrypt(SecretsFixture::config());
        $otherKey = SecretsFixture::configSecrets(
            static fn (): CredentialEncryption => new CredentialEncryption(str_repeat('x', SODIUM_CRYPTO_SECRETBOX_KEYBYTES)),
        );

        $this->expectException(SecretEncryptionException::class);
        $otherKey->decrypt($encrypted);
    }
}
