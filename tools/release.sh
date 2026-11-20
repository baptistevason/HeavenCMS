#!/usr/bin/env bash
# Publie une version de Heaven prête à déployer : met à jour la branche « release », qui contient le contenu du dossier
# heaven/ à la racine (ce que chaque hébergement doit servir). Chaque publication ajoute un commit à la suite du précédent :
# les sites déjà installés se mettent à jour par simple « git pull » (jamais de réécriture d'historique).
#
#   tools/release.sh "Texte de la version"          prépare la branche release en local
#   PUSH=1 tools/release.sh "Texte de la version"   la pousse aussi (REMOTE=origin par défaut), avec main
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"
msg="${1:?Usage : tools/release.sh \"Texte de la version\"}"
remote="${REMOTE:-origin}"
if [ -n "$(git status --porcelain)" ]; then
    echo "Des modifications ne sont pas enregistrées dans Git : les commiter d'abord." >&2
    exit 1
fi
tree="$(git rev-parse HEAD:heaven)"
parent="$(git rev-parse -q --verify refs/heads/release || true)"
if [ -n "$parent" ] && [ "$(git rev-parse "$parent^{tree}")" = "$tree" ]; then
    echo "Rien de nouveau dans heaven/ : la branche release est déjà à jour."
    exit 0
fi
commit="$(git commit-tree "$tree" ${parent:+-p "$parent"} -m "$msg")"
git branch -f release "$commit"
echo "✓ release → ${commit:0:10} : $msg"
if [ -n "${PUSH:-}" ]; then
    git push "$remote" main release
fi
