TITLE = [clean-core]

all: install

clean-composer-lock:
	@rm -rf composer.lock \
	&& /bin/echo -e "${TITLE} deleted composer.lock"

install:
	@/bin/echo -e "${TITLE} downloading composer ..." \
	&& curl -sS https://getcomposer.org/installer | php \
	&& /bin/echo -e "${TITLE} installing dependencies..." \
	&& php composer.phar install \
	&& /bin/echo -e "${TITLE} dependencies installed"

update:
	@/bin/echo -e "${TITLE} update dependencies..." \
	&& php composer.phar update \
	&& /bin/echo -e "${TITLE} dependencies updated"

self-update:
	@ /bin/echo -e "${TITLE} running composer self update" \
	&& php composer.phar self-update"

unit-tests:
	@/bin/echo "${TITLE} running unit tests suite..." \
	&& ./vendor/bin/phpunit -c test/phpunit.xml --do-not-cache-result --coverage-html tests/unit/coverage \
	&& /bin/echo "${TITLE} unit tests completed"

.PHONY: all
.PHONY: clean-composer-lock
.PHONY: install update self-update
.PHONY: unit-tests
