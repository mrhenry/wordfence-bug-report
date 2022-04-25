.PHONY: pull all \
	php81 \
	php80 \
	php74 \
	php73 \
	php72 \
	php71 \
	php70 \
	php56 \
	php55 \
	php54 \
	php53

all: \
	php81 \
	php80 \
	php74 \
	php73 \
	php72 \
	php71 \
	php70 \
	php56 \
	php55 \
	php54 \
	php53

pull:
	docker pull php:8.1
	docker pull php:8.0
	docker pull php:7.4
	docker pull php:7.3
	docker pull php:7.2
	docker pull php:7.1
	docker pull php:7.0
	docker pull php:5.6
	docker pull php:5.5
	docker pull php:5.4
	docker pull php:5.3

php81:
	@mkdir -p results
	@docker run --rm -v $(PWD):/app -w /app php:8.1 php /app/test/test.php

php80:
	@mkdir -p results
	@docker run --rm -v $(PWD):/app -w /app php:8.0 php /app/test/test.php

php74:
	@mkdir -p results
	@docker run --rm -v $(PWD):/app -w /app php:7.4 php /app/test/test.php

php73:
	@mkdir -p results
	@docker run --rm -v $(PWD):/app -w /app php:7.3 php /app/test/test.php

php72:
	@mkdir -p results
	@docker run --rm -v $(PWD):/app -w /app php:7.2 php /app/test/test.php

php71:
	@mkdir -p results
	@docker run --rm -v $(PWD):/app -w /app php:7.1 php /app/test/test.php

php70:
	@mkdir -p results
	@docker run --rm -v $(PWD):/app -w /app php:7.0 php /app/test/test.php

php56:
	@mkdir -p results
	@docker run --rm -v $(PWD):/app -w /app php:5.6 php /app/test/test.php

php55:
	@mkdir -p results
	@docker run --rm -v $(PWD):/app -w /app php:5.5 php /app/test/test.php

php54:
	@mkdir -p results
	@docker run --rm -v $(PWD):/app -w /app php:5.4 php /app/test/test.php

php53:
	@mkdir -p results
	@docker run --rm -v $(PWD):/app -w /app php:5.3 php /app/test/test.php
