<!-- file generated with AI assistance: Claude Code - 2026-06-09 19:27:00 UTC -->

# dmstr/api-configuration-bundle

Manage external API connections as Doctrine entities.

## Features (planned)

- `ApiConfiguration` entity — type, credentials (encrypted at rest, masked on read), endpoint config
- Custom operations: `health`, `authorize`, `test-connection`
- `ApiExtensionRegistry` — discoverable adapter pattern via tag
- `ApiExtensionInterface` / `AuthorizableExtensionInterface` — adapters
  implement these to plug in
- OAuth callback controller for `authorize` flow
- CLI mirrors: `api-configuration:create`, `:health`, `:test-connection`

## API clients

An extension implements `ApiExtensionInterface` (tagged automatically) and builds a client implementing `ApiClientInterface`, which holds only what every source supports. Optional capabilities are separate interfaces; implement those the source really supports, callers check `instanceof`:

| Interface | Methods |
|---|---|
| `CustomerAwareApiClientInterface` | `getCustomers()` |
| `TodoListAwareApiClientInterface` | `getTodoLists()` |
| `ChangeProbeInterface` | `hasChanges()` — cheap, fail open; without it a source counts as always changed |
| `UserAwareApiClientInterface` | `getUsers()` |

`authenticate()` returns nothing and throws `AuthenticationFailedException` with the original exception as `previous`.

`ApiClientFactory::create()` validates the configuration against the extension's `schema.json` before calling `createClient()`, so extensions need no `isset()` checks of their own; an invalid configuration throws `InvalidConfigurationException` naming the field.

### Health checks

`ApiConfigurationHealthChecker` serves the health route, `app:api-configuration:health` and application dashboards. For a type with a `HealthProbeInterface` (tagged automatically) it calls the probe with the decrypted configuration; otherwise it builds the client and calls `getHealthInfo()`. Types without a full client, such as schema-only connection types, only need a probe.

## Security

### Access

Every operation of the `ApiConfiguration` resource under `/api/admin/api_configurations` — reads and the `health` and `authorize` sub-resources included — requires `ROLE_ADMIN`, because `configJson` holds the credentials for the upstream systems.

### Secrets in `configJson`

A type marks its secret keys in its own `schema.json` with the standard JSON Schema annotation `writeOnly`:

```json
{
    "properties": {
        "username": { "type": "string" },
        "password": { "type": "string", "writeOnly": true },
        "oauth": {
            "type": "object",
            "properties": {
                "client_secret": { "type": "string", "writeOnly": true }
            }
        }
    }
}
```

Marked keys are found in nested objects, local `$ref`s, `allOf`/`anyOf`/`oneOf`, `if`/`then`/`else`, array `items` and `additionalProperties`. The schema of a type is looked up by schema provider name, then by extension name, then by the `type` const of the registered schemas; for an unknown type the marked keys of all schemas apply, so a missing mapping over-masks instead of leaking.

For marked keys the bundle guarantees:

- **Encrypted at rest.** Values are stored as `enc:v1:<ciphertext>` (libsodium secretbox via `Dmstr\ApiPlatformUtils\Service\CredentialEncryption`), whoever writes the entity — API, console or application code (Doctrine `onFlush` listener).
- **Masked on read.** API responses contain `********` instead of the value, also for administrators. Logs of the configuration validator are masked the same way.
- **Kept on update.** In `PUT` and `PATCH`, an omitted key or the mask `********` keeps the stored value, `null` removes the key, any other value replaces it. Nothing is carried over when `type` changes.
- **Decrypted only for the client.** `ApiClientFactory::create()` and `createFromEntity()` decrypt before calling `ApiExtensionInterface::createClient()`. Code that reads secrets from `ApiConfiguration::getConfigJson()` itself (for example an `AuthorizableExtensionInterface` reading a client secret) must decrypt with `Dmstr\ApiConfiguration\Security\ConfigSecrets::decrypt()`.

### Encryption key

The key comes from `dmstr/api-platform-utils-bundle`. `CredentialEncryption` expects the 32 raw key bytes, the generated key is base64, so decode it in the configuration:

```yaml
# config/packages/dmstr_api_platform_utils.yaml
dmstr_api_platform_utils:
    credential_encryption:
        key: '%env(base64:CREDENTIALS_ENCRYPTION_KEY)%'
```

```bash
bin/console dmstr:generate-encryption-key   # prints CREDENTIALS_ENCRYPTION_KEY=...
```

Without a usable key (missing, wrong length, not base64), storing or using a secret fails with `Dmstr\ApiConfiguration\Security\SecretEncryptionException`; nothing is stored in clear. Configurations without secrets keep working. Changing the key makes existing secrets unreadable.

### Existing rows

Rows stored before encryption at rest hold their secrets in clear. Encrypt them once after the upgrade; the command is idempotent and leaves encrypted values alone, so it can also run on every deployment:

```bash
bin/console app:api-configuration:encrypt-secrets --dry-run
bin/console app:api-configuration:encrypt-secrets
```

Rows that are updated through Doctrine are encrypted on the next flush anyway.

## License

MIT © diemeisterei GmbH
