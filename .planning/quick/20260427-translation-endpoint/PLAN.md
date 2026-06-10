---
status: incomplete
---

Add Translation Endpoint (`/api/translate`)

1. **Install Package:** Run `composer require google/cloud-translate` to install the official Google Cloud Translation API package.
2. **Configure Authentication:** Ensure `GOOGLE_APPLICATION_CREDENTIALS` is mapped to `service-account.json`. 
3. **Generate Controller:** Create `app/Http/Controllers/Api/TranslationController.php` with a `translate` method to handle incoming translation requests.
4. **Define Route:** Register the `POST /api/translate` endpoint in `routes/api.php`.
5. **Implement Logic:** The endpoint accepts a `target_language` and a `texts` associative array (e.g., `{"full_text": "...", "summary": "..."}`). We will use `TranslateClient::translateBatch()` or iterate calling `translate()` for each item and return the mapped JSON structure matching the original keys.

## Assumptions
- Uses Google Cloud Translation API v2/v3 (Basic/Advanced). 
- Authenticates using existing `service-account.json`.