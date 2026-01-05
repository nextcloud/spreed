FROM php:8.4-apache-trixie

ARG GIT_REF=master
ARG NEXTCLOUD_REPO=https://github.com/nextcloud/server.git
ARG NODE_VERSION=25.2.1

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip rsync nano less \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    libzip-dev libxml2-dev libicu-dev libgmp-dev \
    libbz2-dev libexif-dev libwebp-dev \
    libmagickwand-dev util-linux sudo \
    ca-certificates curl \
    && rm -rf /var/lib/apt/lists/*


RUN set -eux; \
    arch="$(dpkg --print-architecture)"; \
    case "$arch" in \
      amd64) node_arch='x64' ;; \
      arm64) node_arch='arm64' ;; \
      armhf) node_arch='armv7l' ;; \
      *) echo "Unsupported architecture: $arch"; exit 1 ;; \
    esac; \
    curl -fsSLO "https://nodejs.org/dist/v${NODE_VERSION}/node-v${NODE_VERSION}-linux-${node_arch}.tar.xz"; \
    tar -xJf "node-v${NODE_VERSION}-linux-${node_arch}.tar.xz" -C /usr/local --strip-components=1 --no-same-owner; \
    rm "node-v${NODE_VERSION}-linux-${node_arch}.tar.xz"; \
    ln -s /usr/local/bin/node /usr/local/bin/nodejs

RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
 && docker-php-ext-install -j"$(nproc)" \
    gd \
    bcmath \
    bz2 \
    exif \
    intl \
    gmp \
    opcache \
    pcntl \
    pdo_mysql \
    zip \
    xml \
    mysqli


RUN pecl install redis apcu && docker-php-ext-enable redis apcu

RUN pecl install imagick && docker-php-ext-enable imagick || true

RUN a2enmod rewrite headers env dir mime setenvif

RUN mkdir /var/www/.npm && sudo chown -R www-data:www-data /var/www/.npm
RUN mkdir mkdir /var/www/.cache && sudo chown -R www-data:www-data /var/www/.cache

RUN { \
  echo "memory_limit=512M"; \
  echo "upload_max_filesize=512M"; \
  echo "post_max_size=512M"; \
  echo "max_execution_time=360"; \
  echo "output_buffering=0"; \
} > /usr/local/etc/php/conf.d/nextcloud.ini

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
 && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
 && rm composer-setup.php

#COPY nextcloud/ ./
#RUN git clone --single-branch --depth=1 --branch "$GIT_REF" --recurse-submodules "$NEXTCLOUD_REPO" /usr/src/nextcloud
RUN set -eux; \
    git init /usr/src/nextcloud; \
    cd /usr/src/nextcloud; \
    git remote add origin "$NEXTCLOUD_REPO"; \
    if echo "$GIT_REF" | grep -Eq '^[0-9a-f]{7,40}$'; then \
      # GIT_REF looks like a commit SHA
      git fetch --depth=1 origin "$GIT_REF"; \
      git checkout --detach FETCH_HEAD; \
    else \
      # GIT_REF is a branch or tag
      git fetch --depth=1 --tags origin "$GIT_REF"; \
      git checkout -q "$GIT_REF"; \
    fi; \
    # bring in submodules shallowly
    git submodule update --init --recursive --depth=1

WORKDIR /usr/src/nextcloud


RUN if [ -d .git ]; then \
      git remote set-url origin "$NEXTCLOUD_REPO" || true; \
    fi

# RUN if [ -d .git ]; then \
#       git fetch --all --tags; \
#       git checkout "$GIT_REF"; \
#       git submodule update --init --recursive; \
#     else \
#       echo "WARNING: ./nextcloud has no .git; skipping fetch/checkout/submodules"; \
#     fi

ENV COMPOSER_ALLOW_SUPERUSER=1
RUN if [ -f composer.json ]; then composer install --no-dev -o --no-interaction --no-ansi || true; fi


WORKDIR /var/www/html
RUN rm -rf /var/www/html/* \
 && rsync -a /usr/src/nextcloud/ /var/www/html/ \
 && chown -R www-data:www-data /var/www/html

RUN mkdir -p /var/www/html/config /var/www/html/data /var/www/html/custom_apps /var/www/html/themes \
 && chown -R www-data:www-data /var/www/html


HEALTHCHECK --interval=30s --timeout=10s --retries=10 \
  CMD php -r 'echo file_exists("/var/www/html/index.php") ? "OK" : "NO";' | grep -q OK || exit 1

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

EXPOSE 80
CMD ["apache2-foreground"]
