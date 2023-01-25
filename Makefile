# This is a Makefile that implements helpers for a docker stack
#
# This example shows how to run a bash in a docker-compose container as your local user,
# so files are created owned by that user instead of root.
#
# For this to work, the user inside the container must have the same ID as the user outside.
# The name does not matter much but to avoid confusion it is helpful to make them have the same user name.
# You get a users ID by running the `id -u` command, and the users name is `whoami`.
#
# How it works:
# line 28: create a user with the same name and ID inside the docker conterainer if it does not exists.
#          The user is also added to the www-data group to work well with files created by the webserver.
#          The users home directory is /app/docker/home so bash history files are stored in the docker/home directory in this repo.
# line 24: run a bash with the same user ID
#

CONTAINER_NAME=php

.PHONY: cli bash docker-up docker-down

cli: bash
bash: docker-up vendor/autoload.php
	@echo "\nYou are now in the \033[1m$(CONTAINER_NAME)\033[0m container.\n"
	@docker-compose exec --user=$$(id -u) $(CONTAINER_NAME) bash

docker-up:
	docker-compose up -d
	docker-compose exec $(CONTAINER_NAME) bash -c "grep '^$(shell whoami):' /etc/passwd || useradd '$(shell whoami)' --uid=$(shell id -u) -G www-data -s /bin/bash -d /app/docker/home"

docker-down:
	docker-compose down --remove-orphans

vendor/autoload.php: composer.json docker-up
	@docker-compose exec --user=$$(id -u) $(CONTAINER_NAME) composer install
