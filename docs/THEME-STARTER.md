# Thème modèle : créer le thème d'un nouveau site en une commande

Le thème `examples/themes/modele` est le point de départ de chaque site Heaven. Il reprend tout ce qui s'est avéré utile
sur un vrai site, sans rien de propre à un site :

| Élément | Ce que ça apporte |
|---|---|
| **Palette en variables** | Une seule zone à modifier (`:root` en tête de `styles/site.css`) : couleurs, rayon, polices, taille du logo |
| **En-tête pleine largeur** | Bandeau et menu d'un bord à l'autre de l'écran, logo centré et agrandi |
| **Menu mobile déroulant** | Bouton « Menu » qui déplie la liste, liens de 52 px de haut, **sans JavaScript** |
| **Partage des articles** | Facebook, X, LinkedIn, WhatsApp, e-mail en fin d'article : de simples liens, aucun pisteur |
| **Contrat de thème** | Gabarits `layout` et `single` seulement : le reste vient du noyau et suit ses mises à jour |

## Créer un thème

```bash
php tools/new-theme.php mon-site "Mon Site" --accent=#c0392b --link=#992d22 --link-dark=#7b241c --bg=#fff8f6 --bg-soft=#f6e3df --zip
```

- `mon-site` : identifiant technique (minuscules, chiffres, tirets).
- `--out=dossier` : où créer le thème (par défaut `./mon-site`).
- `--zip` : produit aussi `mon-site.zip`, à importer dans Administration → Thèmes, puis à activer.
- Réglages de palette : `accent`, `link`, `link-dark`, `on-accent`, `bg`, `bg-soft`, `card`, `text`, `muted`, `line`
  (couleurs `#rrggbb`), `radius` (`10px`), `font`, `font-head`, `logo-h` (`96px`).

`link` est la couleur des liens sur fond clair et `link-dark` celle du texte accentué (menu actif, boutons de partage) :
choisis-les assez foncées pour un contraste d'au moins 4,5:1 avec le fond.

## Personnaliser ensuite

1. **Identité** : modifier uniquement le bloc « Palette » de `styles/site.css`.
2. **Mise en page propre au site** : ajouter des règles à la suite de `styles/site.css`, et des gabarits
   (`templates/list.php`, `templates/404.php`…) en les déclarant dans `theme.json` → `templates`.
3. **Version** : incrémenter `version` dans `theme.json` à chaque changement, puis reconstruire l'archive.

## À savoir

- Le thème remplace les gabarits `layout` et `single` du noyau. Si l'un d'eux évolue dans Heaven, reporter le
  changement dans `examples/themes/modele/templates/` : les thèmes déjà générés ne le reçoivent pas tout seuls.
- Pour un site qui n'a besoin que d'une palette, le plus léger est un thème sans gabarit (voir
  `docs/THEME-CONTRACT.md`) : il n'exécute aucun code.
- Vérifier chaque thème à 390 px de large (menu fermé et ouvert) et à 1280 px avant de le publier.
