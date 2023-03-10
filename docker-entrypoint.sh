#!/bin/bash
set -e

#cd /var/www/html
#echo "Installing compose dependencies"
#composer install
echo "Init docs and download repos"
php docs.php sources:init
cd /var/www/html/public/template
echo "Install node dependencies and build template"
npm install
npm run build

exec "$@"
