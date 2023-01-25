# This is a Makefile that implements helpers for the docker stack
#

CONTAINER_NAME=php

.PHONY: cli bash docker-up docker-down

# Run a bash in a Docker container as your local user,
# so files are created owned by that user instead of root.
#
# For this to work, the user inside the container must have the same ID as the user outside.
# The name does not matter much but to avoid confusion it is helpful to make them have the same user name.
# You get a users ID by running the `id -u` command, and the users name is `whoami`.
cli: bash
bash: docker-up vendor/autoload.php
	@echo "\nYou are now in the \033[1m$(CONTAINER_NAME)\033[0m container.\n"
	@docker-compose exec --user=$$(id -u) $(CONTAINER_NAME) php -v && echo ""
	@docker-compose exec --user=$$(id -u) $(CONTAINER_NAME) bash

# 1. Start Docker containers
# 2. Create a user with the same name and ID inside the docker conterainer if it does not exists.
#    The user is also added to the www-data group to work well with files created by the webserver.
#    The users home directory is /app/docker/home so bash history files are stored in the docker/home directory in this repo.
docker-up: docker-compose.override.yml docker/home/docker-build
	docker-compose up -d
	docker-compose exec $(CONTAINER_NAME) bash -c "grep '^$(shell whoami):' /etc/passwd || useradd '$(shell whoami)' --uid=$(shell id -u) -G www-data -s /bin/bash -d /app/docker/home"

# auto rebuild docker containers when Dockerfile changes
# will change timestamp of runtime/docker-build to only run this when docker files change
docker/home/docker-build: $(shell find */Dockerfile)
	docker-compose build
	touch $@

# Stop Docker containers
docker-down:
	docker-compose down --remove-orphans

# Run composer install if vendor/autoload.php does not exist or is outdated (older than composer.json)
vendor/autoload.php: composer.json docker-up
	@docker-compose exec --user=$$(id -u) $(CONTAINER_NAME) composer install

# create docker-compose.override.yml if it does not exist
docker-compose.override.yml: docker-compose.override.dist.yml
	test -f $@ || cp $< $@
