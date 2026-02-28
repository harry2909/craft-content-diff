# Craft Content Diff

<img width="732" height="542" alt="Content Diff dashboard" src="https://github.com/user-attachments/assets/c4a91dc4-6e3a-4965-837d-c50c8f1fbbc2" />
<img width="794" height="806" alt="Content Diff settings" src="https://github.com/user-attachments/assets/d845543e-7cdf-4e63-9465-3b7756fd7f3b" />

Compare entry content between Craft CMS environments (local, staging, production). View created, deleted, and updated entries with field-level diffs, including Matrix and nested blocks.

## Requirements

- Craft CMS 5.0.0 or later
- PHP 8.2 or later

## Installation

```bash
composer require roadsterworks/craft-content-diff
./craft plugin/install craft-content-diff
```

## Configuration

Configure in **Control Panel → Content Diff → Settings**. Use the same API key and (where relevant) env vars on **every environment** (local, staging, production) so the diff endpoint and dashboard work correctly.

| Setting | Required | Notes |
|--------|----------|--------|
| **API key** | Yes | Secret for the diff endpoint. Generate in Settings or set a literal / env alias (e.g. `$CRAFT_CONTENT_DIFF_API_KEY`). Same value on all envs. |
| **Production URL** | Yes (for staging/dev) | Base URL of production (e.g. `https://example.com`). Literal or env alias. |
| **Staging URL** | Yes (for production/dev) | Base URL of staging. Literal or env alias. |
| **HTTP Basic auth** | No | If staging or production is behind server-level HTTP Basic auth, set username and password (literals or env aliases). |

Set **ENVIRONMENT** (or **CRAFT_ENVIRONMENT**) to `dev`, `staging`, or `production` so the dashboard shows the right compare targets.

## Cloudflare (or similar WAF)

Compare uses **server-to-server** requests (no browser, no cookies). If production or staging is behind **Cloudflare** (or another WAF), it may block or challenge those requests and the compare will fail with a connection or invalid-response error.

**Fix:** In Cloudflare **Security → WAF → Custom rules**, add a rule that **allows** requests to the diff endpoint.

- Use **URI Path** (not the full URL — the path has no domain, e.g. `/actions/craft-content-diff/diff`).
- **If** URI Path **contains** `craft-content-diff/diff` (or **equals** `/actions/craft-content-diff/diff`) **and** Request Header `X-Content-Diff-Token` is present  
- **Then** Skip (or Allow).

That way only requests that send your API key are allowed through; bots without the token are still challenged. You can also allow by path only if the API key is secret.

## Licence

Proprietary (Craft License). See [LICENSE.md](LICENSE.md).
