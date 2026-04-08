# Tito AI Platform

A modern, scalable SaaS platform built with Laravel 13, React (Inertia.js v2), and advanced AI capabilities. This project features a robust multi-tenant architecture and integration with first-party Laravel AI tools.

## 🚀 Key Features

- **Multi-tenancy**: Built with `stancl/tenancy` for full database isolation and custom domains/subdomains per tenant.
- **AI Integration**: Powered by `laravel/ai` for agent orchestration, knowledge base management, and advanced AI workflows.
- **Modern Frontend**: React 19 + Inertia.js v2 with TypeScript for a seamless SPA experience.
- **Advanced Auth**: Laravel Fortify for authentication, WebAuthn (Passkeys) support, and Laravel Passport for secure API access.
- **Knowledge Base**: Complete API for managing documents and categories with built-in versioning for content history.
- **Real-time**: Integration with Laravel Reverb for high-performance WebSocket communication.
- **Infrastructure**: Ready-to-use Docker environment via Laravel Sail.

## 🛠 Tech Stack

- **Backend**: PHP 8.4+, Laravel 13
- **Frontend**: React 19, TypeScript, Tailwind CSS v4
- **State/Routing**: Inertia.js v2, Laravel Wayfinder (typed routes)
- **Database**: PostgreSQL/MariaDB (Central & Tenant isolation)
- **Testing**: Pest PHP v4
- **Formatting**: Laravel Pint (PHP), Prettier & ESLint (JS/TS)

## 📦 Installation

1. **Clone the repository**:
   ```bash
   git clone https://github.com/usyeimar/app-tito-ai.git
   cd app-tito-ai
   ```

2. **Install dependencies**:
   ```bash
   composer install
   npm install
   ```

3. **Configure Environment**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Launch Infrastructure**:
   ```bash
   ./vendor/bin/sail up -d
   ```

5. **Run Migrations & Seed**:
   ```bash
   php artisan migrate --seed
   ```

6. **Build Frontend**:
   ```bash
   npm run dev
   ```

## 🏗 Project Structure

- `app/Models/Tenant/KnowledgeBase/`: Domain logic for the AI knowledge base.
- `services/runners/`: Python-based runners for background tasks and AI processing.
- `services/pipecat/`: Integration for real-time voice and multimodal AI interactions.
- `routes/tenant/`: API and Web routes specific to isolated tenants.

## 🧪 Testing

Run the test suite using Pest:
```bash
php artisan test
```

## 📄 License

This project is licensed under the MIT License.
