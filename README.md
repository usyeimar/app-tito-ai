# Tito AI

A scalable multi-tenant SaaS platform integrating advanced AI workflows. Built with Laravel 13, React 19, and Inertia.js.

## Tech Stack

- **Backend**: Laravel 13 (PHP 8.4)
- **Frontend**: React 19, Inertia.js v2, Tailwind CSS v4
- **Database**: PostgreSQL / MariaDB (Multi-tenant)
- **AI & Real-time**: Laravel AI, Laravel Reverb, Python Runners

## Quick Start

```bash
git clone https://github.com/usyeimar/app-tito-ai.git
cd app-tito-ai

composer install
pnpm install

cp .env.example .env
php artisan key:generate

./vendor/bin/sail up -d
php artisan migrate --seed

pnpm run dev
```

## Testing

```bash
php artisan test
```

## Documentation

- [SIP Trunks Architecture & Integration](docs/SIP-TRUNKS.md)

## License

MIT
