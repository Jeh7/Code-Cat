---
name: refactoring
description: >
  Refactor existing code safely without intentionally changing behavior. Use
  this skill when Codex needs to clean up procedural PHP, split large files,
  extract helpers, reduce duplication, rename symbols, simplify control flow,
  improve maintainability, or prepare code for future features and tests. This
  applies especially to page-entry PHP files, shared helpers, and CSS in this
  repository when the user asks to refactor, reorganize, clean up, or make code
  easier to change.
---

# Role: Refactoring Engineer

Improve structure without changing intended behavior. Favor small, verifiable
steps over broad rewrites.

## Workflow

1. Read the touched files first and identify the current behavior, dependencies,
   and duplication.
2. State the refactoring target in one sentence before editing:
   reduce duplication, isolate responsibilities, simplify branching, or improve
   naming.
3. Keep public behavior, routes, session checks, and database interactions
   stable unless the user explicitly asks for behavior changes.
4. Make the smallest structural change that meaningfully improves the code.
5. Re-run lightweight validation after edits.

## Repository-Specific Rules

- Treat top-level `.php` files as page entry points. Keep request handling easy
  to follow.
- Prefer extracting small local helpers or shared include files over introducing
  new abstractions prematurely.
- Preserve role checks, session guards, redirects, and flash-message behavior.
- Keep `db.php` as shared setup unless there is a clear reason to change that
  boundary.
- Treat `game/` as generated export assets unless the task is explicitly about
  replacing the export.
- Preserve existing asset paths and naming patterns.

## Refactoring Priorities

- Remove duplicated markup or repeated PHP branches.
- Give repeated literals and mixed concerns better structure.
- Shorten oversized functions or request handlers by extracting cohesive blocks.
- Replace fragile condition chains with clearer control flow.
- Improve naming when the current name hides intent.
- Add brief comments only where the control flow is genuinely non-obvious.

## Guardrails

- Do not mix refactoring with unrelated feature work.
- Do not silently change SQL semantics, form field names, or redirect targets.
- Do not replace straightforward procedural PHP with heavy architecture.
- Do not move code into shared files unless at least two call sites benefit or
  the page becomes materially clearer.

## Validation

- Lint every changed PHP file with `php -l`.
- Manually inspect the affected flow boundaries: auth, form submit, redirect,
  report/export path, or game launch path.
- Use [references/refactoring-checklist.md](references/refactoring-checklist.md)
  when the change touches multiple pages or mixes PHP, HTML, and CSS.

## Output Expectations

Report:

- what was structurally improved
- what behavior was intentionally preserved
- what validation was run
- any remaining risk if the refactor could not be fully exercised
