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

- thème absent → installé (sauf sur un site qui a déjà son propre thème) ;
- thème plus ancien **et pas en service** → remplacé (l'ancienne version est gardée dans `site/themes/.versions/`) ;
- thème plus ancien **et en service** → **jamais remplacé en silence** : un bandeau apparaît dans l'administration
  (« Mise à jour disponible : Heaven CMS 1.0.0 → 1.1.0 », bouton « Voir et installer »). L'installation se fait au clic, depuis
  Administration → Mises à jour, avec confirmation du mot de passe ;
- le cache des pages est vidé à chaque installation, les pages se régénèrent avec le nouveau thème.

Un nouveau site sans thème propre doit **activer** le thème une fois : Administration → Thèmes → « Heaven CMS » → Activer.

## Thèmes et fonctions propres à un site : le canal de mises à jour

Un thème ou une fonction qui n'est pas livré avec le moteur (celui d'un site, par exemple) se met à jour par un **canal** :

1. Le manifeste (`theme.json` ou `extension.json`) déclare l'adresse du canal :
   `"update": { "channel": "https://raw.githubusercontent.com/<compte>/<depot-public>/main/latest.json" }`.
2. Les paquets sont préparés avec `php tools/make-packages.php --out=<dossier> --repo=<compte>/<depot-public> <dossier-du-thème> …`
   (archives + `latest.json` avec empreinte SHA-256), puis publiés à la racine de la branche `main` du dépôt public.
3. Chaque site lit le canal depuis l'administration (au plus toutes les 12 h, ou au clic sur « Vérifier maintenant »).
   S'il annonce une version plus récente, le même bandeau apparaît ; le clic télécharge l'archive, vérifie l'empreinte, la
   taille, l'identifiant et la version annoncés, puis installe (et réactive le thème s'il est en service).

Confiance : le canal et les archives doivent venir du **même dépôt** de raw.githubusercontent.com (HTTPS, sans
redirection) ; une annonce vers un autre domaine est ignorée. Il n'y a pas de signature : la confiance est celle du compte
propriétaire du dépôt du canal. À protéger en conséquence (double authentification GitHub).

## Vérifier

- **Version installée** : lire `site/themes/heavencms/theme.json` sur le serveur (champ `version`).
- **Rendu** : ouvrir le site à 390 px de large (menu mobile fermé, ouvert) et à 1280 px.
- **Journal** : Administration → Sécurité, événement « themes_synced ».

## Revenir en arrière

- **Un site** : Administration → Thèmes, retour à la version précédente (gardée à chaque mise à jour) ; l'ancienne version est aussi
  dans `site/themes/.versions/`.
- **Tous les sites** : `git revert` du commit fautif, puis nouvelle publication avec `tools/release.sh`. Une version
  *inférieure* n'est jamais réinstallée automatiquement : pour annuler une version, publier un correctif avec un numéro
  plus élevé.

## Limites

- Un site qui a son **propre thème** (design très spécifique) ne reçoit pas le thème de base : il se met à jour par son
  propre canal (voir plus haut). Pour créer un thème propre : `tools/new-theme.php` (voir `THEME-STARTER.md`).
- Publier sur tous les sites d'un coup est risqué : toujours passer par un site de test d'abord.
