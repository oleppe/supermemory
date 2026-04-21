# Concerns

Date: 2026-04-21

## Major Concerns

### Architecture Drift From Earlier Cognee/Cognify Design

Evidence of a previous architecture still exists in multiple places:

- `app/Http/Controllers/CognifyController.php`
- `app/Http/Requests/CognifyRequest.php`
- `docs/flutter-api-integration.md`
- `README.md`
- `.phpunit.result.cache`
- `storage/logs/laravel.log`

The active API routes in `routes/api.php` do not expose a cognify endpoint, while docs and leftover classes still reference it. That increases onboarding cost and raises the chance of implementing against stale contracts.

### Template Bloat In Frontend Surface

The Vue frontend contains a large amount of generic admin-template code under `resources/ts/@core`, `resources/ts/@layouts`, dialogs, charts, and UI helpers. That gives fast UI primitives, but it also increases:

- maintenance surface
- dependency load
- noise when searching for product-specific logic

This is especially visible because the current README still starts as a generic Vue template.

### Mixed Package-Manager Signals

The repo includes `pnpm-lock.yaml`, but backend setup scripts call `npm install` and `npm run build`. This can lead to inconsistent local environments, lock drift, and different dependency trees between contributors.

## Operational Concerns

### Optional Firestore Mirroring Can Hide State Drift

`app/Services/FirestoreSyncService.php` intentionally degrades to warnings when Firebase is misconfigured or the sync fails. That protects API availability, but it also means clients depending on Firestore status can quietly drift away from SQL truth if logs are not monitored.

### Usage Limit Correctness Depends On Careful Call Ordering

`app/Services/UsageLimitService.php` is robust and uses transactions/locks for counters, but the overall workflow still depends on controllers enforcing limits before upstream actions and consuming them after success. Any future endpoint that skips that pattern can create billing/abuse inconsistencies.

## Security And Secrets Concerns

### Local Service Account Handling Requires Discipline

- `.env` references `FIREBASE_SERVICE_ACCOUNT_PATH="/service-account.json"`
- `.gitignore` excludes `service-account.json`

That is better than committing credentials, but the project relies on developers placing a sensitive JSON file at the repo root or root-relative path. This deserves explicit setup guidance and regular checks to avoid accidental leakage outside Git.

## Quality Concerns

### Stale Generated And Cache Artifacts In Repository Root

Items such as `node-compile-cache/`, `tsx-0/`, and `.phpunit.result.cache` indicate local-machine artifacts are present. They add noise to the repo and can mislead tooling or contributors if they are treated as source of truth.

### Frontend TODOs Are Still Open In Framework Code

Several TODO markers remain in shipped frontend files, for example:

- `resources/ts/@core/composable/useSkins.ts`
- `resources/ts/@layouts/components/HorizontalNavLayout.vue`
- `resources/ts/@layouts/components/VerticalNavLayout.vue`
- `resources/ts/@core/initCore.ts`

These are not critical on their own, but they reinforce that parts of the UI layer are inherited template code rather than fully curated product code.

## Recommended Follow-Up

1. Decide whether legacy Cognee/Cognify code is still part of the roadmap or should be removed/documented as deprecated.
2. Normalize package-manager usage to either npm or pnpm.
3. Update `README.md` to describe the actual Laravel API + Flutter + Vue architecture.
4. Consider trimming or isolating unused frontend template modules.
5. Add explicit operational guidance for Firestore credential management and sync monitoring.