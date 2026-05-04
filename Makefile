.PHONY: help install test test-unit test-integration coverage stan cs-check cs-fix \
        refactor mutation demo clean check check-full

# ── Colours ───────────────────────────────────────────────────────────────────
BOLD  := \033[1m
CYAN  := \033[36m
GREEN := \033[32m
RESET := \033[0m

# ── Default target: print help ────────────────────────────────────────────────
help:
	@printf "\n$(BOLD)php-io-cli — Development Makefile$(RESET)\n\n"
	@printf "$(CYAN)%-20s$(RESET) %s\n" "make install"       "Install Composer dependencies"
	@printf "$(CYAN)%-20s$(RESET) %s\n" "make test"          "Run full test suite (Unit + Integration)"
	@printf "$(CYAN)%-20s$(RESET) %s\n" "make test-unit"     "Run Unit tests only"
	@printf "$(CYAN)%-20s$(RESET) %s\n" "make test-integration" "Run Integration tests only"
	@printf "$(CYAN)%-20s$(RESET) %s\n" "make coverage"      "Generate HTML coverage report"
	@printf "$(CYAN)%-20s$(RESET) %s\n" "make stan"          "PHPStan static analysis (level 8)"
	@printf "$(CYAN)%-20s$(RESET) %s\n" "make cs-check"      "Check code style (dry-run)"
	@printf "$(CYAN)%-20s$(RESET) %s\n" "make cs-fix"        "Apply code-style fixes"
	@printf "$(CYAN)%-20s$(RESET) %s\n" "make refactor"      "Run Rector automated upgrades"
	@printf "$(CYAN)%-20s$(RESET) %s\n" "make mutation"      "Run Infection mutation testing"
	@printf "$(CYAN)%-20s$(RESET) %s\n" "make demo"          "Launch the interactive component demo"
	@printf "$(CYAN)%-20s$(RESET) %s\n" "make check"         "cs-check + stan + test (CI gate)"
	@printf "$(CYAN)%-20s$(RESET) %s\n" "make check-full"    "check + coverage + mutation"
	@printf "$(CYAN)%-20s$(RESET) %s\n" "make clean"         "Remove build artifacts and caches"
	@echo ""

# ── Dependencies ──────────────────────────────────────────────────────────────
install:
	composer install --no-interaction --prefer-dist

# ── Testing ───────────────────────────────────────────────────────────────────
test:
	vendor/bin/phpunit --no-coverage

test-unit:
	vendor/bin/phpunit --testsuite Unit --no-coverage

test-integration:
	vendor/bin/phpunit --testsuite Integration --no-coverage

coverage:
	vendor/bin/phpunit --coverage-html build/coverage/html --coverage-clover build/coverage/clover.xml
	@printf "\n$(GREEN)✔ Coverage report written to build/coverage/html/$(RESET)\n"

# ── Static analysis ───────────────────────────────────────────────────────────
stan:
	vendor/bin/phpstan analyse --memory-limit=256M

# ── Code style ────────────────────────────────────────────────────────────────
cs-check:
	vendor/bin/php-cs-fixer fix --dry-run --diff --allow-unsupported-php-version=yes --config=php-cs-fixer.php

cs-fix:
	vendor/bin/php-cs-fixer fix --allow-unsupported-php-version=yes --config=php-cs-fixer.php
	@printf "\n$(GREEN)✔ Code style fixes applied.$(RESET)\n"

# ── Refactoring ───────────────────────────────────────────────────────────────
refactor:
	vendor/bin/rector process

# ── Mutation testing ──────────────────────────────────────────────────────────
mutation:
	vendor/bin/infection --threads=max --min-msi=60 --min-covered-msi=80

# ── Demo ─────────────────────────────────────────────────────────────────────
demo:
	php examples/demo.php

# ── Composite gates ──────────────────────────────────────────────────────────
check: cs-check stan test

check-full: cs-check stan coverage mutation
	@printf "\n$(BOLD)$(GREEN)✔ Full quality gate passed.$(RESET)\n"

# ── Clean ─────────────────────────────────────────────────────────────────────
clean:
	rm -rf build/ .phpunit.cache .php-cs-fixer.cache .phpstan.cache \
	       infection.log .rector/ coverage/ coverage-html/ coverage.xml clover.xml
	@printf "$(GREEN)✔ Build artifacts removed.$(RESET)\n"
