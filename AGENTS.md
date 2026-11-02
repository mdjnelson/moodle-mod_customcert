# AI Agent Instructions

## General principles

Keep changes small, focused, and maintainable.

- Make the smallest change necessary to complete the task.
- Fix the underlying problem without redesigning unrelated code.
- Do not introduce unnecessary abstractions, interfaces, services, or helpers.
- Avoid unrelated refactoring, cleanup, formatting, and speculative improvements.
- Follow existing repository patterns unless there is a concrete reason not to.
- Do not expand the scope of a task without being asked.
- If something is already implemented, explain this rather than making unnecessary changes.

## Before making changes

- Read the relevant issue and discussion when available.
- Inspect the existing implementation and relevant tests before proposing a solution.
- Check whether the problem has already been fixed.
- Identify the smallest appropriate change.
- Consider backwards compatibility with existing installations, data, and third-party element plugins.

## Backwards compatibility

- Respect the Moodle version and compatibility requirements of the target branch.
- Do not remove or change released public APIs or subplugin contracts unless explicitly required.
- Preserve existing stored data formats and upgrade paths.
- Do not introduce features or APIs unavailable in the target Moodle or PHP version.
- Do not backport changes to other branches unless explicitly requested.
- Keep compatibility work limited to the versions supported by the target branch.

## Git commits

Keep commit messages concise.

- Use a short imperative subject line.
- Include the relevant issue number when one exists.
- Add a commit body only when it provides necessary context.
- Do not include Co-Authored-By trailers.
- Do not mention Claude, Anthropic, AI, or automated assistance in commits.
- Do not add generated-by attribution or similar metadata.
- Do not write long summaries of every file changed.

Examples:

```text
Fix certificate generation for unenrolled users (#773)
Preserve legacy element compatibility (#954)
Add regression tests for certificate downloads (#934)
```

## Git workflow

Work only on the currently checked-out branch and its task branch unless explicitly instructed otherwise.

- At the start of a task, identify the current Git branch.
- Treat the current branch as the target for the task.
- If on `main` or a `MOODLE_*_STABLE` branch, create a separate task branch based on the current branch.
- If already on a task branch for the current issue, continue using it.
- If the current task branch belongs to a different issue, stop and ask before making changes.
- Do not switch to another release branch unless asked.
- Do not backport changes to other branches unless asked.
- Keep all commits for the task on its task branch.
- Do not merge into the target branch.
- Do not push, force-push, merge, or open a pull request unless explicitly asked.
- Leave the task branch ready for review.

## Code comments and documentation

Keep comments concise and useful.

- Prefer clear code over explanatory comments.
- Only add comments that explain non-obvious intent, constraints, or edge cases.
- Do not narrate straightforward code.
- Do not add comments solely to explain changes made in the current task.
- Preserve useful existing comments and PHPDoc unless incorrect.
- Follow Moodle coding style and required PHPDoc conventions.
- Do not create unnecessary documentation or lengthy implementation summaries.

## Testing

- Add or update focused regression tests for bug fixes where practical.
- Test the behaviour being changed, including relevant edge cases.
- Prefer existing test infrastructure and conventions.
- Run relevant PHPUnit, Behat, and code-quality checks where available.
- Do not modify unrelated tests merely to make a change pass.
- Report which checks were run and which could not be run.
- Never claim tests passed unless they were actually executed.

## Changelog

Update `CHANGES.md` when making a user-facing bug fix, adding a feature, or introducing a meaningful behavioural change.

- Follow the existing changelog format and categories.
- Write concise entries describing the change from the user's perspective.
- Include the relevant issue number when one exists.
- Do not add entries for tests, documentation-only changes, internal refactoring, or routine maintenance unless they have a meaningful impact on users.
- Do not duplicate existing entries.

### Stable branches

For `MOODLE_*_STABLE` branches:

- Add changes to the latest `Unreleased` section if one exists.
- Otherwise, create a new `Unreleased` section at the top of the changelog, after the introductory text.
- Use the next patch version after the latest released version.
- Follow the format `## [5.3.5] - Unreleased` (adjust the version for the target branch).
- Add entries under appropriate headings, such as `Added`, `Changed`, `Fixed`, or `Security`.
- Do not modify changelog entries for released versions.
- Do not bump `version.php` or assign a release date.

### Main branch

For `main`:

- Only update `## [6.0] - [Unreleased]` for changes specific to Moodle 6.0 or the main branch.
- Do not add routine bug fixes or features that are also intended for supported stable branches to this section.
- Do not create a new version section for routine changes on `main`.
- Preserve the existing changelog history.

## Releases

- Do not bump plugin versions or prepare releases unless explicitly requested.
- Do not modify unrelated release metadata.

## Final response

Keep the final summary brief.

- Explain what changed and why.
- Mention the tests performed.
- Identify any remaining limitations or risks.
- Do not provide a lengthy file-by-file summary unless requested.
