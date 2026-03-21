# Event Ticket Scanner

A Laravel 13 web application for managing events and scanning tickets at the door. Built with Filament 4 admin panel and Livewire 3 real-time scanner.

## Features

- **Filament 4 admin panel** — manage events, ticket tiers, and attendees
- **Livewire scanner page** — real-time door check-in with dark UI
- **Auto-generated ticket codes** — format `EVT-XXXXXXXX`
- **Status tracking** — `confirmed` → `checked_in` → `cancelled`
- **Live counter** — scanner page polls every 5 seconds

## Tech Stack

PHP 8.3+, Laravel 13, Filament 4, Livewire 3, Tailwind CSS v4, Vite, SQLite

## Prerequisites

- PHP >= 8.3 with extensions: `pdo`, `pdo_sqlite`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`
- Composer >= 2.x
- Node.js >= 20.x + npm
- Git

## Installation

```bash
git clone https://github.com/balbboa/event-ticket-scanner.git
cd event-ticket-scanner

# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Environment setup
cp .env.example .env
php artisan key:generate

# Database setup
touch database/database.sqlite
php artisan migrate

# Seed demo data (optional)
php artisan db:seed --class=EventSeeder

# Create admin user
php artisan make:filament-user

# Build frontend assets
npm run build

# Create storage symlink
php artisan storage:link
```

## Running Locally

```bash
# Start Laravel dev server
php artisan serve

# In a separate terminal — hot-reload frontend assets
npm run dev
```

Visit: http://localhost:8000 (redirects to `/admin`)

## Key URLs

| URL | Description |
|-----|-------------|
| `/admin` | Filament admin panel |
| `/admin/events` | Event management |
| `/admin/attendees` | Attendee management |
| `/events/{id}/scanner` | Ticket scanner (auth required) |

## Development Workflow

- Models: `app/Models/`
- Livewire components: `app/Livewire/`
- Filament resources: `app/Filament/Resources/`
- Views: `resources/views/`
- Run `npm run dev` for hot reload during frontend work

## Useful Artisan Commands

```bash
php artisan migrate:fresh --seed   # Reset DB and re-seed
php artisan make:filament-user     # Create admin user
php artisan route:list             # List all routes
php artisan tinker                 # Interactive REPL
```

## Database Schema

- `events` → `ticket_tiers` → `attendees` (cascade delete)
- Attendee statuses: `confirmed`, `checked_in`, `cancelled`
- Ticket codes auto-generated on attendee creation

## Testing

```bash
php artisan test
```
