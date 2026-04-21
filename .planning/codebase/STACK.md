# Stack

Date: 2026-04-21

## Overview

This repository is a Laravel 12 application that exposes a JSON API for a Flutter client and also serves a Vue 3 + Vuetify single-page admin frontend. The backend owns authentication, subscription and usage enforcement, upstream AI/search integrations, and persistence. The frontend is bundled with Vite and mounted through a catch-all Blade view.

## Backend Runtime

- Language: PHP 8.2+ via `composer.json`
- Framework: Laravel 12 via `laravel/framework`
- API auth: Laravel Sanctum via `laravel/sanctum`
- CLI/dev helpers: Laravel Tinker, Sail, Pail, Pint
- PDF parsing: `smalot/pdfparser`
- Cloud document mirror: `google/cloud-firestore`

Key backend entrypoints:

- `bootstrap/app.php` wires web, API, console, and `/up` health routing.
- `routes/api.php` defines the mobile/API surface.
- `routes/web.php` serves the SPA shell from `resources/views/application.blade.php`.
- `artisan` is the Laravel CLI entrypoint.

## Frontend Runtime

- Language: TypeScript
- Framework: Vue 3.5
- Bundler: Vite 7
- UI system: Vuetify 3
- State: Pinia
- Routing: `vue-router` plus `unplugin-vue-router`
- Networking: `ofetch`
- Rich UI dependencies: TipTap, ApexCharts, Chart.js, Mapbox GL, Swiper, Shepherd, VueUse

Key frontend entrypoints:

- `resources/ts/main.ts` boots the Vue app.
- `resources/ts/App.vue` is the root application component.
- `resources/ts/plugins/1.router/index.ts` and generated router types drive file-based routing.
- `resources/ts/utils/api.ts` creates the shared authenticated API client.

## Tooling And Build

Node/package tooling from `package.json`:

- `vite` for local development and production builds
- `vue-tsc` for type-checking
- `eslint` with Vue, TypeScript, import, promise, unicorn, sonar, and regex plugins
- `stylelint` with SCSS support
- `msw` for mock service worker setup
- `tsx` for utility/build scripts

Composer scripts from `composer.json`:

- `composer run setup` installs PHP and JS deps, prepares `.env`, generates app key, migrates DB, and builds assets
- `composer run dev` runs Laravel server, queue worker, logs, and Vite concurrently
- `composer test` clears config and runs `php artisan test`

## Storage And Infra

- Primary relational persistence appears to be MySQL, backed by Laravel migrations in `database/migrations`
- Redis is configured in `compose.yaml`
- Dockerized local dev uses Laravel Sail in `compose.yaml`
- Built frontend assets land in `public/build`
- Runtime/generated app files use `storage/`

## Configuration Surface

Important config files:

- `config/services.php` for Gemini and Firebase credentials
- `config/supermemory.php` for Supermemory base URL, key, and timeout
- `config/auth.php`, `config/sanctum.php`, `config/database.php`, `config/queue.php`
- `vite.config.ts`, `tsconfig.json`, `themeConfig.ts`
- `.env` and `.env.example`

## External Dependency Notes

- The repo has both `package.json` and `pnpm-lock.yaml`, but Composer setup scripts call `npm install`; package-manager discipline is mixed.
- The top-level `README.md` is still partly template-oriented and does not fully describe the current backend/API architecture.
- A committed `vendor/` directory is present, which makes the repository heavier than a typical Laravel app checkout.