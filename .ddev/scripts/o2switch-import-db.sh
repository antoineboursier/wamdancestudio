#!/usr/bin/env bash
# Import du dump de production dans la base locale.
#
# Pourquoi ne pas laisser DDEV importer ?
# Sous Windows, l'import automatique de `ddev pull` déduit le nom de la base du chemin
# du fichier téléchargé et produit un nom invalide (« C:\wamp64\www\WAM V1\ »), ce qui
# fait échouer l'import. On pilote donc l'opération explicitement.
set -eu -o pipefail

DUMP=/var/www/html/.ddev/.downloads/db.sql.gz
[ -s "${DUMP}" ] || { echo "❌ Dump introuvable : ${DUMP}" >&2; exit 1; }

echo "🗄  Import dans la base locale..."
mysql -h db -u root -proot -e \
	"DROP DATABASE IF EXISTS db; CREATE DATABASE db CHARACTER SET utf8mb4; GRANT ALL ON db.* TO 'db'@'%';"

# La première ligne des dumps MariaDB 11 (« enable the sandbox mode ») n'est pas
# comprise par tous les clients : on la retire.
gzip -dc "${DUMP}" \
	| sed '1{/enable the sandbox mode/d}' \
	| mysql -h db -u db -pdb db

echo "✅ Base importée ($(mysql -h db -u db -pdb -sN -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='db'") tables)"
