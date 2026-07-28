#!/usr/bin/env bash
# Vérifie que les canaux de synchronisation (FTPS, MySQL distant) sont opérationnels.
set -eu -o pipefail
# shellcheck source=o2switch-env.sh
. /var/www/html/.ddev/scripts/o2switch-env.sh

if ! command -v lftp >/dev/null 2>&1; then
	echo "❌ lftp absent du conteneur web. Lance : ddev restart" >&2
	exit 1
fi

echo "🔌 Test FTPS sur ${FTP_HOST}..."
if ! curl "${CURL_FTPS[@]}" --head "ftp://${FTP_HOST}${FTP_BASE}/wp-config.php" >/dev/null; then
	echo "❌ Connexion FTPS impossible (hôte, identifiants ou chemin ${FTP_BASE} incorrects)." >&2
	exit 1
fi
echo "✅ FTPS OK — ${FTP_BASE}/wp-config.php accessible"

echo "🔌 Test MySQL distant sur ${DB_HOST}..."
if bash /var/www/html/.ddev/scripts/o2switch-db-creds.sh >/dev/null 2>&1; then
	# shellcheck source=/dev/null
	. "${CREDS_FILE}"
	if mysql -h "${DB_HOST}" -u "${PROD_DB_USER}" -p"${PROD_DB_PASS}" \
		-e "SELECT 1" "${PROD_DB_NAME}" >/dev/null 2>&1; then
		echo "✅ MySQL distant OK — dump direct disponible"
	else
		echo "⚠  MySQL distant injoignable depuis ce poste."
		echo "   → cPanel > Bases de données > MySQL distant : autoriser l'IP publique du poste."
		if [ -n "${DB_DUMP_REMOTE}" ]; then
			echo "   → Repli actif : téléchargement du dump ${DB_DUMP_REMOTE} par FTPS."
		else
			echo "   → Aucun repli configuré (DB_DUMP_REMOTE vide) : le pull de la base échouera."
		fi
	fi
else
	echo "⚠  Identifiants de base non lisibles dans le wp-config.php distant."
fi
