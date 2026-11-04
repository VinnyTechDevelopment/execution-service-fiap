# Comandos de desenvolvimento do execution-service (rodam dentro da imagem Docker do
# serviço, que já tem PHP 8.4 + PCOV).
IMAGE := execution-service:dev
RUN   := docker run --rm -v $(CURDIR):/var/www/html -w /var/www/html $(IMAGE)

.PHONY: help image test coverage lint

help: ## Lista os comandos
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  %-10s %s\n", $$1, $$2}'

image: ## Builda a imagem local
	docker build -t $(IMAGE) .

test: image ## Roda a suíte (PHPUnit) sem coverage
	$(RUN) vendor/bin/phpunit --no-coverage

coverage: image ## Gera coverage.xml e aplica o gate de 80%
	$(RUN) sh -c "php -d pcov.enabled=1 vendor/bin/phpunit --coverage-clover coverage.xml --coverage-text --only-summary-for-coverage-text && php scripts/coverage-gate.php coverage.xml 80"

lint: ## Formata o código (Laravel Pint)
	vendor/bin/pint
