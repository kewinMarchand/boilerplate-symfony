LHCI_PROJECT := boilerplate-symfony-lhci
LHCI_PORT := 3230
# Valeurs factices de la phase @observabilite : un faux GlitchTip local et un traceur Matomo intercepté
# (tests/e2e/observability.e2e.ts), jamais une instance réelle.
OBSERVABILITY_ENV := GLITCHTIP_DSN=http://clefactice@localhost:3231/1 MATOMO_URL=http://matomo.test/ MATOMO_SITE_ID=1
# Phase @identite : le site lit une fausse API (tests/e2e/fixtures/fake-api.js) qui sert l'identité avec logo ou couleurs
# et relaie le reste à l'API réelle, dont la base n'est jamais modifiée.
IDENTITY_ENV := API_URL=http://localhost:3232

qa: lint lint-js arch arch-selftest icons-check ui-check format-check typecheck test-unit ## QA rapide : lint, ordre des imports JS, architecture, sprite d'icônes, format, analyse statique, tests unitaires

qa-full: qa test-e2e test-a11y metrics ## QA complète : QA rapide + e2e + a11y + Lighthouse

lint: ## Composer, conteneur, Twig et YAML
	$(EXEC) sh -c "composer validate --strict && bin/console lint:container && bin/console lint:twig templates && bin/console lint:yaml config --parse-tags"

lint-js: ## Ordre des imports JavaScript (ESLint, simple-import-sort)
	$(RUN_NODE) yarn -s eslint .

lint-js-fix: ## Ordre des imports JavaScript, avec correction automatique
	$(RUN_NODE) yarn -s eslint . --fix

arch: arch-deptrac arch-wrappers ## Règles d'architecture : deptrac et bibliothèques tierces confinées à leur wrapper

arch-deptrac: ## Règles de dépendance entre modules et couches (deptrac.yaml)
	$(EXEC) vendor/bin/deptrac analyse --no-progress

arch-wrappers: ## Le sprite d'icônes, Embla et le paquet CSP ne sont utilisés que dans leur wrapper
	@status=0; \
	if grep -rn "icons\.svg" templates assets --exclude-dir=vendor --exclude-dir=boilerplates-ui | grep -v "^templates/shared/ui/_icon.html.twig:"; then \
		echo "Le sprite icons.svg ne s'utilise que dans templates/shared/ui/_icon.html.twig"; status=1; fi; \
	if grep -rnE "from '(embla-carousel|wheel-gestures)" assets --exclude-dir=vendor | grep -v "^assets/controllers/carousel_controller.js:"; then \
		echo "Embla ne s'importe que dans assets/controllers/carousel_controller.js"; status=1; fi; \
	if grep -rn "Kewinmarchand\\\\Csp" src | grep -v "^src/Shared/Infrastructure/Security/"; then \
		echo "Le paquet kewinmarchand/csp ne s'utilise que dans src/Shared/Infrastructure/Security/"; status=1; fi; \
	exit $$status

icons-check: ## Échoue si public/icons.svg ou src/Shared/UI/IconNames.php ne correspondent plus à icons.json
	$(NODE) tools/icons.js --check

ui-check: ## Échoue si assets/boilerplates-ui/ ne correspond plus à la version de boilerplates-ui installée
	$(NODE) tools/ui.js --check

arch-selftest: ## Écrit des violations volontaires, exige que make arch les détecte toutes, puis les retire
	@sh tools/arch-selftest.sh

format: ## PHP-CS-Fixer (écriture)
	$(EXEC) vendor/bin/php-cs-fixer fix

format-check: ## PHP-CS-Fixer (vérification)
	$(EXEC) vendor/bin/php-cs-fixer fix --dry-run --diff

typecheck: ## Analyse statique PHPStan (niveau max)
	$(EXEC) sh -c "bin/console cache:warmup --env=dev -q && vendor/bin/phpstan analyse --no-progress --memory-limit=1G"

test: test-unit test-e2e test-a11y ## Tous les tests

test-unit: ## Tests unitaires et fonctionnels (PHPUnit) et test du mode accessibilité (node:test)
	$(EXEC) sh -c "rm -rf var/cache/test && vendor/bin/phpunit"
	$(NODE) --test 'tests/js/**/*.test.js'

test-e2e: ## Tests end-to-end desktop et mobile : image de production, puis avec traceurs factices, puis avec une fausse API d'identité, puis serveur de dev (Playwright)
	$(UP_PROD)
	$(RUN_NODE) yarn playwright test tests/e2e --grep-invert "@dev|@observabilite|@identite"
	$(OBSERVABILITY_ENV) $(UP_PROD)
	$(RUN_NODE) yarn playwright test tests/e2e --grep @observabilite
	$(IDENTITY_ENV) $(UP_PROD)
	$(RUN_NODE) sh -c 'node tests/e2e/fixtures/fake-api.js & yarn playwright test tests/e2e --grep @identite'
	$(UP_DEV)
	$(RUN_NODE) yarn playwright test tests/e2e --grep @dev

test-a11y: ## Tests d'accessibilité WCAG 2.1 AA, deux modes, prod puis dev (axe-core + Playwright)
	$(UP_PROD)
	$(RUN_NODE) yarn playwright test tests/a11y --grep-invert @dev
	$(UP_DEV)
	$(RUN_NODE) yarn playwright test tests/a11y --grep @dev

metrics: docker-build ## Métriques Lighthouse CI sur l'image de production, en clair puis en sombre (perf ≥ 90, a11y et SEO = 100)
	HTTP_PORT=$(LHCI_PORT) SITE_HOST=127.0.0.1 $(COMPOSE_PROD) -p $(LHCI_PROJECT) up -d --wait
	$(RUN_NODE) sh -c 'export CHROME_PATH=$$(node -e "console.log(require(\"@playwright/test\").chromium.executablePath())") && yarn lhci autorun && yarn lhci autorun --collect.settings.chromeFlags="--headless=new --no-sandbox --blink-settings=preferredColorScheme=0" --upload.outputDir=.lighthouseci/reports-sombre'; \
		status=$$?; HTTP_PORT=$(LHCI_PORT) SITE_HOST=127.0.0.1 $(COMPOSE_PROD) -p $(LHCI_PROJECT) down; exit $$status

.PHONY: qa qa-full lint lint-js lint-js-fix arch arch-deptrac arch-wrappers arch-selftest icons-check ui-check format format-check typecheck test test-unit test-e2e test-a11y metrics
