FROM node:16 AS node
FROM php:7.4-rc-apache

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
COPY --from=node /usr/local/bin/node /usr/local/bin/node
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm

# Copy the PHP settings into place
COPY .docker/php.ini /usr/local/etc/php/

# Add the Apache config file
COPY .docker/docs.conf /etc/apache2/sites-available/

# Install composer
RUN cd /usr/src && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Enable Apache rewrite module, create the /var/www/html/public directory, disable the default Apache config file, and
# activate the docs Apache config file.
RUN a2enmod rewrite \
    && mkdir /var/www/html/public \
    && a2dissite 000-default \
    && a2ensite docs

# Install Git
RUN apt-get update && apt-get install -y --force-yes git zlib1g-dev libicu-dev g++ \
     libzip-dev \
     zip \
     && docker-php-ext-configure intl \
     && docker-php-ext-install intl \
     && docker-php-ext-configure zip --with-libzip \
     && docker-php-ext-install zip

#Set final permissions
RUN mkdir /var/www/.npm && chown -R www-data:www-data /var/www/.npm

COPY composer.json /var/www/html/composer.json
RUN composer install

COPY docker-entrypoint.sh /entrypoint.sh
RUN ["chmod", "+x", "/entrypoint.sh"]

USER www-data

ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]

