#!/usr/bin/env bash
# Crea un worktree in .claude/worktrees/<nome> su un branch nuovo da origin/main,
# pronto per test, Pint e PHPStan: .env copiato, vendor installato davvero (un
# symlink sposta il base_path sul repo principale) e build di Vite (senza
# manifest i test del pannello vanno in 500).
#
#   scripts/prepara-worktree.sh <branch> [nome-cartella]
set -euo pipefail
principale="$(git rev-parse --show-toplevel)"
cd "$principale"

branch="${1:?uso: scripts/prepara-worktree.sh <branch> [nome-cartella]}"
nome="${2:-${branch##*/}}"
dir=".claude/worktrees/$nome"

git fetch -q origin
git worktree add -q "$dir" -b "$branch" origin/main
cd "$dir"
cp "$principale/.env" .env
composer install --no-interaction -q
npm ci --silent
npm run build --silent >/dev/null
echo "Pronto: $principale/$dir"
