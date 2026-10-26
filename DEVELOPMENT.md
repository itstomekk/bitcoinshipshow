# Development notes

Technical notes for the Bitcoin Shipshow website. The friendly overview is in [README.md](README.md).

## What this repo is

A mirror of the live docroot. The repo root is the web root.

- `index.php`, `partials/`, `css/`, `js/`: homepage. Markup carries semantic hooks only; designs live in `css/*.css` (current: `corsair`).
- `about/`, `assets/`, `game/`, `sponsors/`, `privacy-policy/`, `terms-of-service/`: other pages. `/live/` is a 301 to the homepage.
- `gate/`: PHP email gate. Visitors leave an email to unlock the page below the hero and get a calendar invite. Includes the invite mailer, reminder, calendar and click-funnel code.
- `img/`, `robots.txt`, `sitemap.xml`, `llms.txt`: assets and crawler files.
- `tests/`: checks for the stream embed.

## Not in this repo

- `gate/config.php` (room link, admin token, HMAC secret, Google OAuth). See `gate/config.example.php` for the shape.
- `gate/data/` (signups and click logs).

`.gitignore` also keeps out `*.csv`, `*.env` and `*.log`.

## Running it

PHP 8.x. Copy `gate/config.example.php` to `gate/config.php`, fill it in, make `gate/data/` writable. Without a config the gate will not load.

## Deploy

Production runs on shared hosting (cPanel) and is deployed by script from a local working copy, not from this repo. This repo is a copy for reading, review and contributions.

## Brand assets

Logos, wordmarks and posters: `assets/downloads/bitcoin-shipshow-brand.zip`. The unpacked files and their usage rules are in `assets/downloads/` (see `assets/downloads/README.txt`).
