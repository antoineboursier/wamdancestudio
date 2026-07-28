#!/usr/bin/env bash
# Récupère les identifiants MySQL de production depuis le wp-config.php distant (FTPS)
# et les écrit dans un fichier temporaire du conteneur, en dehors de .ddev/.downloads
# (que DDEV importe intégralement comme bases de données).
set -eu -o pipefail
# shellcheck source=o2switch-env.sh
. /var/www/html/.ddev/scripts/o2switch-env.sh

trap 'rm -f "${WPCONFIG_TMP}"' EXIT

umask 077
curl "${CURL_FTPS[@]}" "ftp://${FTP_HOST}${FTP_BASE}/wp-config.php" -o "${WPCONFIG_TMP}"
php /var/www/html/.ddev/scripts/o2switch-parse-wpconfig.php "${WPCONFIG_TMP}" > "${CREDS_FILE}"
chmod 600 "${CREDS_FILE}"
