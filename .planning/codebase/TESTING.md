# Testing

Date: 2026-04-21

## Test Stack

- Test runner: PHPUnit 11 via `phpunit/phpunit`
- Laravel test harness: `php artisan test`
- Database isolation: `Illuminate\Foundation\Testing\RefreshDatabase`
- Mocking: PHPUnit mocks and Laravel container overrides
- HTTP client fakes: `Http::fake(...)` in unit/service tests

Primary commands:

- `composer test`
- `php artisan test`

## Test Layout

- `tests/Feature` covers controller endpoints and end-to-end request flows
- `tests/Unit` covers service-level logic and provider wrappers
- `tests/Concerns` contains reusable helpers like `MocksSupermemoryService`

Representative feature coverage:

- `tests/Feature/AuthControllerTest.php`
- `tests/Feature/IngestionControllerTest.php`
- `tests/Feature/OcrControllerTest.php`
- `tests/Feature/SearchControllerTest.php`
- `tests/Feature/DocumentControllerTest.php`
- `tests/Feature/SystemControllerTest.php`
- `tests/Feature/SubscriptionManagementTest.php`
- `tests/Feature/SyncSupermemoryStatusesCommandTest.php`

Representative unit coverage:

- `tests/Unit/GeminiServiceTest.php`
- `tests/Unit/SupermemoryServiceTest.php`

## Common Testing Patterns

### Authenticated API Tests

- Create a `User` through a factory
- Issue a Sanctum token with `$user->createToken('flutter')->plainTextToken`
- Send the bearer token in request headers

### Provider Mocking

- Replace service bindings in the container, for example via `tests/Concerns/MocksSupermemoryService.php`
- Assert method calls and payload shape with PHPUnit `expects(...)`
- Use `Http::fake(...)` for lower-level service unit tests against external API wrappers

### Business Rule Tests

- Seed/construct usage counters directly for rate-limit scenarios
- Override dependencies like `PdfPageCounter` with test doubles when edge cases are input-dependent
- Assert exact JSON fragments and validation errors in feature tests

## Coverage Notes

- Backend API flows appear reasonably well covered for the active Supermemory/Gemini feature set
- There is no visible frontend unit or component test suite in the inspected repository
- No CI workflow was inspected in this pass, so automated remote test enforcement is unknown

## Testing Risks

- `.phpunit.result.cache` references many legacy Cognee/Cognify tests not present in the active `tests/Feature` tree, suggesting stale test metadata from a previous architecture
- Frontend behavior likely relies on manual verification today