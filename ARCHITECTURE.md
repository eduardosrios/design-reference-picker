# Architecture

Design Reference Picker is a single-page PHP application with embedded HTML, CSS, and vanilla JavaScript.

## Request flow

1. PHP scans allowed screenshot directories and naturally sorts relative paths.
2. The browser renders one current image and client-side navigation controls.
3. **APPROVE** sends a JSON request that validates the current path and copies the original file.
4. Crop submission sends normalized source coordinates and an optional note.
5. PHP validates the path and dimensions, then uses GD to decode, crop, and encode a JPEG.
6. If the server cannot decode that source type, the browser crops the loaded image with Canvas and uploads the resulting JPEG.
7. The server assigns the next available sequential name and stores the image plus any non-empty note.

## Coordinate system

The crop overlay is measured against the rendered current image. Client code converts the selection to ratios of the image’s displayed width and height. Server code applies those ratios to the original pixel dimensions, which preserves source resolution and remains accurate when the document is scrolled.

## Trust boundaries

- Submitted source paths must match the server-generated image list.
- Resolved paths must remain inside the repository root.
- Crop values are bounded to the source image.
- Notes are limited to 1 MiB.
- Output numbering checks both crop and note filenames to avoid accidental reuse.

## Runtime dependencies

- PHP filesystem APIs for discovery and copy operations.
- Optional PHP GD decoders for original-resolution cropping.
- Browser Fetch, Canvas, Pointer Events, and modern layout support.
- External Flaticon CDN URLs for interface icons.

No database, package manager, bundler, or runtime JavaScript dependency is used.
