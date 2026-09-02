# CLAUDE.md — BioLinks

Plugin WordPress gratuit/open-source : alternative auto-hébergée à Linktree / Beacons / Bio.link. Une page "link in bio" standalone avec stats intégrées, templates, drag-and-drop et détection GA.

## Localisation du code

- **Code source réel** : `/home/jerem/PhpstormProjects/biolinks/` (ce dossier-ci). `/home/jerem/claude-scripts/biolinks` est un symlink qui pointe ici.
- **Repo GitHub** : `github.com/JeremieSamson/biolinks` (projet open source → exception à la règle Forgejo par défaut).
- **Instance prod de démo** : `nomadesurrails.fr/linkedtree/` (page ID 3383, slug `linkedtree`, template dark, accent `#0a7286`).
- **Landing page marketing** : `symfolidity.com/biolinks` (fichier `content/biolinks.md` dans `/home/jerem/PhpstormProjects/symfolidity.com/`, déployé manuellement en tant que `ubuntu` sur kimsufi2 à `/var/www/symfolidity.com/biolinks/`).
- **Article case study** : `symfolidity.com/articles/biolinks-plugin-wordpress-link-in-bio/`.
- **Tuto utilisateur** : brouillon ID 3389 sur nomadesurrails.fr (à publier pour la stratégie SEO FR).

## État de release

- Dernière version : **v1.1.11** publiée sur GitHub + Forgejo + WP.org le 2026-08-26 (tag `v1.1.11`, zip ~134 KB, 34 fichiers). Ajoute Bluesky, Mastodon, Blog et RSS aux réseaux sociaux (PR #7). Précédée par v1.1.10 le 2026-08-13 (traductions JS de l'admin) et v1.1.9 le même jour (compatibilité WP 7.1).
- Historique git nettoyé des co-auteurs AI (tag backup `backup-before-claude-removal` conservé en sécurité, supprimable).
- **WordPress.org : APPROUVÉ le 2026-04-21**. Page publique : https://wordpress.org/plugins/biolinks/. Accès SVN actif pour user `nomadesurrails`.
- **SVN workspace** : `/home/jerem/claude-scripts/biolinks-svn/` (checkout de `https://plugins.svn.wordpress.org/biolinks/`). Credentials cachés dans `~/.subversion/auth/svn.simple/`. Trunk pushed à rev 3666498, tag `1.1.11` à rev 3666502, assets à rev 3666501 (screenshot-2 « réseaux sociaux » régénéré pour 1.1.11).
- **Plugin strictement zero-network depuis v1.1.2** : plus aucun appel externe (retrait complet de l'intégration GA). Stats de clic internes uniquement.

## Stack technique

PHP 8+, WordPress 5.9+ (testé jusqu'à 7.1), MySQL, vanilla JS, Chart.js (vendored), SortableJS (vendored). GPLv2.

Structure du zip :
- `biolinks.php` — bootstrap plugin + constante `BIOLINKS_VERSION`
- `includes/` — classes (admin, front, db, icons)
- `templates/` — 5 thèmes CSS (light, colorful, minimal, glass, dark) + `page.php`
- `assets/` — JS admin/front + vendor (Chart.js, Sortable.js)
- `languages/` — `.pot`, `fr_FR.po`, `fr_FR.mo` (traductions informelles en "tu")
- `uninstall.php` — cleanup avec variables préfixées `biolinks_`

## Conventions WordPress.org (apprises)

- **Superglobales** : toujours `wp_unslash()` AVANT toute `sanitize_*()` / `absint()` / `esc_url_raw()`. Pas d'exception.
- **Base de données** : création via `dbDelta()` (require `ABSPATH . 'wp-admin/includes/upgrade.php'`). Syntaxe : pas de `IF NOT EXISTS`, double espace après `PRIMARY KEY`, `;` en fin de statement.
- **Échappement** : valeurs numériques → `esc_html()` avec cast string explicite. HTML autorisé → `wp_kses()` + whitelist d'attributs stricte.
- **i18n** : ne pas appeler `load_plugin_textdomain()` (auto-loaded depuis WP 4.6). Ajouter `translators:` comment pour chaque placeholder dans `printf()`.
- **Header `Tested up to`** : maintenir à jour avec la version WP actuelle, sinon le plugin disparaît de la recherche directory. Il est présent **dans les deux fichiers** (`readme.txt` et le header de `biolinks.php`) : les deux doivent être bumpés ensemble, sinon Plugin Check lève `mismatched_tested_up_to_header` (le header PHP écrase la valeur du readme).
- **Prepared statements `%i`** : nécessite WP 6.2+, donc incompatible avec le floor 5.9+ de BioLinks → rester sur `%s + esc_sql()`.

## Credit link (opt-in)

- Lien dofollow vers `https://symfolidity.com/biolinks` avec `target="_blank" rel="noopener"`.
- Opt-in **désactivé par défaut** (`show_credit => '0'`), toggle admin labellisé "Support the developer" avec description pédagogique (plugin gratuit, temps perso, optionnel). Conforme aux guidelines WP.org (pas de manipulation, pas de récompense fonctionnelle).
- Anchor text simple "BioLinks" pour éviter over-optimization.
- Estimation : 10-20% d'activation volontaire × 30% sites DA>10 = 3-6% d'installations génèrent backlink qualifié.

## Bannière de support (conditions d'affichage)

`BioLinks_Admin::should_show_support_banner()` cumule cinq conditions, toutes obligatoires :

1. `support_dismissed` non posé (le bouton « I already left a review » le pose définitivement) ;
2. `support_snooze_until` dépassé (« Maybe later » repousse de 14 jours) ;
3. au moins 7 jours depuis `activated_at` ;
4. une page BioLinks créée (`page_id`) et au moins un lien configuré ;
5. **au moins `SUPPORT_MIN_CLICKS` clics cumulés** (25), lus via `BioLinks_DB::get_total_clicks()`.

Le critère 5 est le seul qui mesure l'usage réel plutôt que la configuration : une install qui a créé sa page puis l'a abandonnée ne verra jamais la bannière. Seuil volontairement au-dessus du bruit d'auto-tests de l'administrateur.

Calibrage du seuil (mesuré le 2026-09-02 sur la démo `nomadesurrails.fr/linkedtree/`) : 41 clics réellement logués en 5 mois sur un site à ~101 articles, le reste des 137 `click_count` venant de l'import Click Tracker v2. Un seuil à 50 n'aurait donc quasiment jamais été atteint par une install ordinaire, d'où 25.

Contrainte WP.org rappelée : aucune récompense fonctionnelle ni manipulation en échange d'un avis, et le compteur reste local (le plugin est zero-network).

## Gotcha LiteSpeed (instance nomadesurrails.fr)

Les pages BioLinks standalone sont cassées visuellement en Guest Mode LiteSpeed (Combine/Async/Critical CSS supprime le CSS du template). **Obligation** : ajouter chaque slug d'instance BioLinks à l'option `optm-exc` :

```bash
ssh seopress 'cd /home/nomadesurrails.seopress.host/public_html && sudo wp --allow-root litespeed-option set optm-exc "/linkedtree/"'
```

Une URL par ligne. Page reste cachée (HIT conservé), juste exclue des optimisations CSS. Solution long terme : inliner le CSS du template dans le HTML côté plugin (solution B évoquée, pas implémentée).

## Procédure i18n (après ajout ou modification de chaînes)

`wp-cli` n'est pas installé sur la machine hôte : tout passe par le container du banc de test, après un `rsync` du repo (voir mémoire `biolinks-banc-test-local`). `wp i18n make-mo` remplace l'ancien recours à `babel`.

```bash
P=/var/www/html/wp-content/plugins/biolinks
docker exec nomadesurrails-wp wp i18n make-pot $P $P/languages/biolinks.pot \
  --slug=biolinks --domain=biolinks --exclude=assets/vendor --package-name="BioLinks" --allow-root
docker exec nomadesurrails-wp wp i18n update-po $P/languages/biolinks.pot $P/languages/ --allow-root
# traduire les msgstr vides dans les .po, puis :
docker exec nomadesurrails-wp wp i18n make-mo $P/languages/ --allow-root
docker exec nomadesurrails-wp wp i18n make-json $P/languages/ --no-purge --allow-root
```

**Piège `make-json` (wp-cli 2.12)** : les `.json` sont nommés d'après le md5 de `assets/a.js` au lieu de `assets/admin.js`, donc WordPress ne les trouve jamais et les traductions JS restent en anglais, silencieusement. Renommer avec le bon hash après génération :

```bash
HASH=$(echo -n "assets/admin.js" | md5sum | cut -d' ' -f1)   # 558e5e2053e34e0b46c2256cfe60b60b
```

Contrôle : `wp eval` doit voir le fichier, et `wp.i18n.__('Choose a profile photo', 'biolinks')` doit renvoyer le français dans la console admin d'un site en `fr_FR`. WordPress injecte ces traductions **inline** (`setLocaleData`), il n'y a donc aucune requête HTTP vers le `.json` : ne pas conclure à un échec sur cette base.

Les traductions livrées dans `languages/` sont un fallback : sur WP.org, celles de translate.wordpress.org priment. Toute nouvelle chaîne doit donc aussi être traduite sur GlotPress (compte PTE fr_FR).

## Procédure de release (version X.Y.Z)

1. Bump dans 2 fichiers du dossier `biolinks/` :
   - `biolinks.php` : header `* Version: X.Y.Z` + `define('BIOLINKS_VERSION', 'X.Y.Z')` (+ header `* Tested up to:` si la version WP cible change)
   - `readme.txt` : `Stable tag: X.Y.Z` + entrée `= X.Y.Z =` changelog + entrée `Upgrade Notice` (+ `Tested up to:` si besoin)
   Contrôle : `wp plugin check biolinks` doit renvoyer 0 ERROR (les ~102 WARNING DirectDatabaseQuery / InterpolatedNotPrepared sont structurels, dus au floor 5.9 qui interdit `%i`).
2. `git commit -m "chore(release): bump version to X.Y.Z"`
3. `git tag -a vX.Y.Z -m "Release vX.Y.Z\n\n- ..."`
4. `git push origin main && git push origin vX.Y.Z`
5. Build zip :
   ```bash
   cd /home/jerem/PhpstormProjects
   zip -r /tmp/biolinks-X.Y.Z.zip biolinks/ -x "biolinks/.git/*" "biolinks/README.md" "biolinks/screenshots/*" "biolinks/tools/*" "biolinks/CLAUDE.md" "biolinks/Makefile" "biolinks/docs/*" -q
   ```
   Attendu : ~127 KB, 32 fichiers.
6. `gh release create vX.Y.Z /tmp/biolinks-X.Y.Z.zip --title "vX.Y.Z" --notes "..." --repo JeremieSamson/biolinks`
7. Push vers WP.org SVN (plugin maintenant approuvé, toute nouvelle version passe par SVN) :
   ```bash
   cd /home/jerem/claude-scripts/biolinks-svn
   rsync -av --delete --exclude='.git/' --exclude='README.md' --exclude='screenshots/' --exclude='docs/' --exclude='tools/' --exclude='CLAUDE.md' --exclude='Makefile' \
     /home/jerem/PhpstormProjects/biolinks/ trunk/
   svn add --force trunk/*
   svn commit trunk -m "Release X.Y.Z"
   svn cp https://plugins.svn.wordpress.org/biolinks/trunk https://plugins.svn.wordpress.org/biolinks/tags/X.Y.Z -m "Tagging version X.Y.Z"
   ```
   Credentials SVN cachés dans `~/.subversion/auth/svn.simple/` (rafraîchir via `rm -rf ~/.subversion/auth/svn.simple/` puis un `svn commit` interactif si mot de passe rotated).

Exclusions zip justifiées : `.git/*` (inutile), `README.md` (version GitHub, `readme.txt` est la version WP.org), `screenshots/*` (WP.org serve via repo SVN assets séparé).

## Assets WP.org (banner, icon, screenshots)

Les assets vivent dans `biolinks-svn/assets/` (racine SVN, **pas dans trunk/**). Ils n'ont pas de versionning et se mettent à jour via simple commit.

Fichiers actuels (rev 3511597) :
- `icon-128x128.gif`, `icon-256x256.gif` : icon animé zoom/dezoom sur la démo "Nomade sur Rails" (60 frames, 9s cycle, palette LIBIMAGEQUANT). Générés par `tools/generate_gif.py`.
- `banner-1544x500.png`, `banner-772x250.png` : banner avec phone mockup de la démo + widget stats custom (bar chart teal) + titre/tagline. Généré par `tools/generate_banner.py`.
- `screenshot-1.png` à `screenshot-6.png` : captures admin, mappent aux entrées `== Screenshots ==` du `readme.txt`. Sources dans `nomadesurrails.fr/biolinks/screenshots/`.

Scripts de génération dans `tools/` à la racine du repo (`/home/jerem/PhpstormProjects/biolinks/tools/`, exclus du zip et du SVN trunk). Sortie vers `/tmp/biolinks-assets/` par défaut, puis `cp` vers `biolinks-svn/assets/` et `svn commit`. Dépendances : Pillow 10+, libimagequant (inclus), polices Ubuntu et DejaVu (`/usr/share/fonts/truetype/`).

## Stratégie SEO

Analyse SERP du 2026-04-03. Mots-clés cibles FR à faible concurrence :
- `alternative linktree wordpress`
- `créer page link in bio wordpress`
- `plugin link in bio wordpress gratuit`

Concurrence anglophone uniquement (WPBeginner, SeedProd, Kadence) poussant des page builders payants. **Aucun plugin WP gratuit dédié ne domine** le créneau.

Triangle de renforcement :
1. `symfolidity.com/articles/biolinks-...` — article technique, cible devs, lien vers landing
2. `symfolidity.com/biolinks` (ou `nomadesurrails.fr/biolinks` selon réécriture) — landing, capte les backlinks credit
3. `nomadesurrails.fr` brouillon 3389 — tuto utilisateur FR, lien vers landing + GitHub

## Conventions de code BioLinks

- Pas de PHPDoc ni commentaires triviaux (règle globale).
- Fonctions/classes préfixées `biolinks_` ou namespacées dans la classe `BioLinks_*` pour éviter les collisions WP.org.
- Variables globales dans `uninstall.php` préfixées `biolinks_` (ex: `$biolinks_config_table`).
- Tests : pas de suite automatisée (plugin WordPress, cohérent avec règle globale).
- **Zero external HTTP** : ne jamais réintroduire d'appel à un domaine tiers (ni gtag.js, ni CDN, ni API externe). Positionnement marketing verrouillé.
- **Pas d'em dashes** dans les strings user-facing (readme.txt, README.md, admin UI, .po/.pot).
- **Chaînes JS** : jamais de littéral user-facing dans `assets/*.js`. Passer par `__('...', 'biolinks')` (le raccourci `__` défini en tête de `admin.js` retombe sur l'identité si `wp.i18n` manque), déclarer `wp-i18n` en dépendance et appeler `wp_set_script_translations()`. Pour le JS inline généré côté PHP (widget dashboard), injecter les chaînes déjà traduites dans le payload `wp_json_encode()`.
