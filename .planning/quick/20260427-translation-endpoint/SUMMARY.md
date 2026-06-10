---
status: complete
---

Add Translation Endpoint (`/api/translate`)

1. **Install Package:** Installed `google/cloud-translate` via composer root.
2. **Configure Authentication:** Used `service-account.json`.
3. **Generate Controller:** Created `TranslationController` with `translate` method.
4. **Define Route:** Registered `POST /api/translate` in `routes/api.php`.
5. **Implement Logic:** The API translates associative arrays matching keys.

Output: Created Translation endpoint handling multi-text payloads!