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

## Decision log

Settled calls worth not re-litigating. This file is the log — there is no separate decisions file.

- **Deregistration matches WP, plus a nullable priority.** `removeFilter` / `removeAction` default to priority `10` and only touch that bucket, exactly like `remove_filter()` in WordPress, so WP users get no surprises. Passing `null` as the priority is a deliberate extension that strips the callback from every priority of the hook, for callers who do not want to track registration priorities themselves. Rejected: callback-identity-only removal (cannot target one of two registrations of the same callback) and strict WP parity with no null (forces callers to bookkeep priorities).
- **Callbacks are matched by identity (`===`), not normalized.** A `Class::method` string does not match a `[Class::class, 'method']` array registration, unlike WordPress, which normalizes both to a single id. Normalization would mean hashing callables on every `addFilter` call; identity matching keeps registration free and is sufficient when the caller holds the reference it registered. Documented in the README so the limitation is explicit.
- **Removal during dispatch does not affect the in-flight run.** `applyFilter`'s `foreach` iterates a copy-on-write snapshot of the priority buckets, so a callback that removes a later callback does not prevent it from running in that same dispatch; the change applies from the next dispatch. WordPress re-reads and would skip it. Changing this means restructuring the dispatch loop, which was not worth it — the behaviour is tested and documented rather than left implicit.
- **`Hook::forgetEmptyHook()` exists for memory hygiene, not behaviour.** An emptied hook is already indistinguishable from an unregistered one through the public API (`getMinPriority` returns `null`, `applyFilter` short-circuits on a falsy bucket, and `addFilter` clears the sort marker on every add). Pruning keeps `$filters` and `$sortedHooks` from growing in long-running processes. Its tests assert the internal arrays via reflection because nothing else can observe it.

## TODO / backlog

Discussed but not yet done. Pick these up as maintenance opportunities.

- **WordPress-parity API gaps.** Deregistration landed (`removeAction`, `removeFilter`, `removeAllActions`, `removeAllFilters`). Still missing: `hasFilter`, `hasAction`, `didAction`, and exposing the running-hook stack as `currentFilter` / `doingAction` (`Hook::$runningHooks` already holds exactly what those need). Each is small, and each needs its own coverage + mutation tests.
- **`requireTypedParameters` is a magic string.** It is plucked out of `...$arg` by name in `Hook::applyFilter`, which works only because variadics collect named arguments. It silently collides with any hook that wants an argument of that name, and it is invisible in the signature. A dedicated method or an options object would be honest; changing it is a breaking change, so it needs a major.
- **Runtime `$acceptedArgs` deprecation.** The parameter is marked `@deprecated No longer used - kept for backwards compatibility` in `Hook::addAction` / `Hook::addFilter` and their `HookInterface` counterparts, but nothing tells users at runtime. Emit `trigger_error(..., E_USER_DEPRECATED)` when it's passed as a non-default value, so callers get a real signal before it's removed in a future major.
- **CI PHP matrix.** `.github/workflows/release.yml` only tests on PHP `8.2`. Add a matrix over `8.2`, `8.3`, `8.4` so cross-version breakage is caught before release.
- **Neutral composer scripts.** `test-coverage-local` and `test-mutate-local` in `composer.json` hard-code `herd coverage`, which only exists on Laravel Herd (macOS). Add generic `test` / `analyse` aliases that work with any coverage driver so non-Herd contributors are not blocked.
- **`composer.lock` in a library repo.** Currently committed. Most PHP libraries do not commit it (it does not affect consumers and just churns on dev updates). Worth a deliberate keep-or-drop decision.
