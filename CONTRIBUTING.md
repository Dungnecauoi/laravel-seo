# Contributing

## Running the tests

Everything runs in throwaway Docker containers, so nothing has to be installed
on your machine and your working copy is never written to:

```bash
docker/matrix.sh          # PHP suite, every supported Laravel and PHP version
docker/matrix.sh 13       # one Laravel major
docker/js.sh              # the npm client: typecheck, build, test
docker/matrix.sh --clean  # remove the built images
```

If you would rather work locally, `composer install && vendor/bin/phpunit`
covers whichever PHP version you happen to have.

## The rules this codebase is held to

**Contracts are the public API.** Anything in `src/Contracts` is what users
depend on. Changing one is a major release; everything else is free.

**One context parameter per contract method.** Adding a parameter to an
interface method breaks every implementation someone has written. Adding a
field to a DTO breaks nothing. So methods take `SeoContext`,
`AnalysisContext`, `AiRequest` — never a list of scalars.

**No `version_compare` outside `Support/Compat`.** Version checks scattered
through feature code are how a package spanning several majors rots: each one
is invisible until it breaks.

**No third-party runtime dependencies.** Only `illuminate/*` and extensions
that ship with PHP. A library that is abandoned, or whose next major arrives at
an inconvenient time, becomes this package's problem — and this is
infrastructure meant to outlast that.

**Comments explain why, never what.** If a line needs a comment to say what it
does, rename something instead.

## Tests

A test should state a behaviour someone could disagree with, and its name
should read as a sentence. Prefer one that would have caught a real bug over
one that exercises a getter.

Where a test encodes a non-obvious decision — why skipped checks are excluded
from scoring, why `null` means the shared row rather than the current locale —
say so in a comment. Those are the tests that stop a future change from quietly
undoing a deliberate choice.
