# CoCart PHP SDK

Official PHP SDK for the CoCart REST API.

- **Package:** `cocart-headless/cocart-php`
- **Version:** 1.0.0
- **Distribution:** Packagist
- **License:** MIT
- **Requires:** PHP 8.2+

---

## Commands

```bash
composer install                          # install dependencies
vendor/bin/pest                           # run all tests
vendor/bin/pest tests/Unit/CoCartTest.php # run a single test file
vendor/bin/pest --fail-on-deprecation     # run tests, fail on deprecations
```

No separate build, lint, or format commands are configured.

---

## Tech Stack

| | |
|---|---|
| Language | PHP 8.2+ (tested on 8.2, 8.3, 8.4) |
| Tests | Pest PHP 3+ |
| HTTP (optional) | Guzzle 7+ (`guzzlehttp/guzzle`) |
| HTTP (built-in) | cURL adapter, stream adapter, WordPress HTTP API adapter |
| Mocking | Mockery 1.6+ |
| Autoloading | PSR-4 — `CoCart\` → `src/` |
| Config | `composer.json`, `phpunit.xml`, `.editorconfig` |

---

## Project Structure

```
src/
  CoCart.php              # main client class
  CoCartInterface.php     # public interface (for mocking)
  JwtManager.php
  SessionManager.php
  CurrencyFormatter.php
  TimezoneHelper.php
  Endpoints/              # Cart.php, Products.php, Sessions.php, Batch.php, Store.php
  Http/                   # GuzzleAdapter, CurlAdapter, StreamAdapter, WordPressAdapter
  Storage/                # CookieStorage, FileStorage, PhpSessionStorage
  Exceptions/             # exception classes
tests/
  Unit/                   # one test file per source class
  Pest.php                # Pest configuration
phpunit.xml               # PHPUnit / Pest bootstrap and suite config
```

---

## Code Style

- **Namespace:** `CoCart\` (PSR-4)
- **Classes:** `PascalCase`
- **Methods:** `camelCase`
- **Constants:** `SCREAMING_SNAKE_CASE`
- **Indentation:** 4 spaces (`.editorconfig`)
- **Line endings:** LF

---

## Git

- **Commit style:** Imperative, capital first letter — `Add X`, `Added X`, `Fix X`
- **Co-author footer:** `Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>`

---

## Testing

| | |
|---|---|
| Framework | Pest PHP 3 |
| Location | `tests/Unit/` |
| File pattern | `*Test.php` |
| Mocking | Mockery |
| Coverage cache | `.phpunit.cache/` |

Each source class has a corresponding test file (`CoCart.php` → `CoCartTest.php`). Uses Pest's fluent assertion API. Run a specific test: `vendor/bin/pest --filter "it adds item to cart"`.
