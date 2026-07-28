#!/usr/bin/env bash
# Récupère la base de production dans .ddev/.downloads/db.sql.gz
#   1. dump direct via MySQL distant (rapide, toujours à jour)
#   2. repli : téléchargement par FTPS d'un dump déposé sur le serveur (DB_DUMP_REMOTE)
set -eu -o pipefail
# shellcheck source=o2switch-env.sh
. /var/www/html/.ddev/scripts/o2switch-env.sh

mkdir -p "${DOWNLOADS}"
TARGET="${DOWNLOADS}/db.sql.gz"
rm -f "${TARGET}"

dump_direct() {
	bash /var/www/html/.ddev/scripts/o2switch-db-creds.sh
	# shellcheck source=/dev/null
	. "${CREDS_FILE}"
	echo "⏬ Dump direct de ${PROD_DB_NAME} via MySQL distant (${DB_HOST})..."
	mysqldump \
		--single-transaction --quick --no-tablespaces \
		--default-character-set=utf8mb4 --skip-lock-tables \
		-h "${DB_HOST}" -u "${PROD_DB_USER}" -p"${PROD_DB_PASS}" "${PROD_DB_NAME}" \
		| gzip -c > "${TARGET}"
}

dump_from_ftp() {
	[ -n "${DB_DUMP_REMOTE}" ] || return 1
	echo "⏬ Téléchargement du dump ${DB_DUMP_REMOTE} par FTPS..."
	curl "${CURL_FTPS[@]}" "ftp://${FTP_HOST}${DB_DUMP_REMOTE}" -o "${TARGET}"
	# Le dump déposé peut ne pas être compressé : on normalise en .sql.gz
	if ! gzip -t "${TARGET}" 2>/dev/null; then
		mv "${TARGET}" "${TARGET%.gz}"
		gzip -f "${TARGET%.gz}"
	fi
}

if ! dump_direct; then
	rm -f "${TARGET}"
	echo "⚠  Dump direct impossible — tentative de repli FTPS."
	if ! dump_from_ftp; then
		cat >&2 <<MSG
❌ Impossible de récupérer la base de production.

Deux options, au choix :
  • Autoriser MySQL distant : cPanel > Bases de données > MySQL distant,
    ajouter l'IP publique du poste (à relever sur https://api.ipify.org).
  • Déposer un dump sur le serveur puis renseigner DB_DUMP_REMOTE dans
    .ddev/o2switch.env — par exemple, depuis le Terminal cPanel :
        mkdir -p ~/dumps && cd ~/public_html && wp db export - | gzip -c > ~/dumps/wam-latest.sql.gz
    puis DB_DUMP_REMOTE=/dumps/wam-latest.sql.gz
MSG
		exit 1
	fi
fi

[ -s "${TARGET}" ] || { echo "❌ Dump vide." >&2; exit 1; }
gzip -t "${TARGET}" || { echo "❌ Dump corrompu." >&2; exit 1; }
echo "✅ Base récupérée ($(du -h "${TARGET}" | cut -f1))"
