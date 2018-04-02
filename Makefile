.PHONY: all bash build clean down logs restart start status stop tail

SERVER_SERVICE_NAME = docs

all: build start

bash:
	@docker-compose run --rm $(SERVER_SERVICE_NAME) bash

build:
	@docker-compose build

install:
	@docker-compose run --rm $(SERVER_SERVICE_NAME) composer install

clean:
	stop
	@docker-compose rm --force

down:
	@docker-compose down

logs:
	@docker-compose logs -f

restart: stop start

start:
	@docker-compose up -d

status:
	@docker-compose ps

stop:
	@docker-compose stop

tail:
	@docker-compose logs $(SERVER_SERVICE_NAME)