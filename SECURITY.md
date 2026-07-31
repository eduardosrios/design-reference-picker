# Security Policy

## Supported versions

Security fixes are applied to the latest version on the `main` branch. Historical releases are retained for traceability but are not independently maintained.

## Reporting a vulnerability

Do not open a public issue for a suspected vulnerability. Use GitHub’s private vulnerability reporting feature for this repository when available. Include:

- A concise description of the issue.
- Affected file, endpoint, or browser interaction.
- Reproduction steps and a minimal proof of concept.
- Expected impact.
- Suggested mitigation, if known.

You should receive an acknowledgement within seven days. Please allow a reasonable remediation period before public disclosure.

## Deployment guidance

Design Reference Picker writes files on the server and is intended for a trusted local or access-controlled environment. Before network exposure:

- Require authentication and HTTPS.
- Restrict filesystem permissions to the output directory.
- Limit request and note sizes at the web-server layer.
- Keep PHP and GD current.
- Apply Content Security Policy and other appropriate response headers.
- Review or self-host external icon assets.

Never point the application at an untrusted writable document tree.
