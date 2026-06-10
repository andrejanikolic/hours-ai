# HoursAI — Local Development Setup

Stack: Laravel 11 · MySQL 8 · Redis · Vue 3 · Docker

---

## Project Structure

```
hoursAI/
├── backend/          ← Laravel app
├── frontend/         ← Vue 3 app
├── docker/
│   └── nginx/
│       └── default.conf
├── docker-compose.yml
├── README.md
└── SETUP.md
```

---

## Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- [Node.js 20+](https://nodejs.org/) (for frontend, runs outside Docker)
- [Composer](https://getcomposer.org/) (for initial Laravel scaffold, optional if using Docker)

---

## 1. Clone & Enter

```bash
git clone <repo-url> hoursAI
cd hoursAI
```

---

## 2. Create Laravel Backend

```bash
composer create-project laravel/laravel backend
```

Or if you don't have Composer locally:

```bash
docker run --rm -v $(pwd):/app composer create-project laravel/laravel backend
```

---

## 3. Create Vue Frontend

```bash
npm create vue@latest frontend
# Select: TypeScript ✓, Vue Router ✓, Pinia ✓, ESLint ✓
cd frontend && npm install
```

---

## 4. Docker Compose

Create `docker-compose.yml` in the project root:

```yaml
services:
  app:
    build:
      context: ./backend
      dockerfile: Dockerfile
    container_name: hoursai_app
    restart: unless-stopped
    working_dir: /var/www
    volumes:
      - ./backend:/var/www
    networks:
      - hoursai

  nginx:
    image: nginx:alpine
    container_name: hoursai_nginx
    restart: unless-stopped
    ports:
      - "8000:80"
    volumes:
      - ./backend:/var/www
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - app
    networks:
      - hoursai

  mysql:
    image: mysql:8.0
    container_name: hoursai_mysql
    restart: unless-stopped
    environment:
      MYSQL_DATABASE: hoursai
      MYSQL_ROOT_PASSWORD: root
      MYSQL_USER: hoursai
      MYSQL_PASSWORD: secret
    ports:
      - "3306:3306"
    volumes:
      - mysql_data:/var/lib/mysql
    networks:
      - hoursai

  redis:
    image: redis:alpine
    container_name: hoursai_redis
    restart: unless-stopped
    ports:
      - "6379:6379"
    networks:
      - hoursai

networks:
  hoursai:
    driver: bridge

volumes:
  mysql_data:
```

---

## 5. Laravel Dockerfile

Create `backend/Dockerfile`:

```dockerfile
FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev zip unzip \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

RUN composer install --no-interaction --prefer-dist --optimize-autoloader

RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
```

---

## 6. Nginx Config

Create `docker/nginx/default.conf`:

```nginx
server {
    listen 80;
    index index.php index.html;
    root /var/www/public;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

---

## 7. Laravel Environment

Copy and configure the `.env`:

```bash
cp backend/.env.example backend/.env
```

Update `backend/.env`:

```env
APP_NAME=HoursAI
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=hoursai
DB_USERNAME=hoursai
DB_PASSWORD=secret

REDIS_HOST=redis
REDIS_PORT=6379

DEEPSEEK_API_KEY=your_deepseek_api_key_here
DEEPSEEK_API_URL=https://api.deepseek.com/v1
```

---

## 8. Start Docker

```bash
docker compose up -d
```

Verify all containers are running:

```bash
docker compose ps
```

Expected:

```
hoursai_app     running
hoursai_nginx   running   0.0.0.0:8000->80/tcp
hoursai_mysql   running   0.0.0.0:3306->3306/tcp
hoursai_redis   running   0.0.0.0:6379->6379/tcp
```

---

## 9. Laravel Setup

```bash
# Generate app key
docker compose exec app php artisan key:generate

# Run migrations
docker compose exec app php artisan migrate

# (Optional) Seed mock store hours data
docker compose exec app php artisan db:seed
```

---

## 10. Frontend Setup

The Vue app runs outside Docker via Vite dev server:

```bash
cd frontend
npm install
npm run dev
```

Frontend available at: `http://localhost:5173`

Configure the API base URL in `frontend/.env.local`:

```env
VITE_API_BASE_URL=http://localhost:8000/api
```

---

## 11. Verify Everything Works

```bash
# Backend health check
curl http://localhost:8000/api/health
# Expected: {"status":"ok"}

# Frontend
open http://localhost:5173
```

---

## Running Tests

### PHPUnit (Backend)

```bash
# All tests
docker compose exec app php artisan test

# Specific test file
docker compose exec app php artisan test --filter StoreHoursParseTest
```

### Playwright (E2E)

```bash
cd frontend

# Install Playwright
npm install -D @playwright/test
npx playwright install chromium

# Run E2E tests (requires both backend + frontend running)
npx playwright test

# Run with UI
npx playwright test --ui
```

---

## Useful Commands

```bash
# Tail Laravel logs
docker compose exec app tail -f storage/logs/laravel.log

# Access MySQL directly
docker compose exec mysql mysql -u hoursai -psecret hoursai

# Redis CLI
docker compose exec redis redis-cli

# Restart a single service
docker compose restart app

# Stop everything
docker compose down

# Stop and wipe DB volume
docker compose down -v
```

---

## Troubleshooting

**Permission errors on storage/:**
```bash
docker compose exec app chmod -R 775 storage bootstrap/cache
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
```

**MySQL connection refused:**
Wait ~10 seconds after `docker compose up` for MySQL to finish initializing, then re-run migrations.

**Vite can't reach the API (CORS):**
Add to `backend/config/cors.php`:
```php
'allowed_origins' => ['http://localhost:5173'],
```
