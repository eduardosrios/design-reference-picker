# Privacy

Design Reference Picker is local-first. It does not include user accounts, analytics, telemetry, a database, or an application-owned cloud upload service.

Screenshot discovery, file copying, crop storage, and note storage happen on the server where the repository is running. When server-side source decoding is unavailable, browser Canvas creates the crop before it is sent back to that same server.

The interface currently requests icon images from Flaticon’s CDN. Those requests can disclose standard network metadata such as IP address and user agent to the CDN operator. For a fully offline environment, download assets under compatible terms, store them locally, and update the icon URLs.

Users are responsible for ensuring they have permission to process, store, and publish source screenshots and notes.
