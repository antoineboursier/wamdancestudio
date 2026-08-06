#!/usr/bin/env bash
#
# Contrôle du fichier llms.txt — structure, accès des crawlers IA, fraîcheur.
#
# Il n'existe pas d'outil officiel équivalent au Rich Results Test pour
# llms.txt : la spec llmstxt.org n'impose qu'un titre H1. Ce script vérifie
# donc ce qui est réellement vérifiable, et surtout ce qui casse en pratique
# (fichier inaccessible, crawler bloqué, HTML qui fuit, contenu figé par le
# cache LiteSpeed).
#
# Usage :
#   bash .ddev/scripts/llms-txt-check.sh                      # prod
#   bash .ddev/scripts/llms-txt-check.sh http://wam-v1.ddev.site
#
set -uo pipefail

BASE="${1:-https://www.wamdancestudio.fr}"
URL="${BASE%/}/llms.txt"
TMP="$(mktemp)"
trap 'rm -f "$TMP"' EXIT

ok=0
ko=0
pass() { printf '  \033[32m✓\033[0m %s\n' "$1"; ok=$((ok + 1)); }
fail() { printf '  \033[31m✗\033[0m %s\n' "$1"; ko=$((ko + 1)); }
info() { printf '    %s\n' "$1"; }

echo
echo "Contrôle de $URL"
echo

# --- 1. Accès -----------------------------------------------------------
echo "1. Accès et en-têtes"

code=$(curl -sS -o "$TMP" -w '%{http_code}' -L --max-time 30 "$URL" 2>/dev/null)
if [ "$code" = "200" ]; then
    pass "HTTP 200"
else
    fail "HTTP $code — le fichier n'est pas servi"
    echo
    echo "Résultat : $ok OK / $ko problème(s)"
    exit 1
fi

# Un 301 fonctionne, mais la spec attend l'URL canonique en accès direct et
# certains fetchers stricts ne suivent pas les redirections.
direct=$(curl -sS -o /dev/null -w '%{http_code}' --max-time 30 "$URL" 2>/dev/null)
if [ "$direct" = "200" ]; then
    pass "Pas de redirection sur l'URL canonique"
else
    fail "L'URL canonique renvoie $direct (redirection) — flush des permaliens requis"
fi

ctype=$(curl -sS -o /dev/null -w '%{content_type}' -L --max-time 30 "$URL" 2>/dev/null)
case "$ctype" in
    text/plain*|text/markdown*) pass "Content-Type : $ctype" ;;
    *) fail "Content-Type : $ctype (attendu text/plain ou text/markdown)" ;;
esac

# --- 2. Structure (spec llmstxt.org) ------------------------------------
echo
echo "2. Structure"

if [ "$(grep -c '^# ' "$TMP")" = "1" ]; then
    pass "Titre H1 unique — $(grep -m1 '^# ' "$TMP")"
else
    fail "Le H1 est absent ou en double (seul élément obligatoire de la spec)"
fi

grep -q '^>' "$TMP" && pass "Résumé en blockquote" || info "Pas de blockquote (facultatif)"

sections=$(grep -c '^## ' "$TMP")
[ "$sections" -gt 0 ] && pass "$sections sections H2" || fail "Aucune section H2"
grep '^## ' "$TMP" | sed 's/^## /      · /'

liens=$(grep -cE '^- \[[^]]+\]\(https?://[^)]+\)' "$TMP")
[ "$liens" -gt 0 ] && pass "$liens liens markdown bien formés" || fail "Aucun lien exploitable"

# --- 3. Qualité du texte ------------------------------------------------
echo
echo "3. Qualité du texte servi aux modèles"

ent=$(grep -oE '&[a-z]+;|&#[0-9]+;' "$TMP" | wc -l | tr -d ' ')
[ "$ent" = "0" ] && pass "Aucune entité HTML" \
    || { fail "$ent entité(s) HTML — le texte n'est pas décodé"; grep -oE '&[a-z]+;|&#[0-9]+;' "$TMP" | sort -u | head -5 | sed 's/^/      /'; }

bal=$(grep -cE '<(div|span|p|a|img|br) ' "$TMP")
[ "$bal" = "0" ] && pass "Aucune balise HTML" || fail "$bal ligne(s) contiennent du HTML"

# --- 4. Fraîcheur -------------------------------------------------------
echo
echo "4. Fraîcheur"

gen=$(grep -oE 'Generated: [0-9T:+-]+' "$TMP" | head -1 | cut -d' ' -f2)
if [ -n "$gen" ]; then
    jours=$(( ( $(date +%s) - $(date -d "$gen" +%s 2>/dev/null || echo 0) ) / 86400 ))
    if [ "$jours" -le 1 ]; then
        pass "Généré le ${gen%T*} (aujourd'hui ou hier)"
    else
        fail "Généré il y a $jours jours — cache LiteSpeed à purger ?"
    fi
else
    info "Pas d'horodatage de génération"
fi

# --- 5. Crawlers IA -----------------------------------------------------
# Le fichier ne sert à rien si les robots ne peuvent pas l'atteindre.
echo
echo "5. Accès des crawlers IA"

for ua in \
    "GPTBot|GPTBot/1.2 (+https://openai.com/gptbot)" \
    "ClaudeBot|ClaudeBot/1.0 (+claudebot@anthropic.com)" \
    "PerplexityBot|PerplexityBot/1.0" \
    "OAI-SearchBot|OAI-SearchBot/1.0" \
    "Google-Extended|Google-Extended"; do
    nom="${ua%%|*}"
    agent="${ua#*|}"
    c=$(curl -sS -A "$agent" -o /dev/null -w '%{http_code}' -L --max-time 25 "$URL" 2>/dev/null)
    [ "$c" = "200" ] && pass "$nom : $c" || fail "$nom : $c — accès bloqué"
done

echo
echo "Résultat : $ok OK / $ko problème(s)"
[ "$ko" -eq 0 ] || exit 1
