<!-- file generated with AI assistance: Claude Code - 2026-10-05 10:25:39 UTC -->

# Changelog

## Unreleased

Secure storage and transport of the credentials in `configJson` ([Issue #8](https://github.com/dmstr/api-configuration-bundle/issues/8)). Release as a new minor version: the changes below break consumers in the ways listed under "Breaking changes".

### Breaking changes

- **Reads require `ROLE_ADMIN`.** `GET /api/admin/api_configurations`, `GET /api/admin/api_configurations/{id}` and `GET /api/admin/api_configurations/{id}/health` answer `403` for non-admin users (before: `ROLE_USER`). There is no option to restore the old behaviour.
- **Secrets are masked in responses.** Keys marked `writeOnly` in the type schema come back as `********`, also for administrators. Clients that read credentials over the API no longer get them.
- **Secrets are encrypted at rest.** `ApiConfiguration::getConfigJson()` returns `enc:v1:…` for marked keys after a flush. Code that reads secrets from the entity directly (instead of through `ApiClientFactory`) has to decrypt with `ConfigSecrets::decrypt()`, for example an `AuthorizableExtensionInterface` that reads a client secret in `buildAuthorizeUrl()`.
- **A usable encryption key is required** as soon as a configuration holds a marked secret: `dmstr_api_platform_utils.credential_encryption.key: '%env(base64:CREDENTIALS_ENCRYPTION_KEY)%'`. Without it, persisting or using the secret throws `SecretEncryptionException`.
- **Constructor signatures**: `ApiClientFactory`, `ApiConfigurationValidator` and `CreateApiConfigurationCommand` take an additional `ConfigSecrets` argument (only relevant when they are instantiated manually instead of autowired).

### Added

- Secret keys are declared in the type schema with `"writeOnly": true`; the existing type schemas in the consuming bundles and applications need the marker on their `password`, `token`, `client_secret` and equivalent keys.
- Encryption at rest of the marked keys with `CredentialEncryption` (Doctrine `onFlush` listener).
- Masking of the marked keys in API responses and in the validator's logs.
- Update rule: an omitted secret or the mask keeps the stored value, `null` removes it.
- Console command `app:api-configuration:encrypt-secrets` (`--dry-run`) to encrypt the secrets of existing rows, idempotent.

### Fixed

- `app:api:test-connection` called the non-existent `ApiConfiguration::getCredentials()` and failed with a PHP error; it now builds the client from the entity.

## 0.4.0 and earlier

See git history.
