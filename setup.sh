#!/bin/bash

# Automatic Make Times - Development Setup Script
# This script ensures consistent development environment setup across all developers

echo "🚀 Setting up Automatic Make Times development environment..."

# Check if Docker is running
if ! docker info >/dev/null 2>&1; then
    echo "❌ Docker is not running. Please start Docker and try again."
    exit 1
fi

# Copy environment file if it doesn't exist
if [ ! -f .env ]; then
    echo "📄 Copying .env.example to .env..."
    cp .env.example .env
    echo "✅ Environment file created"
else
    echo "✅ Environment file already exists"
fi

# Install Composer dependencies
echo "📦 Installing Composer dependencies..."
./vendor/bin/sail composer install

# Generate application key if not set
if grep -q "APP_KEY=\s*$" .env; then
    echo "🔑 Generating application key..."
    ./vendor/bin/sail artisan key:generate
else
    echo "✅ Application key already set"
fi

# Start Docker containers
echo "🐳 Starting Docker containers..."
./vendor/bin/sail up -d

# Wait for services to be ready
echo "⏳ Waiting for services to start..."
sleep 10

# Run database migrations and seeders
echo "🗄️  Setting up database..."
./vendor/bin/sail artisan migrate:fresh --seed

# Install NPM dependencies and build assets
echo "🎨 Building frontend assets..."
./vendor/bin/sail npm install
./vendor/bin/sail npm run build

# Run tests to verify setup
echo "🧪 Running tests to verify setup..."
./vendor/bin/sail test

echo ""
echo "🎉 Setup complete! Your development environment is ready."
echo ""
echo "📚 Useful commands:"
echo "  Start containers:    ./vendor/bin/sail up -d"
echo "  Stop containers:     ./vendor/bin/sail down"
echo "  Run tests:          ./vendor/bin/sail test"
echo "  View logs:          ./vendor/bin/sail logs"
echo "  Access shell:       ./vendor/bin/sail shell"
echo ""
echo "🌐 Application is available at: http://localhost"
echo "📖 API documentation is in the README.md file"
