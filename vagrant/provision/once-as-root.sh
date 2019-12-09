#!/usr/bin/env bash

source /vagrant/vagrant/provision/common.sh

#== Import script args ==

timezone=$(echo "$1")

#== Provision script ==

info "Provision-script user: `whoami`"

export DEBIAN_FRONTEND=noninteractive

info "Configuring timezone"
timedatectl set-timezone ${timezone} --no-ask-password

info "Adding custom apt-repositories"
add-apt-repository -y ppa:ondrej/php

info "Updating OS software"
apt-get update
apt-get upgrade -y

info "Installing additional software"
apt-get install -y \
php7.4-fpm php7.4-cli \
php7.4-curl php7.4-intl php7.4-mbstring php7.4-xml \
php-xdebug \
unzip mc htop

info "Configuring PHP"
sed -i 's/display_errors = Off/display_errors = On/g' /etc/php/7.4/fpm/php.ini

info "Configuring PHP-FPM"
sed -i 's/user = www-data/user = vagrant/g' /etc/php/7.4/fpm/pool.d/www.conf
sed -i 's/group = www-data/group = vagrant/g' /etc/php/7.4/fpm/pool.d/www.conf
sed -i 's/owner = www-data/owner = vagrant/g' /etc/php/7.4/fpm/pool.d/www.conf

info "Configuring XDebug"
cat << EOF > /etc/php/7.4/mods-available/xdebug.ini
zend_extension=xdebug.so
xdebug.remote_enable=1
xdebug.remote_connect_back=1
xdebug.profiler_enable_trigger=1
xdebug.profiler_output_dir = "/vagrant/vagrant/xdebug-profiler-output"
xdebug.profiler_output_name = "cachegrind.out.%u"
xdebug.remote_host=192.168.135.1
EOF

info "Installing composer"
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
