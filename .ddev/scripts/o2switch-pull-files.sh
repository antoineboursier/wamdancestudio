#!/usr/bin/env bash
# Miroir incrémental des médias par FTPS.
# Les plugins ne suivent que si sync_plugins=true, à une exception près :
# wam-custom-plugin est toujours rapatrié (voir plus bas).
set -eu -o pipefail
# shellcheck source=o2switch-env.sh
. /var/www/html/.ddev/scripts/o2switch-env.sh

command -v lftp >/dev/null 2>&1 || { echo "❌ lftp absent. Lance : ddev restart" >&2; exit 1; }

# Options communes. Volontairement PAS de --only-newer : si lftp échoue à poser la date
# de modification (fréquent sur le système de fichiers synchronisé par mutagen sous
# Windows), un fichier tronqué passerait ensuite pour « à jour » et ne serait jamais
# retéléchargé. La comparaison taille + date par défaut est plus sûre.
MIRROR_COMMON="--continue --delete --no-perms"

# Exclusions réservées aux MÉDIAS, ancrées à la racine du miroir (^) pour ne pas
# écarter des dossiers légitimes situés plus bas dans l'arborescence.
# ⚠ Ne jamais appliquer d'exclusion large aux plugins : un simple « cache/ » non ancré
# écarte des dépendances comme mailpoet/vendor-prefixed/psr/cache/ et casse le site.
MIRROR_MEDIA_EXCLUDES="--exclude-glob *wpvivid* --exclude ^cache/ --exclude ^litespeed/ --exclude ^et-cache/"

run_mirror() {
	local remote="$1" local_dir="$2" parallel="$3" excludes="${4:-}"
	lftp -u "${FTP_USER},${FTP_PASS}" "ftp://${FTP_HOST}" <<LFTP
${LFTP_SETTINGS}
mirror ${MIRROR_COMMON} --parallel=${parallel} ${excludes} "${remote}" "${local_dir}"
bye
LFTP
}

mirror_dir() {
	local remote="$1" local_dir="$2" label="$3" excludes="${4:-}"
	echo "⏬ Synchronisation ${label} (FTPS, incrémental)..."
	mkdir -p "${local_dir}"
	if ! run_mirror "${remote}" "${local_dir}" 4 "${excludes}"; then
		# Les erreurs de transfert en parallèle sont fréquentes sur Pure-FTPd
		# (limite de connexions par IP) : on repasse une fois en séquentiel.
		echo "⚠  Erreurs de transfert — seconde passe séquentielle..."
		run_mirror "${remote}" "${local_dir}" 1 "${excludes}"
	fi
}

mirror_dir "${FTP_BASE}/wp-content/uploads" /var/www/html/wp-content/uploads \
	"des médias" "${MIRROR_MEDIA_EXCLUDES}"

if [ "${sync_plugins:-false}" = "true" ]; then
	mirror_dir "${FTP_BASE}/wp-content/plugins" /var/www/html/wp-content/plugins "des plugins"
else
	# wam-custom-plugin porte la configuration du site maintenue par Ophélie :
	# il est toujours synchronisé, même sans l'option sync_plugins.
	mirror_dir "${FTP_BASE}/wp-content/plugins/wam-custom-plugin" \
		/var/www/html/wp-content/plugins/wam-custom-plugin "du WAM Custom Plugin"
fi

echo "✅ Fichiers synchronisés"
