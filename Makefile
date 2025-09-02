.PHONY: install smoke test phpcs psalm psalm-taint state gen-features gen-context

install:
	composer install --no-interaction --prefer-dist

smoke:
	composer smoke

test:
	composer test

phpcs:
	composer phpcs || true

psalm:
	composer psalm || true

psalm-taint:
	composer psalm:taint || true

gen-features:
	composer gen:features

gen-context:
	composer gen:context

state:
	bash scripts/update_state.sh

baseline-check:
	@php scripts/baseline-check.php --current-phase=$(PHASE)

baseline-compare:
	@php scripts/baseline-compare.php --from=$(FROM) --to=$(TO)

gap-analysis:
	@php scripts/gap-analysis.php --target-phase=$(TARGET)

pre-commit: baseline-check
	@if [ $$? -ne 0 ]; then \
		echo "❌ Baseline check failed. Run 'make gap-analysis TARGET=EXPANSION' for details."; \
		exit 1; \
	fi
