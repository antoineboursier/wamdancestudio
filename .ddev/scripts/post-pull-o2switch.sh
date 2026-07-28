#!/usr/bin/env bash
# Adapte la copie de production à l'environnement local.
# Exécuté automatiquement dans le conteneur web après `ddev pull o2switch`.
set -eu -o pipefail

PROD_DOMAIN="${prod_domain:-wamdancestudio.fr}"
LOCAL_URL="${DDEV_PRIMARY_URL:-https://wam-v1.ddev.site}"
LOCAL_DOMAIN="${LOCAL_URL#*://}"

cd /var/www/html

if ! wp core is-installed --skip-plugins --skip-themes >/dev/null 2>&1; then
	echo "⚠  WordPress non installé / base absente — post-pull ignoré."
	exit 0
fi

echo "🔁 Réécriture des URLs ${PROD_DOMAIN} → ${LOCAL_DOMAIN}"
# Les adresses e-mail (@wamdancestudio.fr) ne sont volontairement pas touchées :
# seules les URLs préfixées d'un schéma ou protocol-relative sont remplacées.
for prefix in "https://www." "http://www." "https://" "http://"; do
	wp search-replace "${prefix}${PROD_DOMAIN}" "${LOCAL_URL}" \
		--all-tables --precise --skip-columns=guid --report-changed-only \
		--skip-plugins --skip-themes || true
done
wp search-replace "//${PROD_DOMAIN}" "//${LOCAL_DOMAIN}" \
	--all-tables --precise --skip-columns=guid --report-changed-only \
	--skip-plugins --skip-themes || true

wp option update home "${LOCAL_URL}" --skip-plugins --skip-themes
wp option update siteurl "${LOCAL_URL}" --skip-plugins --skip-themes

# Plugins de cache/optimisation inutiles (et nuisibles) sur nginx local.
for plugin in litespeed-cache wp-rocket w3-total-cache wp-super-cache autoptimize really-simple-ssl; do
	if wp plugin is-active "${plugin}" --skip-plugins --skip-themes >/dev/null 2>&1; then
		wp plugin deactivate "${plugin}" --skip-plugins --skip-themes
	fi
done

# Garde-fou local (mails interceptés par Mailpit, bandeau d'avertissement).
mkdir -p wp-content/mu-plugins
cp .ddev/scripts/wam-local-guard.php wp-content/mu-plugins/000-wam-local-guard.php

# Le thème est celui du dépôt, jamais celui de la prod.
wp theme activate wamV1 --skip-plugins --skip-themes >/dev/null 2>&1 || true

wp rewrite flush --hard --skip-plugins --skip-themes >/dev/null 2>&1 || true
wp cache flush --skip-plugins --skip-themes >/dev/null 2>&1 || true

# Purge des transients du thème (grille profs, planning, footer) : voir inc/performance.php
wp transient delete --all --skip-plugins --skip-themes >/dev/null 2>&1 || true

echo "✅ Copie locale prête : ${LOCAL_URL}/wp-admin"
