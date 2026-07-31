# Contributing

Contributions that improve crop accuracy, accessibility, browser compatibility, documentation, or safe local operation are welcome.

## Development setup

1. Fork and clone the repository.
2. Create a focused branch from `main`.
3. Start PHP’s local server with `php -S 127.0.0.1:8080`.
4. Open `/top.php` and test with landscape and tall screenshots.
5. Keep the implementation framework-free: PHP, HTML, CSS, and vanilla JavaScript only.

## Standards

- Write source, comments, documentation, commits, and issue content in English.
- Keep changes focused and avoid generated output unless it is a deliberate fixture.
- Preserve path validation and original-resolution coordinate mapping.
- Test left-button-only crop activation, scrolling, drag and resize, cancellation, full-image approval, optional notes, and browser fallback.
- Do not add analytics, remote uploads, or a framework without prior discussion.

## Pull requests

Describe the problem, the behavior change, validation performed, and any visual impact. Include before-and-after screenshots for interface changes. Confirm that no private or unlicensed screenshot was added.

By contributing, you agree that your contribution is licensed under the repository’s MIT License.
