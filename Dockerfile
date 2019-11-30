FROM php:alpine

# PHP dependencies, create users
RUN apk add --update --no-cache curl-dev libcap imap-dev openssl-dev \
    && docker-php-ext-install sockets \ 
    && docker-php-ext-install curl \
    && PHP_OPENSSL=yes docker-php-ext-configure imap --with-imap --with-imap-ssl \
    && docker-php-ext-install imap \
    && setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/php \
    && addgroup -S php && adduser -S php -G php \
    && mkdir -p /home/php/telegram/ \
    && mkdir /home/php/telegram/log

# copy all files
WORKDIR /home/php/telegram/
COPY --chown=php:php . /home/php/telegram/

RUN rm -rf /home/php/telegram/Dockerfile /home/php/telegram/.travis.yml /home/php/telegram/dockerpublish.sh

# set server vars
ENV TELEGRAM_API_TOKEN=tbf \
	MAIL_SERVER=tbf \
	MAIL_USER=tbf \
	MAIL_PW=tbf \
	SYSDOMAIN=tbf \
	DELETMAILS=tbf

# open port
EXPOSE 80/tcp

# run
CMD ["php","/home/php/telegram/server.php"]
USER php
