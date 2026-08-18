#!/usr/bin/env bash
#
# Regenerate translation files for WP Performance.
#
#   1. Extract languages/wpp.pot from PHP + JS(X) source.
#   2. For each languages/wpp-<locale>.po: compile the .mo and build the merged
#      script-translation JSON the admin bundle loads.
#
# Requires WP-CLI and GNU gettext (msgfmt). Override the WP-CLI binary with
# WP_CLI, e.g. on a Local site:
#   WP_CLI="php /Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/wp-cli.phar" bin/make-translations.sh
set -euo pipefail

cd "$(dirname "$0")/.."

WP="${WP_CLI:-wp}"
DOMAIN="wpp"
HANDLE="wpp-admin"
EXCLUDE="build,node_modules,vendor,vendor-prefixed,languages,.idea,bin"

echo "==> make-pot"
$WP i18n make-pot . "languages/${DOMAIN}.pot" --domain="${DOMAIN}" --exclude="${EXCLUDE}"

shopt -s nullglob
for po in languages/${DOMAIN}-*.po; do
    locale="$(basename "$po" .po)"
    locale="${locale#${DOMAIN}-}"

    echo "==> ${locale}: compile .mo"
    msgfmt -o "languages/${DOMAIN}-${locale}.mo" "$po"

    echo "==> ${locale}: script translations (.json)"
    rm -f languages/${DOMAIN}-${locale}-*.json
    $WP i18n make-json "$po" --no-purge --extensions=jsx >/dev/null
    php bin/merge-script-translations.php "$locale" "$DOMAIN" "$HANDLE"
done

echo "Done."
