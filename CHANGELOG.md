# Release Notes for Craft Content Diff

## 1.0.4 - 2026-02-24

- Updated icons.

## 1.0.3 - 2026-02-24

- Added required `description` to `composer.json` for Packagist publishing.
- Removed `version` from `composer.json` (version is taken from git tags for published packages).

## 1.0.2 - 2026-02-23

- Updated Craft CMS version constraint in `composer.json`.

## 1.0.1 - 2026-02-22

- README updates.

## 1.0.0 - 2026-02-22

- Initial release.
- Simplified plugin icon (SVG and mask).
- Compare entry content between environments (local, staging, production) from the Control Panel dashboard.
- Field-level diffs including Matrix and nested blocks; asset/relation IDs shown as labels where possible.
- Diff endpoint at `/actions/craft-content-diff/diff` (site action URL) with API key auth via `X-Content-Diff-Token` header.
- Plugin Settings: API key, Production URL, Staging URL, optional HTTP Basic auth for staging/production. All values support literals or env var aliases (e.g. `$CONTENT_DIFF_PRODUCTION_URL`) via Craft’s `EnvAttributeParserBehavior`.
- Generate API key button in Settings (output shown in a span for copy/paste).
- Graceful error handling: compare and enrich failures show user message and log; remote fetch failures log and return empty with clear warnings.
