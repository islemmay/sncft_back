# SNCFT — Real-time Train Tracking and Delay Management

Stack: **Symfony 7** (PHP 8.2+), **MySQL**, **React (Vite)**, **session authentication** (no JWT), **Chart.js**, **Axios** (`withCredentials: true`).

## Prerequisites

- PHP 8.2+, Composer, MySQL 8
- Node.js 20+ (for the frontend)

## 1. Backend (Symfony)

```bash
cd sncft_project
composer install
```

Configure the database in `.env` or `.env.local`:

```env
DATABASE_URL="mysql://USER:PASSWORD@127.0.0.1:3306/sncft?serverVersion=8.0.32&charset=utf8mb4"
APP_SECRET="generate_a_random_secret"
```

Use an **empty** database (or create a new one). If tables already exist from an older schema, drop them or use another database name before migrating.

```bash
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction
```

Optional demo data (admin / agent / responsable / user accounts and sample trains, gares, trajet):

```bash
php bin/console app:seed-demo
```

| Email               | Password  | Role        |
|---------------------|-----------|-------------|
| admin@sncft.local   | admin123  | ROLE_ADMIN  |
| agent@sncft.local   | agent123  | ROLE_AGENT  |
| resp@sncft.local    | resp123   | ROLE_RESPONSABLE |
| user@sncft.local    | user123   | ROLE_USER   |

Start the API (example):

```bash
php -S localhost:8000 -t public
```

Or: `symfony server:start` (Symfony CLI).

## 2. Frontend (React)

```bash
cd frontend
cp .env.example .env
npm install
npm run dev
```

Default API URL: `http://localhost:8000` (`VITE_API_URL` in `frontend/.env`).

Open `http://localhost:3000`.

## 3. CORS and sessions

- Nelmio CORS allows origin `http://localhost:3000` with **credentials**.
- Authentication uses **Symfony Security** with **JSON login** (`POST /api/login`) and **PHP sessions** (cookies). The React app sends cookies via Axios `withCredentials: true`.

## API overview (prefix `/api`)

| Area        | Endpoints (examples) |
|------------|------------------------|
| Auth       | `POST /login`, `POST /register`, `GET /logout`, `GET /me` |
| Admin      | CRUD `/users`, `GET /logs` |
| Agent      | CRUD `/trains`, `/gares`, `/trajets`, `/passages` |
| Responsable| `/statistiques`, `POST /statistiques/rapport`, `GET /statistics/chart-data` |
| All users  | `GET /trajets`, `GET /passages` (with filters) |

**Registration:** `service` code `AD` → `ROLE_ADMIN`, `AG` → `ROLE_AGENT`, `RS` → `ROLE_RESPONSABLE`, otherwise `ROLE_USER`.

**Passages:** `retardMinutes` and classification are computed from `heureReelle` − `heureTheorique` unless `classification` is `CANCELLED`.

## Production notes

- Set `APP_ENV=prod`, strong `APP_SECRET`, HTTPS, and tighten CORS origins.
- Run `npm run build` in `frontend` and serve `frontend/dist` behind your web server or reverse proxy.
- Configure session cookies (`cookie_secure`, `cookie_samesite`) for your domain.
