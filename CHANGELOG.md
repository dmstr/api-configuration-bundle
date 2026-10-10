<!-- file generated with AI assistance: Claude Code - 2026-10-08 13:38:06 UTC -->

# Changelog

All notable changes to this bundle. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow [SemVer](https://semver.org/) and come from the Git tags.

## [0.5.0] - 2026-10-10

Secure storage and transport of the credentials in `configJson` ([Issue #8](https://github.com/dmstr/api-configuration-bundle/issues/8)) and a slimmer client contract ([Issue #9](https://github.com/dmstr/api-configuration-bundle/issues/9)). The changes break consumers in the ways listed under "Breaking changes". 0.5.0-beta1 contained everything below except the removal of `order[id]` ("Changed").

### Breaking changes

- **Reads require `ROLE_ADMIN`.** `GET /api/admin/api_configurations`, `GET /api/admin/api_configurations/{id}` and `GET /api/admin/api_configurations/{id}/health` answer `403` for non-admin users (before: `ROLE_USER`). There is no option to restore the old behaviour.
- **Secrets are masked in responses.** Keys marked `writeOnly` in the type schema come back as `********`, also for administrators. Clients that read credentials over the API no longer get them.
- **Secrets are encrypted at rest.** `ApiConfiguration::getConfigJson()` returns `enc:v1:…` for marked keys after a flush. Code that reads secrets from the entity directly (instead of through `ApiClientFactory`) has to decrypt with `ConfigSecrets::decrypt()`, for example an `AuthorizableExtensionInterface` that reads a client secret in `buildAuthorizeUrl()`.
- **A usable encryption key is required** as soon as a configuration holds a marked secret: `dmstr_api_platform_utils.credential_encryption.key: '%env(base64:CREDENTIALS_ENCRYPTION_KEY)%'`. Without it, persisting or using the secret throws `SecretEncryptionException`.
- **`ApiClientInterface` is slimmer** (Issue #9). `getCustomers()`, `getTodoLists()` and `hasChanges()` moved to the capability interfaces `CustomerAwareApiClientInterface`, `TodoListAwareApiClientInterface` and `ChangeProbeInterface`. Clients implement only what their source supports and drop their stubs (`return []`, `return true`); callers check `instanceof` and treat a client without `ChangeProbeInterface` as "always changed".
- **`authenticate(): void` throws** (Issue #9) `AuthenticationFailedException` with the transport exception as `previous`, instead of returning `false`. Implementations rethrow via `AuthenticationFailedException::fromPrevious($apiName, $e)`; callers catch instead of checking the return value.
- **`ApiClientFactory::create()` validates the configuration** against the extension's `schema.json` before `createClient()` (Issue #9) and throws `InvalidConfigurationException` (an `\InvalidArgumentException`) naming the failing field. Configurations that an extension accepted despite its own schema are now rejected. Keys starting with `_` (`_apiConfigurationId`) are not validated.
- **Constructor signatures**: `ApiClientFactory`, `ApiConfigurationValidator` and `CreateApiConfigurationCommand` take an additional `ConfigSecrets` argument; `ApiConfigurationHealthProvider` and `ApiConfigurationHealthCommand` take `ApiConfigurationHealthChecker` instead of `ApiClientFactory` and `HealthNormalizer` (only relevant when they are instantiated manually instead of autowired).

### Added

- Secret keys are declared in the type schema with `"writeOnly": true`; the existing type schemas in the consuming bundles and applications need the marker on their `password`, `token`, `client_secret` and equivalent keys.
- Encryption at rest of the marked keys with `CredentialEncryption` (Doctrine `onFlush` listener).
- Masking of the marked keys in API responses and in the validator's logs.
- Update rule: an omitted secret or the mask keeps the stored value, `null` removes it.
- Console command `app:api-configuration:encrypt-secrets` (`--dry-run`) to encrypt the secrets of existing rows, idempotent.
- `HealthProbeInterface` (Issue #9): a health check per type without the project-management client methods, autoconfigured. Schema-only types (no `ApiExtensionInterface`) answer the health route and command instead of "Unsupported API name"; a probe takes precedence over the client-based check.
- `ApiConfigurationHealthChecker`: the health check shared by the health route, the health command and application dashboards.

### Changed

- `GET /api/admin/api_configurations` no longer offers `order[id]`. The identifier is a random UUID v4, so sorting by it has no meaning; admin UIs that derive sortable columns from the offered `order[...]` parameters no longer show a sort control for it. Requests that still send `order[id]` are not sorted by it.

### Fixed

- `GET /api/admin/api_configurations/{id}/health` in JSON-LD returned `metadata` and `error` as `hydra:Collection` without their keys (Issue #9); the operation now normalizes nested arrays raw (`api_sub_level`).
- Write operations returned HTTP 500 (`anyOf must have at least one element`) when no configuration schema was registered ([Issue #5](https://github.com/dmstr/api-configuration-bundle/issues/5)); the validator now reports a violation (HTTP 422). The root cause, the empty `anyOf` from `SchemaRegistry`, is tracked in dmstr/openapi-json-schema-bundle#3.
- `symfony/validator` is declared as a dependency; it was used directly but only installed transitively.
- `app:api:validate-file` called the non-existent `ApiConfiguration::getFileConfig()` (a leftover from file configurations with a `format` key) and failed with a PHP error; it now builds the client and delegates to `FileApiClientInterface::validateFile()` and `parseFile()`, so every file type validates its own format.
- `app:api:test-connection` called the non-existent `ApiConfiguration::getCredentials()` and failed with a PHP error; it now builds the client from the entity.

## 0.4.0 and earlier

See the git history.

[0.5.0]: https://github.com/dmstr/api-configuration-bundle/compare/0.4.0...0.5.0
