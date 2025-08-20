#!/bin/bash
# eSports Tournament Management System Startup Script

echo "Starting eSports Tournament Management System..."
echo "Running on PHP $(php -v | head -n 1)"

# Make sure uploads directory exists and is writable
mkdir -p uploads
chmod 755 uploads

# Make sure database directory exists
mkdir -p database
chmod 755 database

# Start PHP development server
echo "Starting PHP server on port 5000..."
php -S 0.0.0.0:5000 server.php