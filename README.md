# Design Reference Picker

Design Reference Picker is a fast, local-first tool for reviewing a library of web-design screenshots and collecting either complete screenshots or precise cropped regions. Each crop can include an optional plain-text note, keeping visual references and the thinking behind them together.

## Highlights

- Browse a naturally sorted screenshot collection with previous and next controls.
- Approve a complete screenshot with one click.
- Start a crop directly from the current image or with the **CUT** control.
- Define a crop with two exact points, then drag or resize the selection.
- Save at the original image resolution whenever PHP GD supports the source format.
- Fall back to browser canvas cropping when server-side image processing is unavailable.
- Attach an optional note to each crop.
- Generate sequential `cutted-01.jpg`, `cutted-02.jpg`, and matching `NOTE-cutted-01.txt` files.
- Keep all approved assets in `top 20/top 5/handpicked/`.

## Technology

This is a framework-free project built with PHP, HTML, CSS, and vanilla JavaScript. It does not use React, Node.js at runtime, a database, or a build step.

## Requirements

- PHP 7.4 or newer.
- A browser with modern JavaScript, Canvas, Pointer Events, and Fetch support.
- Write permission for `top 20/top 5/handpicked/`.
- Recommended: PHP GD with JPEG, PNG, GIF, and WebP support for maximum-resolution server-side crops.

## Installation

1. Download or clone the repository.
2. Place the repository in a PHP-capable web-server document root, or open a terminal in the repository directory.
3. Ensure PHP can write to `top 20/top 5/handpicked/`.
4. Start a local server:

   ```bash
   php -S 127.0.0.1:8080
   ```

5. Open `http://127.0.0.1:8080/top.php`.

See [INSTALLATION.md](INSTALLATION.md) for Apache, Nginx, PHP GD, and permissions guidance.

## Usage

### Approve a complete screenshot

1. Use **PREV** and **NEXT** to choose the current screenshot.
2. Select **APPROVE**.
3. The original file is copied to `top 20/top 5/handpicked/`.

### Save a cropped reference

1. Left-click the current screenshot, or select **CUT**.
2. Left-click the first corner of the desired crop.
3. Left-click the opposite corner.
4. Drag the selection or its handles to refine the region.
5. Optionally enter a note in **Write Optional Note...**.
6. Select the green scissors button inside the crop.
7. The crop and optional note are saved sequentially in `top 20/top 5/handpicked/`.

Select **CANCEL**, the red close button, or the dark area outside the selection to discard the active crop. Right-click and middle-click never create crop points.

See [USAGE.md](USAGE.md) for the complete interaction guide.

## Screenshot library layout

The picker scans supported images (`jpg`, `jpeg`, `png`, `webp`, and `gif`) from the repository root and recognized `top 20` / `top 5` collections. The `handpicked` output directory and picker scripts are excluded from the source list.

```text
.
├── top.php
├── screenshot-a.jpg
├── screenshot-b.webp
└── top 20/
    └── top 5/
        ├── handpicked/
        └── ...
```

## Data and privacy

The application has no analytics, accounts, database, or remote storage integration. Screenshot processing occurs on the host running PHP or in the current browser fallback. External icon images are loaded from Flaticon’s CDN by the current interface; see [PRIVACY.md](PRIVACY.md).

## Documentation

- [Installation](INSTALLATION.md)
- [Usage](USAGE.md)
- [Architecture](ARCHITECTURE.md)
- [Security Policy](SECURITY.md)
- [Contributing](CONTRIBUTING.md)
- [Code of Conduct](CODE_OF_CONDUCT.md)
- [Changelog](CHANGELOG.md)
- [Support](SUPPORT.md)

## License

Design Reference Picker is available under the [MIT License](LICENSE).

## Credits

Created and maintained by Eduardo Silveira Rios. Source screenshots and externally hosted icons retain the rights and terms of their respective owners.
