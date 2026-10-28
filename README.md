<!-- file generated with AI assistance: Claude Code - 2026-06-09 19:27:00 UTC -->

# dmstr/api-configuration-bundle

Manage external API connections as Doctrine entities.

## Features

- `ApiConfiguration` entity (`name`, `type`, `endpointType`, `configJson`, `active`) under `/api/admin/api_configurations`, `configJson` validated against the JSON schema of its type
- Secrets in `configJson` encrypted at rest and masked on read, see [Security](#security)
- Sub-resources `GET /api/admin/api_configurations/{id}/health` and `GET /api/admin/api_configurations/{id}/authorize`, OAuth callback at `/api/oauth/callback`
- `ApiExtensionRegistry`: extensions implement `ApiExtensionInterface` (and `AuthorizableExtensionInterface` for OAuth) and are registered by tag
- Console commands `app:api:create`, `app:api:test-connection`, `app:api:validate-file`, `app:api-configuration:health` and `app:api-configuration:encrypt-secrets`

Changes per version are listed in [CHANGELOG.md](CHANGELOG.md).

## Requirements

PHP >= 8.5 and `symfony/security-bundle` with a firewall in front of `/api/admin`, see [Access](#access).

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

Every operation of the `ApiConfiguration` resource under `/api/admin/api_configurations` checks a permission attribute with `is_granted()`, because `configJson` holds the credentials for the upstream systems:

| Permission | Operations |
|---|---|
| `apiconfig:read` | `GET` collection and item, `GET /api/admin/api_configurations/{id}/health` |
| `apiconfig:write` | `POST`, `PUT`, `PATCH`, `DELETE` |
| `apiconfig:authorize` | `GET /api/admin/api_configurations/{id}/authorize`, also the browser path (`Accept: text/html`, answered with a `303` redirect) |

The constants are in `Dmstr\ApiConfiguration\Security\ApiConfigurationPermissions` (`READ`, `WRITE`, `AUTHORIZE`, `all()`). The bundle also registers the three attributes as `za7_core.permission` service tags (attribute `permission`), so applications that collect such tags can offer them for assignment; the bundle itself depends on nothing for this.

The bundle grants none of the permissions itself. A permission voter in the application maps its roles or users to the permissions; without one, every operation answers `403`. Denied callers get `403` before any `404`, anonymous callers `401` from the firewall. Credentials stay masked for every caller, and the `hydra:operation` list of an item only shows the operations the caller is allowed.

#### Migrating from 0.5

0.5 checked a fixed role; 0.6 checks the permissions above, so an application without a voter for them loses all access after the upgrade. A minimal voter granting all three permissions to an administrator role, with `<your-admin-role>` replaced by the role your application uses:

```php
use Dmstr\ApiConfiguration\Security\ApiConfigurationPermissions;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class ApiConfigurationPermissionVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, ApiConfigurationPermissions::all(), true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return in_array('<your-admin-role>', $token->getRoleNames(), true);
    }
}
```

With autoconfiguration the voter is registered by itself. If the application already has a permission voter driven by a role-to-permission map, add the three attributes to the entry of `<your-admin-role>` instead. Clients that open the authorize URL in a browser need `apiconfig:authorize` as well; before 0.6 any authenticated caller got the redirect.

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
