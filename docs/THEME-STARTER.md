# Thème de base et thèmes propres à un site

## Le thème de base « Heaven CMS » (cas général)

Livré avec le moteur (`heaven/app/bundled-themes/heavencms`), il est installé et mis à jour automatiquement sur chaque
site à chaque déploiement (voir `DEPLOIEMENT.md`). À activer une fois : Administration → Thèmes → « Heaven CMS ».

| Élément | Ce que ça apporte |
|---|---|
| **Identité par les réglages** | Logo, couleur d'accent et polices se règlent dans Réglages › Apparence, par site |
| **En-tête pleine largeur** | Bandeau et menu d'un bord à l'autre de l'écran, logo centré et agrandi |
| **Menu mobile déroulant** | Bouton « Menu » qui déplie la liste, liens de 52 px de haut, **sans JavaScript** |
| **Partage des articles** | Facebook, X, LinkedIn, WhatsApp, e-mail en fin d'article : de simples liens, aucun pisteur |
| **Mises à jour automatiques** | Une publication du moteur met à jour le thème de tous les sites |

Pour le faire évoluer : modifier le thème dans le dépôt, **incrémenter sa version** dans `theme.json`, puis publier
(`tools/release.sh`).

## Un thème propre à un site (design qui sort de ce cadre)

Quand l'identité d'un site demande plus que logo, couleur et polices (autre mise en page, gabarits propres), créer un thème
dédié à partir du thème de base :

```bash
php tools/new-theme.php mon-site "Mon Site" --accent=#c0392b --link=#992d22 --link-dark=#7b241c --bg=#fff8f6 --bg-soft=#f6e3df --zip
```

- `mon-site` : identifiant technique (minuscules, chiffres, tirets).
- `--out=dossier` : où créer le thème (par défaut `./mon-site`).
- `--zip` : produit aussi `mon-site.zip`, à importer dans Administration → Thèmes, puis à activer.
- Palette : `accent`, `link`, `link-dark`, `on-accent`, `bg`, `bg-soft`, `card`, `text`, `muted`, `line` (couleurs
  `#rrggbb`), `radius` (`10px`), `font`, `font-head`, `logo-h` (`96px`). Elle est ajoutée en tête de `styles/site.css`.

`link` est la couleur des liens sur fond clair et `link-dark` celle du texte accentué : les choisir assez foncées pour un
contraste d'au moins 4,5:1 avec le fond.

**Attention** : un thème propre est une **copie** du thème de base. Il ne reçoit plus ses mises à jour automatiques ; si
un gabarit du noyau évolue (`layout`, `single`), reporter le changement à la main. Le garder au plus léger (peu de
gabarits) limite ce travail. Un thème sans gabarit (seulement `styles`) n'exécute aucun code (voir `THEME-CONTRACT.md`).

Vérifier chaque thème à 390 px de large (menu fermé et ouvert) et à 1280 px avant de le publier.
