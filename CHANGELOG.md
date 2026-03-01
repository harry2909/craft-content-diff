# Release Notes for Craft Content Diff

## 1.0.9 - 2026-02-28

- **Compare fix:** Resolved "Array to string conversion" when comparing: block/Matrix type labels and HTTP Basic auth are coerced to strings; `valueChanged()` no longer casts arrays to string when one side is array and the other is not; asset filename in labels is always string.
- **Compare fix:** Array comparison is now key-order independent (deep compare). Identical field values that differed only by JSON key order (e.g. Color Swatches) no longer show as changed.
- **Settings:** Resolved values (API key, URLs, HTTP auth) now use `App::env()` for known env vars (e.g. `CRAFT_CONTENT_DIFF_API_KEY`) with fallback to stored settings. Resolution logic moved into `SettingsService`.
- **Docs:** README notes env resolution via `App::env()` and that HTTP Basic auth can be left blank when not used.

## 1.0.8 - 2026-02-28

- **Logging fix**: All `Craft::error()` / `Craft::info()` calls now pass the category as the string `'craft-content-diff'` (not an array). Fixes `strpos(): Argument #1 ($haystack) must be of type string, array given` in Yii log Target when a compare fetch failed.

## 1.0.7 - 2026-02-28

- **README**: Added “Cloudflare (or similar WAF)” section: server-to-server compare requests can be blocked; how to allowlist `/actions/craft-content-diff/diff` (and optionally require `X-Content-Diff-Token`) in Cloudflare WAF.
- **Compare errors**: Fetch failure messages now point to the README Cloudflare section when connection fails or response is invalid. Logging simplified (Craft `craft-content-diff` category, single-line error).

## 1.0.6 - 2026-02-28

- **Compare errors**: Dashboard now shows the actual failure reason when fetch fails (e.g. remote 401 message, connection timeout). Logging improved with HTTP status, remote message, and PHP/connection errors (category `craft-content-diff`).
- **View JSON**: Link includes API key as `?token=` when set, so it works when opened from the CP. Diff URL uses the current environment (staging/production/local) instead of always `local`.
- **Remote fetch**: Request to production/staging now sends the correct `?environment=` so the remote’s JSON reflects the right environment label.
- **Environment detection**: If `ENVIRONMENT` / `CRAFT_ENVIRONMENT` is not set, current environment is inferred by comparing the site URL to the configured Production URL and Staging URL.
- **Docs**: API key examples use `$CRAFT_CONTENT_DIFF_API_KEY` in settings, README, and 401 hints.

## 1.0.5 - 2026-02-24

- README: Installation section simplified to Composer commands only.

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
