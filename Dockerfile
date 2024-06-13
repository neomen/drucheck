FROM composer:2 AS composer
FROM php:8.1-cli-alpine

ENV COMPOSER_ALLOW_SUPERUSER=1

# hadolint ignore=DL3006
RUN apk --no-cache add git zlib-dev libpng-dev

COPY --from=composer /usr/bin/composer /usr/bin/composer

ENV PATH="${PATH}:/root/.composer/vendor/bin"

RUN docker-php-ext-install gd


# Install phpcs and set the code sniffer path
RUN composer global config --no-plugins allow-plugins.dealerdirect/phpcodesniffer-composer-installer true;\
	composer global require drupal/coder; \
	composer global require php-parallel-lint/php-parallel-lint; \
	composer global config --no-plugins allow-plugins.phpstan/extension-installer true; \
	composer global require phpstan/phpstan phpstan/extension-installer mglaman/phpstan-drupal phpstan/phpstan-deprecation-rules;

# Install dependencies
RUN apk add \
	--update \
	--no-cache \
	# Deployment
	bash \
	git \
	gd \
	rsync \
	# Front-end tools
	nodejs \
	npm \
	# Tools for imagemin
	autoconf \
	automake \
	g++ \
	openssh \
	libc6-compat \
	libjpeg-turbo-dev \
	libpng-dev \
	make

RUN npm install -g git+https://github.com/streetsidesoftware/cspell-cli
RUN npm install -g stylelint stylelint-config-standard stylelint-order stylelint-junit-formatter
# Set workdirs
RUN mkdir -p /downloads
RUN composer create-project drupal/recommended-project /downloads/drupal
RUN mkdir -p /downloads/drupal/web/modules/custom/workspace
COPY phpstan.neon /downloads/phpstan.neon
RUN curl -o /downloads/.cspell.json https://git.drupalcode.org/project/gitlab_templates/-/raw/main/assets/.cspell.json
COPY prepare-cspell.php /downloads/prepare-cspell.php
COPY entrypoint.sh /entrypoint.sh
WORKDIR /downloads/drupal/web/modules/custom/workspace
ENTRYPOINT ["/entrypoint.sh"]
