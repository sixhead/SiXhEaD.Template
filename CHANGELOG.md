# Changelog

## 6.2.0 - 2026-11-10

- Requires PHP 8.5. Runs with `error_reporting(E_ALL)` without warnings or deprecations.
- Fix: a hidden parent block that contains an applied child block is shown again (broken in 6.1.0, e.g. `block-advance.php`).
- Fix: `ignore('no')` keeps only `$no`, not `$note`.
- Fix: literal `%0A`, `%0D`, `%09` and `{%24}` text in a template is no longer changed.
- Fix: an empty template file returns an empty string instead of a `ValueError`.
- Fix: sub parts belong to their block, so `applyBlock('other')` after `sub()` uses the right HTML.
- Change: missing files, blocks and sub parts throw `RuntimeException` instead of printing a debug message and calling `exit`.
- Change: `apply()`, `hide()`, `sub()` and `getCurrentSub*()` without `block()` throw `LogicException`.
- Change: `assign()` accepts `string|int|float|bool|Stringable|null`. Arrays and other values in `$GLOBALS` become an empty string.
- Change: license is now MIT.
- Change: the class file moved from `module/SiXhEaD.Template.php` to `SiXhEaD.Template.php` at the project root. Examples moved to `examples/`.
- Change: examples use the default key `template` (`<!--template:name-->`) and no longer include Bootstrap or jQuery.
- Add PHPUnit tests for every example, PHPStan level 9 and PHP CS Fixer config.
- Add README with an nginx rule that blocks web access to template files.
- Add Redis examples (phpredis 6 options array). Memcached and Redis examples read the server host from `MEMCACHED_HOST` / `REDIS_HOST` and share `_tp_cache.html`.

## 6.1.0

- Default key changed from `SiXhEaD` to `template`.
- `display()` removes `<!-- tp ... -->` / `<!-- template ... -->` comments and blank lines.
