#!/usr/bin/env sh

if [ -f /www/.update-sources ]; then
    rm /www/.update-sources
    echo "Updating sources"
    cd /www/
    php docs.php sources:update
fi
