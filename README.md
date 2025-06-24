# Kirby Tools Licensing Backend

Backend licensing system for all commercial [Kirby Tools plugins](https://kirby.tools). This package provides server-side license validation, activation, and management for Kirby Tools plugins within the Kirby CMS ecosystem.

## What It Does

This licensing backend handles:

- **License Validation**: Validates Kirby Tools license keys against the remote API
- **Plugin Integration**: Seamlessly integrates with Kirby 5's native plugin license system
- **Version Compatibility**: Checks license compatibility with plugin versions using semantic versioning
- **License Management**: Handles license activation, storage, and status reporting
- **Automatic Updates**: Refreshes license data when plugin versions change

## Classes

### `Licenses`

The main licensing engine that:

- Reads and writes license data to `.kirby-tools-licenses` file
- Communicates with the Kirby Tools API at `https://repo.kirby.tools/api`
- Validates license keys using the pattern `KT{version}-{hash}-{hash}`
- Manages license status: `active`, `inactive`, `invalid`, `incompatible`, `upgradeable`
- Handles license activation via email and order ID

### `PluginLicense`

Kirby 5 integration adapter that:

- Extends Kirby's native [`License`](https://getkirby.com/docs/reference/objects/plugin/license) class
- Maps internal license statuses to [Kirby's license status system](https://getkirby.com/releases/5/plugin-license)
- Provides standardized license display in the Kirby Panel
- Integrates with Kirby's plugin management interface

## Usage in Kirby Tools Plugins

This package is automatically included in all commercial Kirby Tools plugins and provides the licensing infrastructure that enables:

- Trial periods and license enforcement
- [Panel integration for license status display](https://getkirby.com/releases/5/plugin-license)
- Seamless license activation from within Kirby
- Version-based license compatibility checking

For Panel-side licensing UI components, see [@kirby-tools/licensing](https://github.com/kirby-tools/licensing-panel).

## License

[AGPL](./LICENSE) License © 2024-PRESENT [Johann Schopplich](https://github.com/johannschopplich)
