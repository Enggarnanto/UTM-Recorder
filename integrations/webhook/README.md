# Generic Webhook/API integration — UTM Recorder 2.6.15

This integration has no organization-specific hostname, brand, or database assumption in the WordPress sender. Any backend (PHP, CodeIgniter, Laravel, Node.js, Python, etc.) can implement the protocol below. The included PHP/CodeIgniter adapters are reference receivers, not a claim that an existing external application has been modified or deployed.

Google Sheets remains independent. Both destinations have their own configuration, scheduler, cursor, lock, and status. Enabling the webhook does not disable Sheets or copy its acknowledged cursor.

## WordPress setup

1. Install plugin 2.6.15 once manually, then open **Traffic Recorder > Settings > Integrasi Webhook/API**.
2. Obtain the final HTTPS endpoint, a unique site ID, and a random 32–512 character ASCII API key without spaces from the backend administrator.
3. Save without enabling sync, then choose **Tes koneksi backend**. The backend must verify authentication, website registration, and database access without inserting a sample click.
4. Enable sync and save. **Sinkronkan ke backend** sends one batch immediately; remaining records follow the five-minute WP-Cron schedule. Up to 100 records are sent per batch. WP-Cron depends on site traffic unless hosting cron is configured.
5. **Kirim ulang riwayat backend** restarts only the webhook cursor. Existing 90-day WordPress retention still applies. Deleted local records cannot be recovered. New website ID or endpoint starts export from the beginning. Changing just the key preserves the cursor.

The API key is server-side and is not displayed again in HTML. Leave its field empty to preserve it. HTTPS certificate checks are enabled; redirects are rejected, and WordPress's safe HTTP transport rejects unsafe destinations. This template uses server-to-server Bearer authentication rather than browser sessions. Do not add API keys to URLs, logs, screenshots, or source control.

## API protocol (language-independent)

`POST <your final HTTPS endpoint>`

Headers:

```http
Content-Type: application/json; charset=utf-8
Accept: application/json
Authorization: Bearer <unique website key>
```

Test payload (no writes):

```json
{
  "protocol_version": 1,
  "site_id": "website_a",
  "site_url": "https://website-a.example/",
  "action": "test",
  "events": []
}
```

Successful test: HTTP 200, JSON `{"ok":true,"site_id":"website_a","accepted_ids":[]}`.

Sync payload:

```json
{
  "protocol_version": 1,
  "site_id": "website_a",
  "site_url": "https://website-a.example/",
  "action": "sync",
  "events": [{
    "id": "123",
    "occurred_at": "2026-10-28 14:59:03",
    "visitor_id": "aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa",
    "session_id": "bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb",
    "account_id": "1",
    "account_name": "Admin",
    "phone": "6280000000000",
    "link_type": "floating",
    "page_path": "/promo",
    "utm_source": "google",
    "utm_medium": "cpc",
    "utm_campaign": "Promo",
    "utm_term": "keyword",
    "utm_content": "ad-1",
    "utm_match_type": "exact",
    "message_source": "Sumber: google",
    "message_source_recorded": "1"
  }]
}
```

The phone example is a fixture, not a contact. `occurred_at` is UTC from WordPress, regardless of the Sheets display timezone. Store UTC and display `Asia/Jakarta` (UTC+7) in your dashboard. IDs should be strings to avoid large-integer precision loss in JavaScript.

Maximum batch: 100 events and 450,000 bytes including metadata. Validate all events before writing. Bind credentials to both the registered site ID and its registered `site_url` (including any WordPress subdirectory). The payload URL is not proof of identity by itself. Escape text when presenting it in HTML, CSV, or spreadsheets.

Persist the batch transactionally. Deduplicate with a unique `(site_id, event_id)` constraint. An existing event is immutable and is acknowledged again. Return HTTP 200 only AFTER a successful commit:

```json
{"ok":true,"site_id":"website_a","accepted_ids":["123"]}
```

`accepted_ids` must contain every sent ID as a string, in the original order, including duplicates already stored. A missing acknowledgement, wrong site ID, timeout, non-JSON body, or error status leaves the batch queued. Never return success if storage fails. Keep site IDs unique; if a website's database is reset and click IDs restart, register a new site ID.

Error examples: HTTP 401 `{"ok":false,"error_code":"unauthorized"}`, HTTP 403 `site_url_mismatch`, HTTP 400 `invalid_batch`, HTTP 503 `database_error`. Do not include keys, database credentials, or raw exceptions in responses. Configure request-size limits and per-site rate limiting on the receiving server. Do not cache this endpoint's responses.

## Included reference adapters: PHP 7.4+ and MySQL/MariaDB

Choose ONE adapter. Use a staging database first. These tables are separate from existing lead/customer tables; this feature does not create leads or infer a visitor's WhatsApp number from a click.

1. Import `schema.sql` into the application's existing database. Both tables must use InnoDB. If CodeIgniter has a database prefix, add that prefix to both SQL table names before importing; the adapter uses the configured prefix automatically.
2. Register each website in `utr_integration_sites`. Generate a random key with a password manager. Store only its lowercase SHA-256 hex digest in `token_hash`; `home_url` must equal the WordPress home URL, including its subdirectory. Site IDs are case-sensitive. Example SQL with a placeholder, not a real key:

   ```sql
   INSERT INTO utr_integration_sites (site_id, home_url, token_hash, enabled)
   VALUES ('website_a', 'https://website-a.example/', SHA2('REPLACE_WITH_YOUR_RANDOM_KEY_AT_LEAST_32_CHARACTERS', 256), 1);
   ```

   Prefer parameterized registration through an admin tool. Do not commit a real registration query to source control or shared SQL logs. The original key goes into the WordPress Settings field. Key rotation changes the stored hash and the WordPress key.

### Plain PHP

Install `php/index.php` and the adjacent `shared` directory on the receiving server. Set `UTR_DB_DSN` (example `mysql:host=localhost;dbname=YOUR_DB;charset=utf8mb4`), `UTR_DB_USER`, and `UTR_DB_PASSWORD` through server environment configuration. Requires PDO MySQL. If moving `shared` outside the web root, update the two require paths. Use the final HTTPS URL to `index.php` as the webhook endpoint. This adapter uses unprefixed table names from `schema.sql`.

### CodeIgniter 3

- Copy `shared/UtrReceiver.php` and `shared/SqlStore.php` into `application/libraries/utr_webhook/`.
- Copy `ci3/Utr_webhook.php` into `application/controllers/`.
- Add to `application/config/routes.php`:

  ```php
  $route['api/integrations/utr/wa-clicks']['post'] = 'utr_webhook/clicks';
  ```

- If global CSRF is enabled, add ONLY `api/integrations/utr/wa-clicks` to `$config['csrf_exclude_uris']`. Keep CSRF enabled for other forms. If your app has global login hooks, exempt only this exact machine endpoint and retain Bearer validation. Never disable authentication globally.
- Endpoint is normally `https://your-domain.example/api/integrations/utr/wa-clicks`; include `index.php` if required by the app's URL configuration. Ensure the hosting forwards the Authorization header.

### CodeIgniter 4

- Copy the shared files into `app/Libraries/UtrWebhook/`.
- Copy `ci4/UtrWebhook.php` into `app/Controllers/`.
- Add an explicit POST route to `app/Config/Routes.php`:

  ```php
  $routes->post('api/integrations/utr/wa-clicks', 'UtrWebhook::clicks');
  ```

- Add only this route as a CSRF exception if global CSRF is enabled. Scope any browser-login exception to this endpoint; Bearer authentication remains required. Disable legacy auto-routing and apply your machine-API rate limiter to this route. Existing HTML form protections remain unchanged.
- The controller uses the default `db_connect()` connection. Change it to the intended database group if your application uses multiple groups.

## Application dashboard

Data is stored in `utr_wa_clicks`, grouped by `site_id`. Join `utr_integration_sites` for the registered website URL. Build authenticated dashboard views with date/site/account/UTM filters using the application's own permissions and styles. Display dates in WIB if desired. `phone` is the destination/admin number; a click is not proof that a chat was sent or a registration occurred.

The CodeIgniter adapters need installation/review by the application's maintainer because no existing backend project was available in this workspace. Other languages can implement the same JSON contract without using these PHP files.
