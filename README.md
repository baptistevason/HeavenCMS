# Heaven CMS

CMS à fichiers plats, sans base de données, pensé pour être dupliqué sur plusieurs sites : publication rapide, cache
statique, sécurité stricte, pilotable par un humain ou par une IA.

Licence : AGPL-3.0-only (voir `LICENSE`). Les mentions de droits d'auteur et de licence des composants d'origine sont
conservées dans `NOTICE`, `THIRD-PARTY-NOTICES.txt` et l'en-tête des fichiers sources : la licence l'exige.

## Caractéristiques

- Fichiers plats, aucune base de données ; pages servies depuis un cache statique.
- Back-office tenu pour des milliers de pages : liste paginée, médiathèque par lots, éditeur allégé.
- Indexation pour les moteurs et les IA : sitemap, `robots.txt`, `llms.txt`, version Markdown de chaque page, données
  structurées.
- Thèmes (`heaven-theme/1`) et fonctions (`heaven-extension/1`) installables par archive ; un thème modèle et un exemple de
  fonction dans `examples/`.
- Aucun contenu de site dans ce dépôt : chaque site apporte son thème, ses fonctions et ses données.

## Installer un site

1. Déployer le dépôt sur l'hébergement (Git), le dossier `heaven/` étant la racine du site.
2. Ouvrir l'adresse du site : l'installateur crée le compte administrateur.
3. Administration → Thèmes pour activer un thème, Administration → Fonctions pour les fonctions.

Prérequis : PHP 8.1 ou plus. Aucune base de données.

## Développer

```bash
php -S localhost:8000 -t heaven tools/router.php
```

```bash
php tests/run.php
```

- `docs/GUIDE.md` : guide d'utilisation.
- `docs/THEME-CONTRACT.md`, `docs/EXTENSION-CONTRACT.md` : contrats des thèmes et des fonctions.
- `docs/THEME-STARTER.md` : thème modèle et `tools/new-theme.php`, pour créer le thème d'un nouveau site en une commande.

## Limites connues

- 10 000 contenus par site au maximum.
- Pas encore de canal de mises à jour signé : les mises à jour passent par Git.

## Obligation de la licence AGPL

Si tu modifies Heaven et que des personnes l'utilisent à travers le réseau, tu dois leur proposer le code source de ta
version modifiée, sous la même licence.
