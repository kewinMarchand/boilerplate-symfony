# boilerplate-symfony

Point de départ pour une application **Symfony 8.1** rendue côté serveur avec **Twig**, enrichie par **Stimulus** et **Turbo**, avec toute la chaîne qualité : lint, format, analyse statique, tests unitaires et fonctionnels, end-to-end, accessibilité et Lighthouse. Tout tourne dans Docker : aucun PHP ni Composer n'est nécessaire sur le poste.

Les pages de démonstration montrent chaque brique en situation :

| Page                       | Ce qu'elle montre                                                                                                   |
| -------------------------- | ------------------------------------------------------------------------------------------------------------------- |
| `/`                        | Hero pleine largeur (image LCP prioritaire), carrousel Embla en contrôleur Stimulus lu dans l'API, grille d'articles rendue serveur |
| `/catalogue/...`           | Catalogue à facettes : formulaire GET natif, état dans l'URL, Turbo Frame, panneau de filtres modal sur mobile        |
| `/taches`                  | Liste chargée à la demande dans un Turbo Frame, avec ses trois états : chargement, erreur avec relance, liste vide    |
| `/contact`                 | Symfony Form et Validator, erreurs liées aux champs, envoi par Turbo Stream, fonctionne sans JavaScript              |
| Pages légales              | Mentions légales, données personnelles, déclaration d'accessibilité lues dans l'API (texte administré dans son `/admin`), plan du site |
| `/charte-graphique`        | Charte graphique, en développement uniquement (404 en production)                                                    |

Un bouton « Accessibilité renforcée » dans l'en-tête agrandit le texte, espace le contenu (WCAG 1.4.12), renforce les contrastes et coupe les animations. Le choix est mémorisé dans le navigateur.

Le site parle français par défaut. Les langues activées dans l'identité du site de l'API (`locales`) sont servies sous leur préfixe (`/en/produit/swiss-cheese-plant`), avec un sélecteur de langue dans l'en-tête, des `hreflang` et un sitemap par langue ; un site à une seule langue ne change en rien.

Le site suit le thème clair ou sombre du système. Un bouton « Thème » dans l'en-tête permet de le forcer en clair ou en sombre, choix lui aussi mémorisé et appliqué dès le premier affichage. Le mode renforcé garde toujours la page en clair.

## Stack

| Brique                                      | Rôle                                                                    |
| ------------------------------------------- | ----------------------------------------------------------------------- |
| Symfony 8.1, PHP 8.5 (FrankenPHP)           | Routage, contrôleurs, formulaires, validation, pages d'erreur            |
| Twig 3                                      | Rendu serveur, templates rangés par domaine                              |
| AssetMapper + importmap                     | JavaScript et CSS sans bundler, versionnés à la compilation              |
| Stimulus (stimulus-bundle), Turbo (ux-turbo) | Comportements ciblés, navigation et formulaires sans rechargement        |
| Embla Carousel 8                            | Carrousel (glisser, swipe, molette), derrière un seul contrôleur          |
| Sprite SVG tiré de Lucide                   | Icônes, générées par `make icons`, derrière un seul partial Twig          |
| CSS maison sur les tokens de boilerplates-ui | Thème et mode renforcé par les variables `--bui-*`, copiées par `make ui` |
| PHP-CS-Fixer, PHPStan (niveau max)          | Style et analyse statique                                                |
| PHPUnit 12, node:test                       | Tests unitaires (domaine, fonctions pures) et fonctionnels (WebTestCase) |
| Playwright + axe-core                       | Tests end-to-end et accessibilité, desktop et mobile, deux modes         |
| Lighthouse CI                               | Performance, accessibilité, SEO, bonnes pratiques                         |

## Prérequis

- Docker (avec Compose), `make` et git. Rien d'autre : PHP, Composer, Node, Yarn et Chromium tournent en conteneur.

## Démarrage

```sh
git clone https://github.com/kewinMarchand/boilerplate-symfony.git
cd boilerplate-symfony
make install   # images de dev (PHP, Node et Chromium), dépendances installées dans les conteneurs
make up        # http://localhost:8095
```

Variables d'environnement (`.env`, à surcharger dans `.env.local` non versionné ou par de vraies variables) :

| Variable      | Rôle                                                                              |
| ------------- | --------------------------------------------------------------------------------- |
| `SITE_URL`    | URL publique : URL canoniques, Open Graph, JSON-LD, sitemap                        |
| `APP_SECRET`  | Secret Symfony, obligatoire en production                                          |
| `HTTP_PORT`   | Port publié par Docker (8095 par défaut)                                           |
| `GLITCHTIP_DSN` | Suivi des erreurs GlitchTip (DSN au format Sentry). Vide : rien n'est chargé ni envoyé |
| `GLITCHTIP_ENVIRONMENT` | Environnement des erreurs remontées, par défaut celui de Symfony |
| `MATOMO_URL`, `MATOMO_SITE_ID` | Mesure d'audience Matomo sans cookie. Les deux sont nécessaires, vides : rien n'est chargé ni envoyé |
| `API_URL` | API commune : `http://localhost:8090` (API Platform, par défaut) ou `http://localhost:8091` (Hono) |
| `API_SCENARIO` | Scénario de l'API fixé sur tous les appels (`bilingual` : site en français et en anglais, tests e2e). Vide hors des tests |
| `MAINTENANCE` | `1` sert la page statique de maintenance en 503 avec `Retry-After`, sans PHP : celle de la langue du préfixe de l'adresse (`/en/…`), sinon celle de `DEFAULT_LOCALE` |
| `DEFAULT_LOCALE` | Langue de la page de maintenance hors préfixe (`fr` par défaut) : celle par défaut du site, Caddy ne lit pas l'API |

## Démarrer un projet à partir de ce dépôt

### Créer le dépôt

La voie recommandée est le template GitHub : le nouveau dépôt part d'un historique neuf, sans lien avec celui-ci. Bouton « Use this template » sur GitHub, ou en ligne de commande :

```sh
gh repo create mon-projet --template kewinMarchand/boilerplate-symfony --private --clone
cd mon-projet
make install
make up
```

Pour suivre les évolutions du boilerplate ou y contribuer, passer plutôt par un fork, qui garde l'historique commun :

```sh
gh repo fork kewinMarchand/boilerplate-symfony --clone --fork-name mon-projet
```

GitHub n'autorise pas un fork dans le compte qui possède déjà le dépôt : depuis ce compte, passer par le template, ou forker vers une organisation avec `--org`.

### Récupérer les mises à jour

Avec un fork, l'historique est commun : `gh repo fork --clone` a déjà déclaré le dépôt d'origine comme remote `upstream`.

```sh
git fetch upstream
git merge upstream/main
```

Avec un template, l'historique n'est pas commun et une fusion n'a pas de sens. On reprend les commits utiles un par un :

```sh
git remote add upstream https://github.com/kewinMarchand/boilerplate-symfony.git
git fetch upstream
git log --oneline upstream/main
git cherry-pick <sha>
```

### Personnaliser

| Sujet | Fichiers |
| --- | --- |
| Nom du projet et du paquet | `composer.json` (`name`, `description`), `package.json` (`name`, `description`), `compose.yaml` (`name:` du projet Compose, image `…:prod`), `compose.override.yaml` (image `…:dev`), `Makefile.qa.mk` (`LHCI_PROJECT`), `LICENSE`, titres de `README.md` et `CLAUDE.md` |
| Config du site et identité | `config/packages/site.yaml` (nom, description, couleur du thème, navigation), `templates/seo/manifest.webmanifest.twig` (`short_name`), `translations/messages.{fr,en}.yaml` (libellés de l'interface, dont le texte alternatif de l'image de partage `seo.default_image_alt` et les boutons du hero), `templates/home/index.html.twig` (sur-titre du hero). Les diapositives du carrousel se modifient dans l'admin de l'API Platform (`GET /api/slides`, images comprises). Le nom du site est aussi vérifié par `tests/Functional/PagesTest.php` (`SITE`) et `tests/e2e/navigation.e2e.ts` |
| Ports | `Makefile` (`HTTP_PORT`, 8095), `compose.yaml` (port par défaut), `.env` (`SITE_URL`), `playwright.config.ts`, `Makefile.qa.mk` (`LHCI_PORT`, 3230) et `.lighthouserc.json` |
| Logo et favicons | `assets/images/logo-mark.svg`, `public/favicon.ico`, `public/favicon.svg`, `public/apple-touch-icon.png`, `public/icon-192.png`, `public/icon-512.png`, `public/og-image.jpg` |
| Pages légales | textes dans l'admin de l'API Platform (`GET /api/legal_pages/{slug}`), `config/packages/site.yaml` (`site.accessibility` une fois l'audit RGAA réalisé, pour l'état de conformité du lien du pied de page), section « Mesure d'audience » de `templates/legal/privacy.html.twig` |
| URL Lighthouse | `.lighthouserc.json` (une URL par page à mesurer, sur le port 3230) |
| CI | `.github/workflows/ci.yml` |

### Retirer les modules d'exemple

`Catalog`, `Blog` et `Task` sont des exemples. `Contact` et `Content` (carrousel et pages légales) restent, et `Site` et `Shared` restent en place. Pour les retirer sans casser deptrac ni `make arch-selftest` :

1. **Code** : supprimer `src/Catalog/`, `src/Blog/` et `src/Task/`, puis `assets/controllers/catalog_controller.js` et `assets/controllers/category_menu_controller.js`. Dans `src/Site/UI/HomeController.php`, ne garder que les diapositives.
2. **Templates** : supprimer `templates/catalog/`, `templates/tasks/`, `templates/legal/_category_tree.html.twig`, et dans `templates/shared/layout/`, `_category_menu.html.twig`, `_category_menu_column.html.twig` et `_mobile_menu_level.html.twig`. Puis retirer :
   - dans `templates/shared/layout/_header.html.twig`, l'appel `catalog_tree()` et la branche `catalog` de la navigation
   - dans `_mobile_menu.html.twig`, la branche `catalog` et les niveaux du catalogue
   - dans `templates/home/index.html.twig`, la section des articles et le bouton vers `/taches`
   - dans `templates/legal/sitemap.html.twig`, la section Catalogue
   - dans `templates/seo/sitemap.xml.twig`, la boucle `catalog_paths()` qui produit les entrées de catégories
   - dans `templates/bundles/TwigBundle/Exception/error404.html.twig`, le lien vers le catalogue
   - dans `templates/styleguide/index.html.twig`, les liens `path('catalog')` et le filtre `price`.
3. **Routes et services** : les routes disparaissent avec leurs contrôleurs. Retirer les entrées `catalog` et `tasks` de la navigation dans `config/packages/site.yaml` (menu, sitemap, plan du site, fil d'Ariane), et les alias des ports de `Task`, `Blog` et `Catalog` dans `config/services.yaml`.
4. **Architecture** : dans `deptrac.yaml`, supprimer les couches et les règles `Catalog*`, `Blog*` et `Task*`, et réduire la règle de `Site` à `[ContentDomain, ContactDomain, Shared, Framework]`. Dans `tools/arch-selftest.sh`, repointer les cinq violations PHP vers `Contact`, en gardant leurs noms de classe :

   | Violation | Fichier | Dépendance interdite |
   | --- | --- | --- |
   | `ArchSelftestDomainToInfrastructure` | `src/Contact/Domain/` | `App\Contact\Infrastructure\Http\HttpContactMessageSender` |
   | `ArchSelftestDomainToFramework` | `src/Contact/Domain/` | `Symfony\Component\HttpFoundation\Response`, inchangée |
   | `ArchSelftestModuleToModule` | `src/Contact/Domain/` | `App\Content\Domain\Slide` |
   | `ArchSelftestSharedToModule` | `src/Shared/UI/` | `App\Contact\Domain\ContactMessage` |
   | `ArchSelftestUiToInfrastructure` | `src/Contact/Infrastructure/` | `App\Contact\UI\ContactController` (l'UI de `Contact` peut appeler son infrastructure, on teste donc le sens interdit) |
5. **Tests** :
   - supprimer `tests/Unit/Catalog/`, `tests/Unit/Task/`, `tests/Functional/CatalogControllerTest.php` et `tests/Functional/TasksControllerTest.php`
   - dans `tests/Functional/PagesTest.php`, retirer les pages catalogue, sous-catégorie et tâches, et l'URL de catégorie du sitemap
   - dans `tests/Functional/HomeControllerTest.php`, retirer les tests du blog
   - côté Playwright, supprimer `tests/e2e/catalogue.e2e.ts`, `taches.e2e.ts` et `menu.e2e.ts`, et adapter `routes.ts`, `accueil.e2e.ts`, `breadcrumb.e2e.ts`, `pages.e2e.ts`, `layout.e2e.ts` et `tests/a11y/pages.a11y.ts`.
6. **Lighthouse** : retirer `/catalogue`, `/catalogue/plantes-interieur/feuillages` et `/taches` de `.lighthouserc.json`.
7. **Facultatif** : les styles du catalogue dans `assets/styles/app.css`.

Terminer par `make qa`, puis `make test-e2e test-a11y`. Déroulée sur une copie jetable du dépôt, cette procédure mène à un `make qa` vert : 0 violation deptrac, 7 violations volontaires sur 7 détectées, 40 tests PHPUnit.

## Commandes

`make help` liste toutes les commandes. Les principales :

| Commande                               | Effet                                                                                     |
| -------------------------------------- | ----------------------------------------------------------------------------------------- |
| `make up` / `make down`                | Serveur de développement, arrêt des conteneurs                                             |
| `make qa`                              | QA rapide : lint, architecture, format, analyse statique, tests unitaires et fonctionnels  |
| `make arch`                            | Règles d'architecture : deptrac (`deptrac.yaml`) et bibliothèques tierces confinées à leur wrapper |
| `make arch-selftest`                   | Écrit une violation volontaire de chaque règle, exige qu'elles soient toutes détectées, puis les retire |
| `make icons`                          | Génère le sprite `public/icons.svg` et la liste des noms depuis `icons.json`                |
| `make ui`                             | Copie boilerplates-ui (tokens, composants, comportements) dans `assets/boilerplates-ui/` |
| `make qa-full`                         | QA complète : QA rapide, e2e, accessibilité, Lighthouse                                    |
| `make format`                          | PHP-CS-Fixer en écriture                                                                   |
| `make typecheck`                       | PHPStan niveau max                                                                         |
| `make test-unit`                       | PHPUnit et tests du mode renforcé et du thème                                              |
| `make test-e2e`                        | Playwright sur l'image de production, puis servie en bilingue (`@bilingue`), puis les tests `@dev` sur le serveur de développement |
| `make e2e-vite`                        | Playwright desktop sur les e2e modifiés depuis `origin/main`, `FICHIERS="…"` ou `GREP="…"` ; image de production reconstruite seulement si les sources ont changé |
| `make test-a11y`                       | axe-core, WCAG 2.1 AA, chaque page en standard, sombre et renforcé                         |
| `make metrics`                         | Lighthouse CI sur l'image de production (port 3230) en clair puis en sombre : échoue sous 90 en performance ou sous 100 en accessibilité et SEO |
| `make docker-build` / `make docker-up` | Image de production, sur le port `HTTP_PORT`                                               |

Toutes les commandes tournent dans des conteneurs jetables : PHP par `docker compose run --rm php`, Node, Playwright et Lighthouse par `docker compose run --rm node` (image officielle Playwright à la version exacte de `@playwright/test`, profil Compose `dev`, réseau de l'hôte pour joindre les ports 8095 et 3230). Les `node_modules` vivent dans un volume nommé, et les conteneurs tournent avec l'UID et le GID de la machine.

## Architecture

Découpage par module métier d'abord, par couche ensuite, selon l'architecture commune aux boilerplates de l'auteur (décisions D1 à D12, règle R12 pour PHP).

```
src/
  Catalog/           module complet : catalogue de la jardinerie
    Domain/          modèle sans framework : Category, CategoryTree, CategoryPath, CatalogQuery, ProductFilters,
                     Product, Price (centimes), le port CatalogRepository
    Application/     ShowCatalogPage : résout la catégorie, interroge le dépôt, décide (introuvable, page
                     hors bornes, échec de chargement), sans framework
    Infrastructure/  Http/ : dépôt HTTP et CatalogTranslation, la traduction depuis et vers l'API
    UI/              contrôleur, état de recherche porté par l'URL (euros), fenêtre de pagination, extension Twig
  Blog/              forme réduite : Domain/ (Article, port, exception), Infrastructure/Http/
  Contact/           forme réduite : Domain/, Infrastructure/Http/ (envoi, violations 422), UI/ (contrôleur, formulaire)
  Content/           forme réduite : Domain/ (Slide, LegalPage, ports, exceptions), Infrastructure/Http/
  Task/              forme réduite : Domain/ (Task protège son invariant), Infrastructure/Http/, UI/
  Site/              pages sans métier propre : UI/ (accueil, légal, SEO, charte),
                     Legal/ (conformité d'accessibilité), Styleguide/ (contraste, tokens)
  Shared/            socle sans métier : UI/Seo/ (métadonnées, fil d'Ariane), UI/Twig/
templates/
  shared/            layout, en-tête, menus, pied de page, icône, image responsive, carrousel
  <page>/            vues de chaque page (catalog, contact, home, legal, seo, styleguide, tasks)
assets/
  controllers/       contrôleurs Stimulus
  styles/app.css     tokens et styles
tests/
  Unit/<Module>/     PHPUnit, rangés comme src/
  Functional/        WebTestCase
  js/                node:test
  e2e/ a11y/         Playwright
tools/
  arch-selftest.sh   violations volontaires de make arch-selftest
```

Les règles de dépendance sont vérifiées par deptrac (`make arch`) : `Domain` ne dépend que de lui-même, ni framework, ni autre couche, ni autre module. `Infrastructure` dépend du `Domain` de son module et des frameworks. `UI` du `Domain` de son module, de `Shared` et des frameworks, et de l'`Infrastructure` seulement dans les modules sans couche application. `Site` compose les modules, `Shared` n'en importe aucun. `make arch` vérifie aussi que le sprite `icons.svg` n'est utilisé que dans `templates/shared/ui/_icon.html.twig` et qu'Embla n'est importé que dans `assets/controllers/carousel_controller.js`. `make arch-selftest` écrit une violation de chaque règle et exige qu'elles soient toutes détectées.

Les données viennent de l'API commune (`API_URL`). Chaque module traduit ses réponses dans `src/<Module>/Infrastructure/Http/` : la forme est vérifiée, puis les objets du domaine sont construits (`Price` en centimes, arbre des catégories). Le domaine et l'application ignorent l'API.

Les conventions de code viennent du `CLAUDE.md` global de l'auteur. Le `CLAUDE.md` de ce dépôt note les choix propres au projet, les versions épinglées et les pièges connus.

## Recettes

### Ajouter une page

1. Créer le contrôleur dans `src/<Module>/UI/` (ou `src/Site/UI/` pour une page sans métier), avec sa route en attribut. Il ne fait qu'appeler le domaine et rendre une vue.
2. Créer la vue dans le dossier de templates de la page, `templates/<page>/index.html.twig` (comme `templates/contact/` ou `templates/legal/`), qui commence par `{% set seo = seo_page({title: '…'|trans, description: '…'|trans}) %}`. Chaque libellé est une clé de `translations/messages.fr.yaml` et `messages.en.yaml` (`{{ 'page.title'|trans }}`), mêmes clés dans les deux fichiers (`TranslationFilesTest`). La route est servie aussi sous le préfixe de chaque autre langue activée, et ses liens `path()` gardent la langue de la page.
3. Ajouter la route à `config/packages/site.yaml` (navigation, sitemap, plan du site, fil d'Ariane) et à `tests/e2e/routes.ts` (tests de réponse, SEO, débordement et accessibilité), puis à `.lighthouserc.json`.
4. Écrire `tests/e2e/<page>.e2e.ts` et un test fonctionnel dans `tests/Functional/`.

### Charger des données à la demande avec un Turbo Frame

Le pattern est dans le domaine Tasks :

- `src/Task/Domain/TaskRepository.php` est le port, `src/Task/Infrastructure/Http/HttpTaskRepository.php` l'adaptateur, qui lit `GET /api/tasks`.
- `TasksController::index()` rend la page avec un `<turbo-frame loading="lazy">` qui contient le squelette de chargement.
- `TasksController::list()` rend le contenu du frame : liste, état vide, ou erreur métier avec un bouton « Réessayer » (simple formulaire GET, sans JavaScript dédié).

### Ajouter un champ de formulaire

1. Ajouter la propriété et ses contraintes dans `src/Contact/UI/Form/ContactFormData.php`, avec pour message une clé de `translations/validators.fr.yaml` et `validators.en.yaml`.
2. L'ajouter dans `ContactType.php` (libellé : une clé de `messages.*.yaml`), puis dans `templates/contact/_form.html.twig` avec son `data-testid`. Le thème `form/bui_layout.html.twig` produit le balisage `bui-field` de boilerplates-ui, écrit « (obligatoire) » dans le libellé d'un champ requis, et Symfony relie l'erreur au champ par `aria-describedby` et pose `aria-invalid`.

### Activer une langue

Dans l'admin de l'API, fiche « Identité du site » : langues activées (la première est la langue par défaut). Le front les lit dans `GET /api/site-identity` à chaque requête, rien n'est à configurer. Une langue sans fichier `translations/messages.<langue>.yaml` prend les libellés français : pour une nouvelle langue, copier `messages.en.yaml` et `validators.en.yaml` et les traduire. Les appels à l'API passent `locale=<langue de la page>` (`Accept-Language` pour le contact) dès qu'il y a plus d'une langue.

### Ajouter une icône

Ajouter son nom Lucide à `icons.json`, lancer `make icons`, puis `{{ include('shared/ui/_icon.html.twig', {name: '<nom>'}) }}`. Le sprite `public/icons.svg` et `src/Shared/UI/IconNames.php` sont régénérés et versionnés, et `make icons-check` vérifie en QA qu'ils restent à jour.

### CSP et rapports de violation

Chaque réponse porte un en-tête `Content-Security-Policy-Report-Only` construit par le paquet [`kewinmarchand/csp`](https://github.com/kewinMarchand/csp), dans `src/Shared/Infrastructure/Security/SiteCsp.php` : rien n'est bloqué, le navigateur signale chaque ressource qu'une CSP stricte refuserait à `POST /api/csp-report`, qui écrit une ligne lisible sur la sortie standard du conteneur :

```
[csp-report] https://cdn.exemple.com a besoin d'une autorisation de type script (script-src) — bloqué sur /contact
```

- **Lire les logs** : `docker compose logs -f php`, en local comme sur l'image de production, ou la console de l'hébergeur. Au démarrage, `[boilerplate-symfony] serveur démarré (prod)` permet de retrouver le bon fichier.
- **Autoriser une origine** : l'ajouter à sa directive (le nom est entre parenthèses dans la ligne) dans `SiteCsp::extra()`. L'API (`API_URL`), Matomo et GlitchTip y sont déjà, quand leur variable est renseignée.
- **Pourquoi Report-Only et `'unsafe-inline'`** : on collecte d'abord ce que le site charge vraiment avant de bloquer quoi que ce soit ; un nonce par réponse empêcherait la mise en cache des pages.

## Tests

En développement, on ne lance que ce que le changement touche : `make qa` (porte de chaque commit), puis `make e2e-vite`. La suite complète (e2e desktop et mobile, phases `@identite`, `@observabilite` et `@dev`, accessibilité, Lighthouse) tourne en CI à chaque push ; `make test-e2e`, `make test-a11y`, `make metrics` et `make qa-full` restent disponibles à la main.

`make e2e-vite` (`tools/e2e-vite.sh`, lancé sur la machine) lance Playwright sur le seul projet desktop : par défaut les fichiers e2e modifiés par rapport à `origin/main` (`--only-changed`, qui suit aussi les fichiers importés par les tests), sinon `FICHIERS="tests/e2e/panier.e2e.ts …"` ou `GREP="motif"` (titre des tests, sur toute la suite e2e), avec `--pass-with-no-tests`. La phase principale exclut les tests étiquetés ; si la sélection contient des tests `@bilingue`, l'image est relancée avec `API_SCENARIO=bilingual` pour eux. L'image de production n'est reconstruite que si les sources ont changé : empreinte SHA-256 des fichiers suivis ou non ignorés par git, hors `tests/`, `*.md`, `playwright.config.ts` et `.github/`, comparée à `var/e2e-vite-empreinte`, écrite après la construction avec l'identifiant de l'image. Aucune variable n'est lue au build (`API_URL` et les autres sont lues par le conteneur), et un `make docker-build` lancé depuis d'autres sources change l'identifiant, donc force la reconstruction. Mesuré sur `panier.e2e.ts` (8 tests) : 15 s après une modification d'un template (reconstruction), 11 s à chaud ; 36 s avec `bilingue.e2e.ts` (58 tests de plus).

- **Unitaires et fonctionnels** : `tests/Unit` (domaine et fonctions pures, sans conteneur), `tests/Functional` (WebTestCase, avec des dépôts remplacés pour les états vide et erreur), `tests/js` (mode renforcé et thème).
- **End-to-end** : Playwright sur l'image de production servie sur le port 8095. Les éléments sont ciblés par `data-testid`, préfixé par le domaine (`catalog-product`, `contact-submit`). Chaque rendu conditionnel est testé présent et absent. Les tests marqués `@bilingue` (`tests/e2e/bilingue.e2e.ts` : routes préfixées, libellés anglais, sélecteur, `hreflang`, sitemap, 404 des préfixes non servis, axe des pages anglaises, aucun libellé français sur les pages anglaises : libellés tirés de `messages.fr.yaml` et des défauts de boilerplates-ui, cherchés en mots entiers dans le texte et les attributs annoncés) tournent sur l'image servie avec `API_SCENARIO=bilingual`, les tests marqués `@dev` (page 500, charte graphique) ensuite sur le serveur de développement.
- **Accessibilité** : `tests/a11y/` passe axe-core sur chaque route, en mode standard, sombre et renforcé, desktop et mobile, menus ouverts compris. Axe ne couvre qu'une partie du RGAA, un audit manuel reste nécessaire.
- **Captures** : `tests/e2e/layout.e2e.ts` enregistre l'accueil et le catalogue dans les trois modes, à 375, 768 et 1280 px, dans `screenshots/`.

## Intégration continue

`.github/workflows/ci.yml` n'appelle que des cibles `make` (`make install`, `make qa`, `make test-e2e test-a11y`, `make metrics`), donc les mêmes conteneurs que le poste de dev, à chaque push sur `main` et sur chaque pull request. Le job `qa` n'a besoin d'aucune API (réponses capturées dans `tests/Fixtures/api/`). Le job e2e clone `boilerplate-hono` dans `.api`, le lance par `make -C .api install db-reset` et pointe `API_URL` sur `http://localhost:8091`. En cas d'échec, le rapport Playwright est joint au run.

## Licence

MIT
