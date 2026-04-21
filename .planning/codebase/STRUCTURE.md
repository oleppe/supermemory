# Structure

Date: 2026-04-21

## Top-Level Layout

- `app/` Laravel application code
- `bootstrap/` framework bootstrapping
- `config/` runtime/service configuration
- `database/` migrations, factories, seeders
- `docs/` integration and API reference docs
- `public/` web root and built assets
- `resources/` frontend source, styles, images, Blade views
- `routes/` Laravel route definitions
- `storage/` logs, framework cache, app files
- `tests/` PHPUnit test suite
- `vendor/` Composer dependencies

## Backend Structure

### Core App

- `app/Http/Controllers` contains the HTTP API controllers
- `app/Http/Requests` contains request validation objects
- `app/Http/Middleware` contains custom middleware such as admin checks
- `app/Models` contains Eloquent models for plans, subscriptions, usage, ingestions, and users
- `app/Services` contains integration and business-logic services
- `app/Console/Commands` contains scheduled/manual console behaviors
- `app/Exceptions` contains provider-specific and usage-limit exception types

### Configuration

- `config/services.php` centralizes third-party credential wiring
- `config/supermemory.php` isolates the Supermemory integration config
- Standard Laravel config files remain in place for auth, cache, DB, mail, queue, and session concerns

### Data Layer

- `database/migrations` defines schema evolution
- `database/factories` supports test data generation
- `database/seeders` seeds plans and supporting records

## Frontend Structure

### App Source

- `resources/ts/main.ts` bootstraps Vue
- `resources/ts/pages` contains route-driven page components
- `resources/ts/layouts` and `resources/ts/@layouts` contain layout framework code
- `resources/ts/components` contains shared app components
- `resources/ts/composables` and `resources/ts/utils` contain client helpers
- `resources/ts/plugins` contains router, Pinia, Vuetify, and icon setup
- `resources/ts/@core` contains template/core UI framework utilities and reusable components

### Presentation Assets

- `resources/styles` and `resources/ts/@layouts/styles` provide SCSS layers
- `resources/views/application.blade.php` is the SPA shell
- `public/build` contains generated frontend assets

## Route Organization

- `routes/api.php` contains the active mobile/API contract
- `routes/web.php` maps everything else to the Vue shell
- `routes/console.php` contains Artisan command registrations

## Testing Structure

- `tests/Feature` focuses on HTTP/API and integration behavior
- `tests/Unit` focuses on isolated service logic
- `tests/Concerns` contains shared mocking helpers
- `tests/TestCase.php` is the common Laravel test base

## Generated / Environment-Coupled Files

- `auto-imports.d.ts`, `components.d.ts`, `typed-router.d.ts`, `env.d.ts`, `shims.d.ts` are generated or tooling-support TS files
- `bootstrap/cache/` holds framework-generated caches
- `storage/logs/laravel.log` captures runtime/test logs
- `node-compile-cache/` and `tsx-0/` look like machine-generated local caches

## Naming And Organization Notes

- Backend follows Laravel’s conventional singular model names and controller/service suffixes
- Frontend uses alias-based imports such as `@/`, `@core/`, and `@layouts/`
- The frontend includes substantial template/framework surface area beyond the Supermemory-specific product code