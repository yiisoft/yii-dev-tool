#!/usr/bin/env bash

source /vagrant/vagrant/provision/common.sh

#== Import script args ==

git_name=$(echo "$1")
git_email=$(echo "$2")

#== Provision script ==

info "Provision-script user: `whoami`"

if [ "$git_name" != "Your Name" ] && [ "$git_email" != "your-email@example.com" ]
then
    info "Configuring name and email for git commits"
    git config --global --replace-all user.name "$git_name"
    git config --global --replace-all user.email "$git_email"
fi
