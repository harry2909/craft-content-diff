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
| **HTTP Basic auth** | No | If staging or production is behind server-level HTTP Basic auth, set username and password (literals or env aliases). Leave blank on an environment that does not use HTTP auth; you do not need to set empty env vars. |

Env aliases (e.g. `$CRAFT_CONTENT_DIFF_API_KEY`) are resolved at runtime using Craft’s `App::env()`.

Set **ENVIRONMENT** (or **CRAFT_ENVIRONMENT**) to `dev`, `staging`, or `production` so the dashboard shows the right compare targets.

## Cloudflare (or similar WAF)

Compare uses **server-to-server** requests (no browser, no cookies). If production or staging is behind **Cloudflare** (or another WAF), it may block or challenge those requests and the compare will fail with a connection or invalid-response error.

**Fix — step by step in Cloudflare:**

1. Go to **Security** → **WAF** → **Custom rules** (or **Security** → **Configuration** → **WAF Custom rules**).
2. Click **Create rule** (or **Add rule**).
3. **Rule name:** e.g. `Allow Content Diff endpoint`.
4. **Field / When:** Build an expression:
   - Choose **Field**: **URI Path** (under “Request”).
   - **Operator:** **equals** (or **contains**).
   - **Value:** `/actions/craft-content-diff/diff` (for equals) or `craft-content-diff/diff` (for contains).  
   Do **not** use the full URL (no `https://` or domain).
5. (Optional) Click **Add condition** (or **And**): **Request Header** → **Name** `X-Content-Diff-Token` → **Operator** “is present” or “exists”, so only requests that send the API key are allowed.
6. **Action:** Choose **Skip** → **Skip all remaining custom rules**, or **Allow**. (“Skip” only skips WAF custom rules; if the request is still blocked, try **Allow** or check **Security** → **Events** to see which product blocked it.)
7. **Deploy** / **Save**.

If it still fails, open **Security** → **Events**, trigger a compare, find the request to `craft-content-diff/diff`, and check the **Action** and **Reason** (e.g. Bot Fight Mode, Security Level). Add an exception for that path or lower the security level for that URI if needed.

## Licence

Proprietary (Craft License). See [LICENSE.md](LICENSE.md).
