<!-- file generated with AI assistance: Claude Code - 2026-06-09 19:27:00 UTC -->

# dmstr/api-configuration-bundle

Manage external API connections as Doctrine entities.

## Features (planned)

- `ApiConfiguration` entity — type, credentials (encrypted), endpoint config
- Custom operations: `health`, `authorize`, `test-connection`
- `ApiExtensionRegistry` — discoverable adapter pattern via tag
- `ApiExtensionInterface` / `AuthorizableExtensionInterface` — adapters
  implement these to plug in
- OAuth callback controller for `authorize` flow
- CLI mirrors: `api-configuration:create`, `:health`, `:test-connection`

## License

MIT © diemeisterei GmbH
