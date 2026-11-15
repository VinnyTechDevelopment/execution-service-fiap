FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libonig-dev \
    zip \
    unzip \
&& docker-php-ext-configure gd --with-freetype --with-jpeg \
&& docker-php-ext-install -j$(nproc) pdo_mysql mbstring exif pcntl bcmath gd zip opcache \
&& pecl install pcov \
&& docker-php-ext-enable pcov \
&& rm -rf /var/lib/apt/lists/*

# Agente PHP do New Relic (APM + distributed tracing). A License Key é
# injetada em runtime pelo docker/entrypoint.sh a partir de
# NEWRELIC_LICENSE_KEY (Secret do K8s); sem ela (dev local) o agente fica
# inerte e a aplicação sobe normalmente.
# A chave de assinatura do repositório da New Relic usa um certificado
# autoassinado em SHA1, que o verificador de política do Debian trixie
# (sqv) rejeita desde 2026-02-01 independente de como o keyring é
# referenciado — não é algo ajustável do nosso lado. "trusted=yes" pula
# a verificação só pra esse repositório específico (fonte oficial única,
# via HTTPS, restrita a instalar o pacote newrelic-php5).
RUN echo "deb [trusted=yes] https://apt.newrelic.com/debian/ newrelic non-free" > /etc/apt/sources.list.d/newrelic.list \
 && apt-get update \
 && apt-get install -y newrelic-php5 \
 && rm -rf /var/lib/apt/lists/*

# O instalador genérico embutido no .deb (newrelic-install.sh) foi feito pro
# layout do tarball oficial e não bate com onde o .deb realmente instala os
# arquivos (procura /usr/lib/newrelic-php5/daemon, que aqui nem existe — o
# daemon vai pra /usr/bin/newrelic-daemon) — rodá-lo só dá erro. Registramos
# a extensão manualmente: newrelic-20240924.so é o build compatível com a
# PHP API desta imagem (PHP 8.4 → API 20240924; confira com
# `php -i | grep "PHP API"` se algum dia trocar a versão do PHP na FROM).
RUN echo 'extension = "/usr/lib/newrelic-php5/agent/x64/newrelic-20240924.so"' > /usr/local/etc/php/conf.d/newrelic.ini \
 && echo 'newrelic.license = ""' >> /usr/local/etc/php/conf.d/newrelic.ini \
 && echo 'newrelic.appname = "POS Tech"' >> /usr/local/etc/php/conf.d/newrelic.ini \
 && echo 'newrelic.daemon.address = "/tmp/.newrelic.sock"' >> /usr/local/etc/php/conf.d/newrelic.ini \
 && echo 'newrelic.enabled = true' >> /usr/local/etc/php/conf.d/newrelic.ini \
 && echo 'newrelic.distributed_tracing_enabled = true' >> /usr/local/etc/php/conf.d/newrelic.ini \
 && echo 'newrelic.application_logging.enabled = true' >> /usr/local/etc/php/conf.d/newrelic.ini \
 && echo 'newrelic.application_logging.forwarding.enabled = true' >> /usr/local/etc/php/conf.d/newrelic.ini

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-interaction --prefer-dist --no-progress

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
