# Development Environment Setup

## Requirements

- Docker Desktop
- Git
- A modern terminal (bash/zsh)

## Quick Setup

This project uses Laravel Sail for containerized development.

### Automated Setup (Recommended)

```bash
git clone <repository-url>
cd b-amt
./setup.sh
```

The setup script will:
1. Copy `.env.example` to `.env`
2. Install Composer dependencies
3. Generate application key
4. Start Docker containers (Laravel app, MySQL, Redis)
5. Run database migrations and seeders
6. Build frontend assets
7. Run tests to verify everything works

### Manual Setup

If you prefer to set up manually:

```bash
# Copy environment configuration
cp .env.example .env

# Install dependencies and start containers
./vendor/bin/sail composer install
./vendor/bin/sail up -d

# Generate application key
./vendor/bin/sail artisan key:generate

# Set up database
./vendor/bin/sail artisan migrate:fresh --seed

# Build assets
./vendor/bin/sail npm install && ./vendor/bin/sail npm run build

# Verify setup
./vendor/bin/sail test
```

## Daily Development Commands

```bash
# Start development environment
./vendor/bin/sail up -d

# Stop development environment
./vendor/bin/sail down

# View logs
./vendor/bin/sail logs -f

# Access application shell
./vendor/bin/sail shell

# Run tests
./vendor/bin/sail test

# Run specific test file
./vendor/bin/sail test tests/Feature/OrderApiTest.php

# Clear caches
./vendor/bin/sail artisan cache:clear
./vendor/bin/sail artisan config:clear

# Database operations
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan migrate:fresh --seed
```

## Environment Configuration

The `.env` file is configured for Sail with these key settings:

```bash
# Database (MySQL in Docker)
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password

# Redis (for caching)
REDIS_HOST=redis
REDIS_PORT=6379

# Application
APP_URL=http://localhost
```

## Troubleshooting

### Docker Issues
- Ensure Docker Desktop is running
- Try `./vendor/bin/sail down && ./vendor/bin/sail up -d` to restart containers

### Port Conflicts
- If port 80 is in use, modify `APP_PORT` in `.env`: `APP_PORT=8080`
- Access application at `http://localhost:8080`

### Database Issues
- Reset database: `./vendor/bin/sail artisan migrate:fresh --seed`
- Check container logs: `./vendor/bin/sail logs mysql`

### Redis Issues
- Clear Redis cache: `./vendor/bin/sail artisan cache:clear`
- Check Redis connection: `./vendor/bin/sail redis redis-cli ping`
