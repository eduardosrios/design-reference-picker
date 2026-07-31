# Release Process

This repository uses milestone releases to preserve the evolution of the picker, its screenshot library, documentation, validation, and deployment configuration.

Each release should:

1. Reference an immutable commit on `main`.
2. Use a semantic version tag.
3. Summarize the specific capability or content batch introduced.
4. Pass PHP syntax, JavaScript syntax, repository-scope, and Markdown link checks.
5. Avoid private notes, temporary files, local rules, and unrelated workspace content.

Historical releases are retained for auditability. Only the latest `main` version receives support and security fixes.
