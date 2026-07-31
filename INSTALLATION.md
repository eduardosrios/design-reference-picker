# Installation

## Quick start

Design Reference Picker needs a PHP web server because approving full images and saving original-resolution crops write files on the server.

```bash
git clone https://github.com/eduardosrios/design-reference-picker.git
cd design-reference-picker
php -S 127.0.0.1:8080
```

Open `http://127.0.0.1:8080/top.php`.

## PHP requirements

PHP 7.4 or newer is recommended. Enable the GD extension to crop the original source pixels on the server. The application checks individual decoder support for JPEG, PNG, GIF, and WebP. If GD or a required decoder is missing, the browser can produce a JPEG fallback from the rendered source image.

Check GD:

```bash
php -m | findstr /I gd
```

On macOS or Linux, replace `findstr /I gd` with `grep -i gd`.

## Writable output directory

The web-server user must be able to create and write:

```text
top 20/top 5/handpicked/
```

The application creates this directory when necessary, but its parent directories must allow creation. Use the least-permissive filesystem rights that work for your local server; do not expose a world-writable public directory on an internet-facing host.

## Apache

Place the repository under the configured document root, confirm PHP is enabled, and open `/design-reference-picker/top.php`. No rewrite rules are required.

## Nginx

Configure the site’s PHP location to pass `.php` requests to PHP-FPM, set the repository as the document root, and open `/top.php`. No framework routing is required.

## Screenshot collection

Add supported files to the repository root or the recognized `top 20` and `top 5` directories. Supported extensions are `jpg`, `jpeg`, `png`, `webp`, and `gif`.

## Production warning

The tool is designed for a trusted local or access-controlled environment. If exposed to a network, add authentication, HTTPS, strict server permissions, request-size controls, and appropriate security headers.
