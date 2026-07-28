#!/usr/bin/env bash
# Chargement et validation des paramètres de connexion o2switch.
# Fichier destiné à être sourcé par les scripts o2switch-*.sh.
# shellcheck disable=SC2034

ENV_FILE=/var/www/html/.ddev/o2switch.env

if [ ! -f "$ENV_FILE" ]; then
	cat >&2 <<'MSG'
❌ Fichier .ddev/o2switch.env introuvable.

Crée-le à partir de .ddev/o2switch.env.sample et renseigne le mot de passe FTP :
    cp .ddev/o2switch.env.sample .ddev/o2switch.env
Ce fichier n'est jamais versionné (exclu par .gitignore).
MSG
	exit 1
fi

set -a
# shellcheck source=/dev/null
. "$ENV_FILE"
set +a

: "${FTP_HOST:?FTP_HOST manquant dans .ddev/o2switch.env}"
: "${FTP_USER:?FTP_USER manquant dans .ddev/o2switch.env}"
: "${FTP_PASS:?FTP_PASS manquant dans .ddev/o2switch.env}"
# `${VAR-défaut}` et non `${VAR:-défaut}` : une valeur volontairement vide doit être
# respectée (cas d'un compte FTP dont la racine est déjà celle du site).
FTP_BASE="${FTP_BASE-/public_html}"
DB_HOST="${DB_HOST:-$FTP_HOST}"
DB_DUMP_REMOTE="${DB_DUMP_REMOTE:-}"

# Le certificat TLS du serveur FTP porte le nom du serveur o2switch, pas celui du
# domaine : la vérification stricte échouerait. Le chiffrement reste actif.
CURL_FTPS=(--silent --show-error --fail --ssl-reqd --insecure --connect-timeout 20
	--user "${FTP_USER}:${FTP_PASS}")

LFTP_SETTINGS="set ftp:ssl-force true;
	set ftp:ssl-protect-data true;
	set ssl:verify-certificate no;
	set net:timeout 20;
	set net:max-retries 3;
	set cmd:fail-exit true;"

# DDEV importe TOUT fichier présent dans .ddev/.downloads comme une base de données :
# les fichiers de travail doivent donc rester en dehors de ce dossier.
DOWNLOADS=/var/www/html/.ddev/.downloads
CREDS_FILE=/tmp/.o2switch-dbcreds
WPCONFIG_TMP=/tmp/.o2switch-wp-config.php
