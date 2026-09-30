#!/bin/bash
# Runs before every deploy on Railway (set as the Pre-Deploy Command in
# the service's Deploy settings). Make sure this file is executable:
#   chmod +x railway/init-app.sh

# Exit immediately if any command fails, so a bad migration doesn't
# silently continue into a half-set-up deploy.
set -e

# Apply any new migrations
php artisan migrate --force

# Clear anything cached from the previous deploy, then rebuild fresh
php artisan optimize:clear
php artisan config:cache
php artisan event:cache
php artisan route:cache
