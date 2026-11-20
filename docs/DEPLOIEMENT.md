# Publier une mise à jour de Heaven (code et thème de base) sur tous les sites

Le thème de base **Heaven CMS** (`heaven/app/bundled-themes/heavencms`) voyage avec le code du moteur : une seule
publication met à jour le moteur ET le thème de tous les sites. L'identité de chaque site (logo, couleur d'accent, polices)
reste dans ses réglages (Réglages › Apparence) : aucune mise à jour ne l'écrase.

## Le circuit

```
modifier le thème dans le dépôt → tests → tools/release.sh → déploiement site de test → vérification → déploiement des autres sites
```

1. **Modifier** `heaven/app/bundled-themes/heavencms/` (gabarits, `styles/site.css`) et **incrémenter `version`** dans son
   `theme.json` : c'est ce numéro qui déclenche la mise à jour sur les sites.
2. **Tester** : `php tests/run.php`.
3. **Enregistrer** dans Git (`git commit`), puis publier :
   ```bash
   PUSH=1 tools/release.sh "Thème 1.0.1 : correction du menu mobile"
   ```
   Le script met à jour la branche `release` (le contenu de `heaven/` à la racine) à la suite de la précédente, puis la
   pousse avec `main`.
4. **Déployer d'abord sur le site de test**, vérifier (voir plus bas), **puis** sur les autres sites.

## Déploiement sur un hébergement

Chaque site tire la branche `release` de ce dépôt dans le dossier du site (racine du domaine) :

- **Hostinger** : opération « Deploy website Git repository » (dépôt en SSH, branche `release`, dossier vide pour la racine),
  ou déploiement automatique à chaque envoi une fois l'application GitHub liée dans hPanel.
- **o2switch / cPanel** : « Git Version Control » → créer le dépôt dans le dossier du site, branche `release`, puis
  « Deploy » (ou « Update from Remote »).

La liste des sites à tenir à jour se garde à part du moteur (le dépôt Heaven ne contient aucun site) ; le modèle de fichier
est `tools/sites.example.json`.

## Ce qui se passe sur chaque site

Au premier chargement dynamique après le déploiement (n'importe quelle page non encore en cache, ou une adresse avec un
paramètre inutile comme `/?maj=1` pour le provoquer aussitôt), Heaven compare les versions des thèmes livrés avec celles
installées :

- thème absent → installé ; thème plus ancien → remplacé (l'ancienne version est gardée dans `site/themes/.versions/`) ;
- si c'est le thème actif, il est réactivé avec ses nouveaux fichiers ;
- le cache des pages est vidé, les pages se régénèrent avec le nouveau thème.

Un nouveau site doit **activer** le thème une fois : Administration → Thèmes → « Heaven CMS » → Activer.

## Vérifier

- **Version installée** : lire `site/themes/heavencms/theme.json` sur le serveur (champ `version`).
- **Rendu** : ouvrir le site à 390 px de large (menu mobile fermé, ouvert) et à 1280 px.
- **Journal** : Administration → Sécurité, événement « themes_synced ».

## Revenir en arrière

- **Un site** : Administration → Thèmes, retour à la version précédente (gardée à chaque mise à jour).
- **Tous les sites** : `git revert` du commit fautif, puis nouvelle publication avec `tools/release.sh`. Une version
  *inférieure* n'est jamais réinstallée automatiquement : pour annuler une version, publier un correctif avec un numéro
  plus élevé.

## Limites

- Un site qui a son **propre thème** (design très spécifique) ne reçoit pas cette mise à jour : il se met à jour comme
  avant, par import de son archive. Pour un thème propre à un site : `tools/new-theme.php` (voir `THEME-STARTER.md`).
- Publier sur tous les sites d'un coup est risqué : toujours passer par un site de test d'abord.
