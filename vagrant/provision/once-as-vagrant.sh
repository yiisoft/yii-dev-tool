#!/usr/bin/env bash

source /vagrant/vagrant/provision/common.sh

#== Import script args ==

github_token=$(echo "$1")

#== Provision script ==

info "Provision-script user: `whoami`"

info "Adding GitHub to known hosts"
ssh-keyscan github.com > /home/vagrant/.ssh/known_hosts

info "Configuring composer"
composer config --global github-oauth.github.com ${github_token}

info "Installing yii-dev-tool"
cd /yii-dev-tool
composer --no-progress install

info "Configuring auto-opening project dir after SSH login"
echo 'cd /yii-dev-tool' | tee -a /home/vagrant/.bashrc

info "Enabling colorized prompt for guest console"
sed -i "s/#force_color_prompt=yes/force_color_prompt=yes/" /home/vagrant/.bashrc

info "Configuring of rewriting .bash_history to directory /vagrant/history"
rm -f /home/vagrant/.bash_history
touch /vagrant/vagrant/history/.bash_history
ln -s /vagrant/vagrant/history/.bash_history /home/vagrant/.bash_history

info "Configuring max size of .bash_history"
sed -i "s/HISTSIZE=1000/HISTSIZE=99999999/" /home/vagrant/.bashrc
sed -i "s/HISTFILESIZE=2000/HISTFILESIZE=99999999/" /home/vagrant/.bashrc
