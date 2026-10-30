# CLAUDE.md

## Testing

Coverage and mutation testing must both pass at 100%. Always run both after making changes.

```bash
# Coverage
herd coverage ./vendor/bin/pest --coverage

# Mutation testing
herd coverage ./vendor/bin/pest --mutate --parallel --everything --covered-only
```

Both are also wired as composer scripts with the thresholds attached, so the gate is the same locally and in CI. On this machine use the `-local` variants, which wrap the same commands in `herd coverage`; the plain ones need a coverage driver already enabled in the PHP binary and are what CI runs.

```bash
composer check-local    # analyse + coverage + mutation, via herd
composer check          # same, needs Xdebug/PCOV enabled
composer analyse        # phpstan only, no driver needed
```

Note the mutation scripts omit `--parallel` on purpose — see the decision log.

## Static analysis

PHPStan runs at level `max` against `src/` and must report zero errors. No `ignoreErrors` and no `@phpstan-ignore` comments — fix the underlying type shape instead.

```bash
./vendor/bin/phpstan analyse
```

## Decision log

Settled calls worth not re-litigating. This file is the log — there is no separate decisions file.

- **WordPress parity is considered complete.** Deregistration shipped in 2.12.0 (`removeAction`, `removeFilter`, `removeAllActions`, `removeAllFilters`) and introspection right after (`hasFilter`/`hasAction`, `didFilter`/`didAction`, `currentFilter`/`currentAction`, `doingFilter`/`doingAction`). What is left in WordPress is either array-variant sugar made redundant by variadics (`apply_filters_ref_array`, `do_action_ref_array`) or global-registry behaviour that does not apply to an instance-based library (the `all` hook, `merge_filters`). Do not add more WP verbs speculatively — wait for a consumer to ask.
- **Deregistration matches WP, plus a nullable priority.** `removeFilter` / `removeAction` default to priority `10` and only touch that bucket, exactly like `remove_filter()` in WordPress, so WP users get no surprises. Passing `null` as the priority is a deliberate extension that strips the callback from every priority of the hook, for callers who do not want to track registration priorities themselves. Rejected: callback-identity-only removal (cannot target one of two registrations of the same callback) and strict WP parity with no null (forces callers to bookkeep priorities).
- **Callbacks are matched by identity (`===`), not normalized.** A `Class::method` string does not match a `[Class::class, 'method']` array registration, unlike WordPress, which normalizes both to a single id. Normalization would mean hashing callables on every `addFilter` call; identity matching keeps registration free and is sufficient when the caller holds the reference it registered. Documented in the README so the limitation is explicit.
- **Removal during dispatch does not affect the in-flight run.** `applyFilter`'s `foreach` iterates a copy-on-write snapshot of the priority buckets, so a callback that removes a later callback does not prevent it from running in that same dispatch; the change applies from the next dispatch. WordPress re-reads and would skip it. Changing this means restructuring the dispatch loop, which was not worth it — the behaviour is tested and documented rather than left implicit.
- **Sonar is advisory; it must never gate a release.** The scan runs in its own `sonar` job with `continue-on-error: true`, and `release` depends only on `test`. This is not a style preference — an expired `SONAR_TOKEN` produced an HTTP 403 before a single file was analysed and blocked the 2.12.0 release on 2026-10-30, even though PHPStan, the suite, coverage and mutation all passed. The real gates live in the `test` job; Sonar is a second opinion on top of them.
- **Mutation testing runs serial everywhere it is automated.** `--parallel` only works when pest is invoked directly in an interactive shell. Spawned through composer — and therefore anywhere a script or CI step wraps it — the workers do not inherit the coverage-enabled environment, so `--covered-only` finds nothing and the run reports `0 Mutations for 0 Files created` / `Score: 0.00%` rather than erroring. Combined with `--min=100` that is a hard failure, so it fails loudly rather than passing a lie, but it blocks a release for no reason. The composer scripts and the CI step therefore omit `--parallel`: 43s serial versus 5s parallel on 158 mutations, which is a fine trade for determinism. The hand-run command at the top of this file keeps `--parallel` because typing it in a terminal does work.
- **CI enforces the 100% gates, it does not just report them.** The `test` job runs `pest --coverage --min=100` and `pest --mutate ... --min=100`, both of which exit non-zero below the threshold (verified). Before this, CI ran the suite and generated a coverage report but nothing checked the numbers, so the quality bar this file states was enforced only by whoever remembered to run it locally.
- **`hasFilter` returns `bool`, not WP's `int|false`.** WordPress's `has_filter()` returns the priority when a callback is found and `false` otherwise, a union that is awkward under PHPStan at max and easy to misuse with a loose comparison (priority `0` is falsy). We return a plain `bool` and let the caller pass the priority in as the third argument. If "which priority is this callback at?" is ever actually needed, add a separate `getPriority(): ?int` rather than overloading the return.
- **Introspection verbs ship as `*Filter`/`*Action` alias pairs.** `hasFilter`/`hasAction`, `didFilter`/`didAction`, `currentFilter`/`currentAction`, `doingFilter`/`doingAction`. All eight names exist in WordPress, and the library already pairs `addAction`/`addFilter` and `removeAction`/`removeFilter` this way, with the filter variant implementing and the action variant forwarding. Since `doAction` forwards to `applyFilter`, the two halves of each pair are genuinely the same thing — including the `didFilter`/`didAction` counter, which is shared per hook name.
- **`composer.lock` is not committed, and the old backlog entry claiming otherwise was wrong.** `git log --all -- composer.lock` is empty: it has never been tracked, and `.gitignore` has ignored it all along. The keep-or-drop decision is therefore already settled in favour of "drop", which is the norm for libraries. Note the consequence: CI runs `composer install` with no lock file, so every run resolves the newest versions inside the declared constraints. For a library that is a feature — it catches breakage against what consumers will actually get — but it does mean CI is not byte-reproducible, and a dependency release can turn CI red without a commit.
- **`didFilter` counts dispatches, not deliveries.** The counter is incremented at the top of `applyFilter` before the no-callbacks early return, so a hook that nobody listens to still counts as fired, matching `did_action()` in WordPress. Removing callbacks does not reset it.
- **`Hook::forgetEmptyHook()` exists for memory hygiene, not behaviour.** An emptied hook is already indistinguishable from an unregistered one through the public API (`getMinPriority` returns `null`, `applyFilter` short-circuits on a falsy bucket, and `addFilter` clears the sort marker on every add). Pruning keeps `$filters` and `$sortedHooks` from growing in long-running processes. Its tests assert the internal arrays via reflection because nothing else can observe it.

## TODO / backlog

Discussed but not yet done. Pick these up as maintenance opportunities.

- **`requireTypedParameters` is a magic string.** It is plucked out of `...$arg` by name in `Hook::applyFilter`, which works only because variadics collect named arguments. It silently collides with any hook that wants an argument of that name, and it is invisible in the signature. A dedicated method or an options object would be honest; changing it is a breaking change, so it needs a major.
- **Runtime `$acceptedArgs` deprecation.** The parameter is marked `@deprecated No longer used - kept for backwards compatibility` in `Hook::addAction` / `Hook::addFilter` and their `HookInterface` counterparts, but nothing tells users at runtime. Emit `trigger_error(..., E_USER_DEPRECATED)` when it's passed as a non-default value, so callers get a real signal before it's removed in a future major.
- **Dev dependency advisories.** `composer audit` reports two: `phpunit/phpunit` (high, CVE-2026-24765, unsafe deserialization in PHPT coverage handling) and `symfony/process` (medium, CVE-2026-24739, argument escaping under MSYS2/Git Bash on Windows). Both arrive transitively through `pestphp/pest` and are dev-only, so no consumer of this library is exposed, but the test toolchain should not sit on a known high. Needs a dependency bump, then re-run all three gates.
- **`pestphp/pest` is a major behind.** Pinned at `^3.8` with 3.8.4 installed; 5.3.4 is out. `phpstan/phpstan` is also behind (2.2.8 vs 2.3.4). The Pest jump is the one that needs care, since the mutation-testing flags and thresholds this repo depends on changed across majors.
- **`composer.json` carries a `version` field.** `composer validate --strict` warns against it for a Packagist-published package, but `.releaserc.json` has semantic-release rewrite it on every release via `jq`. Removing it means changing the release pipeline to stop writing it; keep-or-drop is a real decision, not a cleanup.
