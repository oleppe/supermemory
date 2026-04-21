# Conventions

Date: 2026-04-21

## Backend Code Style

Observed PHP style matches modern Laravel conventions:

- PSR-12 style formatting and typed properties
- Constructor property promotion with `private readonly` dependencies
- Explicit return types on controller and service methods
- Small controllers that delegate to services
- Array-shaped JSON responses rather than dedicated response DTOs

Representative files:

- `app/Http/Controllers/AuthController.php`
- `app/Http/Controllers/IngestionController.php`
- `app/Services/SupermemoryService.php`
- `app/Services/UsageLimitService.php`

## Validation Pattern

- Request validation lives in FormRequest classes when a request shape is reused or non-trivial
- Simple auth flows use inline `$request->validate(...)`
- Validation failures are expected to return standard Laravel JSON validation payloads

## Service Layer Pattern

Integration-heavy behavior is consistently wrapped in service classes:

- Controllers do not call `Http::...` directly in the files inspected
- Provider-specific error shaping happens inside services
- Configuration access is centralized through `config(...)`
- Helper methods normalize upstream payload shapes and failure modes

## Persistence Pattern

- Eloquent models represent business state
- `updateOrCreate(...)` is used for idempotent ingestion tracking writes
- Transactions and `lockForUpdate()` are used where counters can race, as in `app/Services/UsageLimitService.php`

## User Scoping Pattern

Per-user external state is scoped consistently:

- Supermemory uses deterministic `containerTag` values like `user-{id}`
- Metadata commonly includes `uploaded_by_user_id`, `source`, and original file attributes
- Firestore mirrors ingestion rows under the owning user document path

## Error Handling Pattern

- External providers have dedicated exception classes in `app/Exceptions`
- Operationally optional integrations, such as Firestore mirroring, log warnings instead of failing the request
- Hard business-rule violations use structured exceptions like `UsageLimitExceededException`

## Frontend Code Style

Observed TypeScript/Vue conventions:

- ESM imports with alias-based paths
- Two-space indentation
- No semicolons in inspected files
- Shared API client defined in `resources/ts/utils/api.ts`
- Core plugins registered centrally through `@core/utils/plugins`

## Frontend Architectural Convention

- File-based pages in `resources/ts/pages`
- Layout wrappers in `resources/ts/layouts` and `resources/ts/@layouts`
- Common reusable controls live under `resources/ts/components` and `resources/ts/@core/components`
- Styling is SCSS-driven and split between app-level and template/framework-level layers

## Documentation Convention

- Product/API intent is documented in `docs/`
- Some docs are up to date for Supermemory/Flutter flows, while others still reference older Cognify terminology
- README has not yet been normalized to the current backend-first project identity