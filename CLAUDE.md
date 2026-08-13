# CLAUDE.md

## Testing

Coverage and mutation testing must both pass at 100%. Always run both after making changes.

```bash
# Coverage
herd coverage ./vendor/bin/pest --coverage

# Mutation testing
herd coverage ./vendor/bin/pest --mutate --parallel --everything --covered-only
```

## Static analysis

PHPStan runs at level `max` against `src/` and must report zero errors. No `ignoreErrors` and no `@phpstan-ignore` comments — fix the underlying type shape instead.

```bash
./vendor/bin/phpstan analyse
```

## TODO / backlog

Discussed but not yet done. Pick these up as maintenance opportunities.

- **WordPress-parity API gaps.** No `removeAction`, `removeFilter`, `hasFilter`, `hasAction`, or `didAction`. If the library is meant to feel WP-style, these are the obvious missing verbs. Each is small, and each needs its own coverage + mutation tests.
- **Runtime `$acceptedArgs` deprecation.** The parameter is marked `@deprecated No longer used - kept for backwards compatibility` in `Hook::addAction` / `Hook::addFilter` and their `HookInterface` counterparts, but nothing tells users at runtime. Emit `trigger_error(..., E_USER_DEPRECATED)` when it's passed as a non-default value, so callers get a real signal before it's removed in a future major.
- **CI PHP matrix.** `.github/workflows/release.yml` only tests on PHP `8.2`. Add a matrix over `8.2`, `8.3`, `8.4` so cross-version breakage is caught before release.
- **Neutral composer scripts.** `test-coverage-local` and `test-mutate-local` in `composer.json` hard-code `herd coverage`, which only exists on Laravel Herd (macOS). Add generic `test` / `analyse` aliases that work with any coverage driver so non-Herd contributors are not blocked.
- **`composer.lock` in a library repo.** Currently committed. Most PHP libraries do not commit it (it does not affect consumers and just churns on dev updates). Worth a deliberate keep-or-drop decision.
