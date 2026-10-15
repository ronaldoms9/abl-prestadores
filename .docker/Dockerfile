FROM ubuntu:24.04

LABEL maintainer="Taylor Otwell"


ARG WWWGROUP
ARG NODE_VERSION=22

WORKDIR /var/www/html

#ENV DEBIAN_FRONTEND noninteractive
ENV TZ=UTC

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

#RUN echo "deb http://security.ubuntu.com/ubuntu focal-security main" | tee /etc/apt/sources.list.d/focal-security.list
RUN apt-get update && apt-get upgrade -y


RUN apt-get install -y gnupg gosu curl ca-certificates zip unzip git supervisor libcap2-bin libpng-dev dnsutils librsvg2-bin
RUN  apt install software-properties-common -y
RUN LC_ALL=C.UTF-8  add-apt-repository ppa:ondrej/php
RUN apt install php8.4 libapache2-mod-php8.4 -y
RUN apt install unzip
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && php -r "unlink('composer-setup.php');"

#RUN curl -sS 'https://keyserver.ubuntu.com/pks/lookup?op=get&search=0x14aa40ec0831756756d7f66c4f4ea0aae5267a6c' | gpg --dearmor | tee /usr/share/keyrings/ppa_ondrej_php.gpg > /dev/null
#RUN echo "deb [signed-by=/usr/share/keyrings/ppa_ondrej_php.gpg] https://ppa.launchpadcontent.net/ondrej/php/ubuntu focal main" > /etc/apt/sources.list.d/ppa_ondrej_php.list
RUN apt-get update \
    && apt-get install -y php8.4-cli php8.4-dev \
       php8.4-gd php8.4-imagick \
       php8.4-xdebug \
       php8.4-curl php8.4-memcached \
       php8.4-imap php8.4-mysql php8.4-mbstring \
       php8.4-xml php8.4-zip php8.4-bcmath php8.4-soap \
       php8.4-intl php8.4-readline php8.4-pcov \
       php8.4-msgpack php8.4-igbinary php8.4-ldap \
       php8.4-redis php8.4-swoole
RUN apt-get install -y  curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer \
    && curl -sLS https://deb.nodesource.com/setup_$NODE_VERSION.x | bash - \
    && apt-get install gcc g++ make \
    && apt-get install -y nodejs \
    && npm install -g npm \
    && curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | gpg --dearmor | tee /usr/share/keyrings/yarnkey.gpg >/dev/null \
    && echo "deb [signed-by=/usr/share/keyrings/yarnkey.gpg] https://dl.yarnpkg.com/debian/ stable main" > /etc/apt/sources.list.d/yarn.list \
    && curl -sS https://www.postgresql.org/media/keys/ACCC4CF8.asc | gpg --dearmor | tee /usr/share/keyrings/pgdg.gpg >/dev/null \
    && echo "deb [signed-by=/usr/share/keyrings/pgdg.gpg] http://apt.postgresql.org/pub/repos/apt focal-pgdg main" > /etc/apt/sources.list.d/pgdg.list \
    && apt-get update \
    && apt-get install -y yarn \
    && apt-get install -y mysql-client \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN update-alternatives --set php /usr/bin/php8.4

RUN setcap "cap_net_bind_service=+ep" /usr/bin/php8.4


#RUN groupadd --force -g $WWWGROUP sail
#RUN useradd -ms /bin/bash --no-user-group -g $WWWGROUP -u 1337 sail

COPY start-container /usr/local/bin/start-container
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY php.ini /etc/php/8.4/cli/conf.d/99-sail.ini
RUN chmod +x /usr/local/bin/start-container

# RUN composer install --working-dir=/var/www/html

EXPOSE 8000

ENTRYPOINT ["start-container"]
