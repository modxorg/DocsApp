FROM php:7.1.8-apache

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
RUN apt-get update && apt-get install -y git