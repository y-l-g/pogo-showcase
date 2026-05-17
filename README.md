# Pogo Showcase

Pogo Showcase is a demonstration Laravel/Vue application for the Pogo
FrankenPHP extension suite.

It exists to show how a PHP application can be compiled and deployed as a
single static FrankenPHP binary containing:

- Laravel and Vue/Inertia frontend assets.
- FrankenPHP, Caddy, and PHP.
- Pogo request-scoped parallel jobs.
- Pogo Queue for in-memory background work.
- Pogo WebSocket for realtime broadcasting.
- Pogo Scheduler for embedded scheduled command execution.
- A small runtime bootstrap that prepares SQLite storage, generated secrets,
  Laravel storage paths, and migrations.

## Production status

This repository is a showcase and reference app. It should not be treated as a
production application template.

The included Pogo modules are experimental. Use the showcase to evaluate the
architecture, static-binary deployment model, and developer experience before
adopting any module in a production system.

## Static binary

See [docs/single-binary.md](docs/single-binary.md) for the current build and run
instructions.

The binary creates a `data/` directory next to itself on first start. That
directory stores the SQLite database, Laravel storage, logs, generated app key,
and WebSocket secrets.

## Local development

This app is still a Laravel/Vue project during development:

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
php artisan test
```
