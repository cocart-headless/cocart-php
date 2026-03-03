<!-- CoCart SDK Support Policy Template v1 -->

# Support & Versioning Policy

> **Note:** This SDK is currently in development. The full support lifecycle (maintenance phase for previous major versions, EOL (End-of-Life) grace periods) takes effect once the SDK is declared stable and production-ready.

## Versioning

This SDK follows [Semantic Versioning](https://semver.org/) (SemVer):

- **Major** (X.0.0) — Breaking changes to the public API
- **Minor** (x.Y.0) — New features that are backward-compatible
- **Patch** (x.y.Z) — Bug fixes and security patches

Only the **latest major version** receives active development. Older major versions remain available for install but receive no updates. Migration guides are provided in the `docs/` folder for major version upgrades.

### What constitutes a breaking change

- Removing or renaming a public class, method, or interface
- Changing required parameters of a public method
- Changing return types or adding union types to existing return types
- Removing a public exception class
- Dropping a PHP version from the supported matrix

### What is NOT a breaking change

- Adding new optional parameters to existing methods
- Adding new classes, methods, or response fields
- Internal refactors that do not affect the public API
- Adding a new PHP version to the supported matrix
- Bug fixes that correct behavior to match documentation

## SDK Lifecycle

| Phase | Description | Duration |
|---|---|---|
| **Active** | New features, bug fixes, security patches | Current major version |
| **Maintenance** | Security patches and critical bug fixes only | Previous major version, 12 months |
| **Deprecated** | No updates; remains installable | After maintenance ends |

## Supported PHP Versions

| PHP | Status | SDK Support | Notes |
|---|---|---|---|
| 8.4 | Active | Supported | Tested in CI |
| 8.3 | Active | Supported | Tested in CI |
| 8.2 | Security-only | Minimum version | Tested in CI |
| 8.1 and below | EOL | Not supported | |

### Version support policy

We support all PHP versions that are in **active support** or **security-only** status according to the [PHP Supported Versions](https://www.php.net/supported-versions.php) page.

- **Adding new versions:** When a new PHP version is released (typically each November), we add CI testing and official support within 3 months.
- **Dropping old versions:** When a PHP version's security support ends, we continue supporting it for **6 months**, then drop it in the next minor or major SDK release.

## Deprecation Notices

We communicate deprecations through:

1. **PHPDoc tags** — `@deprecated` annotations recognized by IDEs and static analysis tools
2. **Runtime warnings** — `trigger_error('...', E_USER_DEPRECATED)` so users see the warning during execution
3. **Changelog entry** — Every deprecation is noted in release notes
4. **Minimum one minor release** — A deprecation warning ships at least one minor version before the deprecated feature is removed
5. **Migration guide** — Major version upgrades include a migration guide in the `docs/` folder

## Getting Help

- **Documentation:** https://cocartapi.com/docs
- **Community:** https://cocartapi.com/community
- **Issues:** https://github.com/cocart-headless/cocart-php/issues
