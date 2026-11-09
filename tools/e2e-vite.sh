#!/bin/sh
# make e2e-vite : Playwright, projet desktop seul, sur les fichiers e2e ciblés, contre l'image de production reconstruite
# seulement si les sources ont changé depuis sa construction. Lancé par make sur la machine (Compose, APP_SECRET, HOST_UID).
#   FICHIERS="tests/e2e/a.e2e.ts …" : ces fichiers ; GREP="motif" : les tests dont le titre correspond ;
#   sans l'un ni l'autre : fichiers modifiés par rapport à origin/main (--only-changed).
# Phase principale, puis phase @bilingue (API_SCENARIO=bilingual) si la sélection en contient. Les phases @identite,
# @observabilite et @dev restent à make test-e2e et à la CI.
set -eu

EMPREINTE=var/e2e-vite-empreinte
IMAGE=boilerplate-symfony:prod
PROD="docker compose -f compose.yaml"
TAGS='@dev|@observabilite|@identite|@bilingue'

# Empreinte de ce qui entre dans l'image : fichiers suivis ou non ignorés, hors tests, docs et configuration de la CI.
# Aucune variable n'est lue au build : API_URL, API_SCENARIO et les autres sont lues par le conteneur à l'exécution.
empreinte() {
  git ls-files -z -co --exclude-standard -- . ':!:tests' ':!:*.md' ':!:playwright.config.ts' ':!:.github' \
    | xargs -0 sha256sum 2>/dev/null | sha256sum | cut -d ' ' -f 1
}

image_id() {
  docker image inspect --format '{{.Id}}' "$IMAGE" 2>/dev/null || true
}

SOURCES=$(empreinte)
# L'identifiant de l'image lie l'empreinte à cette image-ci : un make docker-build lancé depuis d'autres sources en change.
if [ -f "$EMPREINTE" ] && [ "$(cat "$EMPREINTE")" = "$SOURCES $(image_id)" ]; then
  echo "e2e-vite : image de production à jour, réutilisée"
  BUILD=
else
  echo "e2e-vite : sources modifiées depuis la dernière image, reconstruction"
  rm -f "$EMPREINTE"
  BUILD=--build
fi

# Sélection commune aux deux phases
set -- --project=desktop --pass-with-no-tests
if [ -n "${FICHIERS:-}" ]; then
  # shellcheck disable=SC2086 # une liste de fichiers séparés par des espaces
  set -- "$@" $FICHIERS
elif [ -n "${GREP:-}" ]; then
  set -- "$@" tests/e2e
else
  set -- "$@" tests/e2e --only-changed=origin/main
fi

playwright() {
  docker compose run --rm -T node yarn -s playwright test "$@"
}

# Titres retenus : ceux de GREP s'il est donné, avec ou sans l'étiquette de la phase
avec_grep() {
  if [ -n "${GREP:-}" ]; then printf '(?=.*%s)(?=.*%s)' "$1" "$GREP"; else printf '%s' "$1"; fi
}

$PROD up -d --wait $BUILD
if [ -n "$BUILD" ]; then echo "$SOURCES $(image_id)" > "$EMPREINTE"; fi

if [ -n "${GREP:-}" ]; then
  playwright "$@" --grep "$GREP" --grep-invert "$TAGS"
else
  playwright "$@" --grep-invert "$TAGS"
fi

BILINGUE=$(avec_grep @bilingue)
if playwright "$@" --grep "$BILINGUE" --list | grep -q '^Total: [1-9]'; then
  API_SCENARIO=bilingual $PROD up -d --wait
  playwright "$@" --grep "$BILINGUE"
fi
