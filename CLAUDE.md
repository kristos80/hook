# CLAUDE.md

## Testing

Coverage and mutation testing must both pass at 100%. Always run both after making changes.

```bash
# Coverage
herd coverage ./vendor/bin/pest --coverage

# Mutation testing
herd coverage ./vendor/bin/pest --mutate --parallel --everything --covered-only
```
