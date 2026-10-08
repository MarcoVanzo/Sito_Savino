#!/usr/bin/env bash
# Lancia i test che nominano le classi PHP cambiate rispetto alla base
# (predefinita origin/main), più i test modificati. Va lanciato prima del push:
# la CI gira l'intera suite in ~14 minuti, questo prende in pochi secondi i test
# che leggono ancora la vecchia forma di una costante o di una chiave.
#
#   scripts/test-toccati.sh [base]
#
# Usa il binario diretto di PHPUnit: `vendor/bin/phpunit` parallelizza da solo e
# corrompe il DB di test condiviso.
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"

base="${1:-origin/main}"
cambiati=$(
    {
        git diff --name-only "$(git merge-base "$base" HEAD)"
        git ls-files --others --exclude-standard
    } | grep '\.php$' | sort -u || true
)

test=""
for f in $cambiati; do
    [ -f "$f" ] || continue
    case "$f" in
        tests/*Test.php) test="$test $f" ;;
        app/*)
            classe=$(basename "$f" .php)
            test="$test $(grep -rlw --include='*Test.php' "$classe" tests || true)"
            ;;
    esac
done

test=$(echo "$test" | tr ' ' '\n' | grep . | sort -u || true)
if [ -z "$test" ]; then
    echo "Nessun test nomina le classi cambiate rispetto a $base."
    exit 0
fi

echo "Test toccati ($(echo "$test" | wc -l | tr -d ' ')):"
echo "$test" | sed 's/^/  /'
# Copia di phpunit.xml nella root (i percorsi restano relativi al repo) con le
# sole suite sostituite dai file trovati.
lista=.phpunit-toccati.xml
trap 'rm -f "$lista"' EXIT
file=$(echo "$test" | sed 's|.*|            <file>&</file>|')
python3 - "$lista" "$file" <<'PY'
import re, sys
s = open('phpunit.xml').read()
blocco = '<testsuites>\n        <testsuite name="toccati">\n' + sys.argv[2] + '\n        </testsuite>\n    </testsuites>'
open(sys.argv[1], 'w').write(re.sub(r'<testsuites>.*?</testsuites>', lambda m: blocco, s, flags=re.S))
PY
php vendor/phpunit/phpunit/phpunit -c "$lista"
