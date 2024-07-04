FROM php:8.2.9-cli-alpine

RUN apk --no-cache add mysql-client \
    && rm -rf /tmp/* /var/cache/apk/*

WORKDIR /var/app

COPY /composer.* .
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer install --no-ansi --no-dev --no-interaction --no-progress --optimize-autoloader --no-scripts --no-cache \
    && rm /usr/bin/composer

COPY src /var/app

RUN chmod +x run.sh

CMD ["sh", "run.sh"]
