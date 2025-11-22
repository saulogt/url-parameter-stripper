# URL Parameter Stripper

A WordPress plugin that strips unwanted substrings or query parameters from URLs before they are persisted to the database. This repository also contains tooling to validate the plugin structure and produce a submission-ready ZIP file for the WordPress.org plugin directory.

## Requirements

-   Node.js 18+ and [pnpm](https://pnpm.io/) (see `packageManager` field)
-   PHP 5.6+ / WordPress 4.5+ for runtime (per plugin header)
-   `zip` available on your PATH (used by the bundling task)

## Project structure highlights

-   `url-parameter-stripper.php`: Main plugin entrypoint
-   `admin/`, `includes/`: PHP sources
-   `languages/`: i18n files
-   `.distignore`: Files excluded from release bundles

## Development scripts

```bash
pnpm install            # install JS tooling dependencies
pnpm run test           # run minimal structural checks
pnpm run bundle         # uses wp-scripts dist-zip to create url-parameter-stripper.zip
```

### `pnpm run test`

Runs the PHPUnit suite defined in `tests/`, currently asserting that the root plugin files exist and include the expected plugin header. Extend this suite as you add more automated checks.

### `pnpm run bundle`

Uses `@wordpress/scripts`' `dist-zip` command to package the plugin, respecting `.distignore`, and outputs `url-parameter-stripper.zip` at the repository root. Re-run whenever you need a fresh bundle; the previous archive is overwritten.

## Release checklist

1. Update version numbers and changelog/readme as needed.
2. Run `pnpm run test` and resolve failures.
3. (Optional) regenerate translation catalogues via Composer/WP-CLI (`composer run makepot`).
4. Run `pnpm run bundle` and verify the reported ZIP size.
5. Unzip locally (or inspect via `zipinfo`) to confirm it contains the plugin slug folder with the expected assets.
6. Submit `url-parameter-stripper.zip` to WordPress.org or deploy to your chosen distribution channel.

## Contributing

Bug reports and pull requests are welcome. Please follow WordPress coding standards and keep the bundle workflow in sync if you reorganize files.
