# Refactoring Checklist

## Before Editing

- Identify the exact behavior that must stay the same (inputs, outputs, side effects).
- Trace includes, session checks, redirects, and query dependencies.
- Separate true duplication from similar-looking code with different rules.
- Note any global state, superglobals (`$_GET`, `$_POST`, `$_SESSION`), or shared config in scope.

## Goals (Applied Throughout)

Every change must move the code toward:

- **DRY** — eliminate repeated logic; extract once, use everywhere
- **Single Responsibility** — each function/class does one thing
- **Clarity** — names describe intent, not implementation
- **Reusability** — helpers are generic enough to reuse, specific enough to be coherent
- **Minimal surface area** — prefer one clear helper over several thin wrappers

## During Editing

- Extract shared logic into named helpers with descriptive, narrow names.
- Preserve request parameter names and expected output structure exactly.
- Keep HTML output order stable unless a layout change is explicitly in scope.
- Remove dead code, unused variables, and commented-out blocks.
- Replace magic values with named constants or config entries.
- Consolidate repeated query patterns into reusable data-access functions.
- Keep business logic out of templates/views.

## Code Quality Rules

- Functions should do one thing and be testable in isolation.
- Avoid deeply nested conditionals — use early returns to reduce indentation.
- Group related functions together; don't scatter helpers across unrelated files.
- Use consistent naming conventions (`camelCase` vs `snake_case`) — match the existing codebase.
- Avoid side effects inside helper functions (no session writes, no redirects inside utilities).

## Validation

- Run `php -l` on every changed PHP file before committing.
- Re-check entry conditions for guest, logged-in, and admin/teacher flows when relevant.
- Confirm links, forms, and redirects still point to the same targets.
- Check that shared CSS changes do not unintentionally affect unrelated pages.
- Manually test the happy path and at least one edge case per changed flow.
- Confirm no change in visible output, error behavior, or redirect destination.