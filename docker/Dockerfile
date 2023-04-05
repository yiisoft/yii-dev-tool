FROM yiisoftware/yii-php:8.2-apache

RUN apt-get update && apt-get install -y sqlite3 libsqlite3-dev openssh-client git unzip htop mc && apt-get clean

RUN chmod go+rx /usr/local/bin/composer

RUN install-php-extensions apcu memcached gettext

# configure xdebug
RUN echo "xdebug.discover_client_host=1" >> /usr/local/etc/php/conf.d/xdebug2.ini
RUN echo "xdebug.client_port=9003" >> /usr/local/etc/php/conf.d/xdebug2.ini
RUN echo "xdebug.idekey=yii_dev" >> /usr/local/etc/php/conf.d/xdebug2.ini
