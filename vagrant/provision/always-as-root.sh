#!/usr/bin/env bash

source /vagrant/vagrant/provision/common.sh

#== Provision script ==

info "Provision-script user: `whoami`"

info "Restarting web-stack"
service php7.2-fpm restart
