# CLAUDE.md — WAM Dance Studio · wamV1
 
---
 
## Philosophie — lire en premier
 
**Penser système avant de coder.**
 
Avant tout composant, poser cette question :
> Ce composant peut-il apparaître dans un autre template ou un autre contexte ?
 
- **Oui** → `template-parts/mon-composant.php` + styles dans `style.css`
- **Non, lié à une page ou un CPT précis** → styles dans `assets/css/[contexte].css`
 
Exemples :
- Une card d'article → réutilisable → `style.css`
- Le hero de la page d'accueil → spécifique → `home.css`
- La mise en page d'un single cours → spécifique → `single-cours.css`
 
Si c'est ambigu, créer le composant le plus générique possible et demander confirmation.
 
**Ne jamais commencer à coder sans :**
- Connaître les champs ACF du CPT concerné (noms exacts, types)
- Savoir quelle variante de header utiliser
- Avoir créé et enqueué le fichier CSS du contexte si nécessaire
 
---
 
## Contexte du projet
 
**WAM Dance Studio** — école de danse à Villeneuve d'Ascq.
Thème WordPress custom `wamV1`, sans page builder.
 
Stack : PHP, CSS vanilla, JS vanilla, ACF Pro, DDEV local, GitHub, Vercel.
 
**CPT déclarés :** `cours`, `stage`
**Taxonomie partagée :** `cat_cours` (ne pas ajouter `category` ou `post_tag`)
**Rôles custom :** `professeur`, `directrice`
 
**Polices :** Outfit (défaut UI), Mallia (titres signatures), Cholo Rhita (titres graphiques)
**Thème visuel :** dark par défaut, light disponible (voir section Tokens)
 
**Variantes de header :**
- `home` → page d'accueil, logo masqué
- `default` → pages standard
- `center-forced` → articles, cours, pages intérieures
 
---
 
## Tokens — dark / light
 
Le projet utilise deux modes. Les valeurs sont définies dans `theme.json` et les variables CSS WP sont générées automatiquement via `--wp--preset--color--[slug]`.
 
| Token Figma | Slug WP | Dark | Light |
|---|---|---|---|
| `background/800` | `background-800` | `#131620` | `#F9F6F1` |
| `background/600` | `background-600` | `#1A1D28` | `#F6F2E9` |
| `background/500` | `background-500` | `#232734` | `#E9E3D8` |
| `text/normal` | `text-normal` | `#F9F4EC` | `#1D367F` |
| `text/subtext` | `text-subtext` | `#E1CDAD` | `#5F6E9B` |
| `text/disabled` | `text-disabled` | `#808080` | `#707070` |
| `accent/green` | `accent-green` | `#01E3BD` | `#0C6E7D` |
| `accent/yellow` | `accent-yellow` | `#FFDC08` | `#8E600A` |
| `accent/orange` | `accent-orange` | `#FF843D` | `#BD3F00` |
| `accent/pink` | `accent-pink` | `#FFBAD5` | `#B71088` |
 
Usage CSS : toujours `var(--wp--preset--color--accent-yellow)` — jamais la valeur hex.
Le mode light se gère par une redéfinition des variables sur `.theme-light` — jamais en dupliquant du HTML.
 
**Espacement** (via `--wp--preset--spacing--[slug]`) :
`2xs`=8px · `xs`=12px · `sm`=16px · `md`=20px · `lg`=24px · `xl`=32px · `2xl`=40px · `3xl`=48px · `9xl`=96px
 
---
 
## Figma MCP → conversion CSS
 
Le MCP génère du React + Tailwind. **Ne jamais l'utiliser tel quel.**
Convertir selon ces tables avant d'écrire le PHP et le CSS.
 
**Polices**
 
| MCP génère | CSS correct |
|---|---|
| `font-['Outfit:Regular',sans-serif] font-normal` | `font-family: var(--wp--preset--font-family--outfit); font-weight: 400;` |
| `font-['Outfit:Bold',sans-serif] font-bold` | `font-family: var(--wp--preset--font-family--outfit); font-weight: 700;` |
| `font-['Mallia:Regular',sans-serif]` | `font-family: 'Mallia', sans-serif;` |
| `font-['Cholo_Rhita:Regular',sans-serif]` | `font-family: 'Cholo Rhita', sans-serif;` |
 
**Couleurs**
 
| MCP génère | CSS correct |
|---|---|
| `var(--background\/800,#131620)` | `var(--wp--preset--color--background-800)` |
| `var(--accent\/yellow,#ffdc08)` | `var(--wp--preset--color--accent-yellow)` |
| `var(--text\/normal,#f9f4ec)` | `var(--wp--preset--color--text-normal)` |
| `var(--ui\/bgpage,#131620)` | `var(--wp--preset--color--background-800)` |
| `var(--ui\/opacity-white-bg,rgba(...))` | `rgba(249, 244, 236, 0.06)` (valeur directe) |
| *(même logique pour toutes les variables Figma)* | *(remplacer `\/` par `-` et préfixer `--wp--preset--color--`)* |
 
**Espacement**
 
| MCP génère | CSS correct |
|---|---|
| `gap-[var(--sm,16px)]` | `gap: var(--wp--preset--spacing--sm)` |
| `px-[var(--ui\/section-mx,96px)]` | `padding-inline: var(--wp--preset--spacing--9xl)` |
| *(même logique pour tous les spacings)* | *(utiliser le slug correspondant)* |
 
**Auto-layout → flexbox**
 
| MCP génère | CSS correct |
|---|---|
| `flex flex-col items-start` | `display: flex; flex-direction: column; align-items: flex-start;` |
| `flex items-center justify-between` | `display: flex; align-items: center; justify-content: space-between;` |
| `flex-[1_0_0]` | `flex: 1 0 0;` |
| `shrink-0` | `flex-shrink: 0;` |
| `gap-[var(--lg,24px)]` | `gap: var(--wp--preset--spacing--lg)` |
 
Toujours créer une classe CSS nommée par son **rôle**, pas son apparence, et l'écrire dans le bon fichier.
 
---
 
## CSS — conventions
 
### Nommage : BEM souple
 
```css
/* Bloc */
.card-cours {}
 
/* Élément */
.card-cours__title {}
.card-cours__image {}
.card-cours__btn {}
 
/* Modificateur */
.card-cours--horizontal {}
.card-cours--complet {}
```
 
Noms en français quand le concept est métier, en anglais pour les patterns UI génériques.
 
### Pas de style inline — jamais
 
```php
// ❌
<div style="display:flex; gap:24px; color:#ffdc08;">
 
// ✅ Classe dans le fichier CSS du contexte
<div class="section-cours__actions">
```
 
Exception unique : valeur dynamique calculée en PHP.
```php
// ✅ Seul cas valide
<div style="--thumb-color: <?php echo esc_attr($color_field); ?>;">
```
 
### Classes typographiques — déclarées dans `style.css`, toujours utiliser
 
| Style Figma | Classe |
|---|---|
| `title/title_cool_md` (Cholo Rhita 48px) | `.title-cool-md` |
| `title/title_cool_lg` (Cholo Rhita 36px) | `.title-cool-lg` |
| `title/title_sign_lg` (Mallia 46px) | `.title-sign-lg` |
| `title/title_sign_md` (Mallia 32px) | `.title-sign-md` |
| `title/title_sign_sm` (Mallia 24px) | `.title-sign-sm` |
| `title/title_norm_md` (Outfit Bold 32px) | `.title-norm-md` |
| `paragraph/text-lg` (Outfit 26px) | `.text-lg` |
| `paragraph/text-md` (Outfit 20px) | `.text-md` |
| `paragraph/text-sm` (Outfit 16px) | `.text-sm` |
| `paragraph/text-xs` (Outfit 14px) | `.text-xs` |
 
### Responsive — mobile-first, breakpoints fixes
 
```css
.mon-composant { /* mobile */ }
@media (min-width: 640px)  { /* tablette */ }
@media (min-width: 1024px) { /* desktop */ }
@media (min-width: 1280px) { /* large */ }
```
 
---
 
## PHP — conventions
 
### Boucle principale — `the_post()` obligatoire avant tout champ
 
```php
while (have_posts()) : the_post();
    $valeur = get_field('mon_champ'); // ✅ contexte actif
endwhile;
// ❌ get_field() hors boucle → null silencieux
```
 
### WP_Query secondaire
 
```php
$current_id = get_the_ID(); // stocker AVANT endwhile
endwhile;
 
$query = new WP_Query([...]);
while ($query->have_posts()) : $query->the_post();
    // ...
endwhile;
wp_reset_postdata(); // TOUJOURS
```
 
### ACF — ne jamais inventer de valeur par défaut
 
```php
// ❌
$duree = get_field('duree') ?: '1h00';
 
// ✅ Afficher seulement si la valeur existe
$duree = get_field('duree');
if ($duree) : ?>
    <p class="cours__duree"><?php echo esc_html($duree); ?></p>
<?php endif;
```
 
### ACF — types spéciaux
 
| Type | Retourne | Usage |
|---|---|---|
| `user` multi | array d'arrays | `$p['display_name']` |
| `post_object` | `WP_Post` ou null | vérifier `instanceof WP_Post` |
| `image` | array `['id', 'url', 'alt'…]` | `wp_get_attachment_image($img['id'], 'wam-card')` |
| `select` | slug (pas le label) | mapper manuellement si besoin |
 
### Un champ = une variable = un affichage
 
```php
// ❌ Double appel
<p><?php echo get_the_excerpt(); ?></p>
<div><?php echo get_the_excerpt(); ?></div>
 
// ✅
<?php $excerpt = get_the_excerpt(); ?>
<?php if ($excerpt) : ?>
    <p class="card__excerpt"><?php echo wp_kses_post($excerpt); ?></p>
<?php endif; ?>
```
 
### Règles générales
 
- Préfixe fonctions : `wamv1_`
- Échappement : `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`
- Commentaires en français sur les mécaniques non évidentes — pas sur chaque ligne
- ❌ `query_posts()` → ✅ `WP_Query`
- ❌ `get_avatar_url()` + `<img>` → ✅ `get_avatar()`
 
---
 
## Images
 
Toujours passer par WordPress — jamais `<img src="url_directe">`.
 
```php
// ✅ WP génère srcset, lazy loading, AVIF automatiquement
echo wp_get_attachment_image(get_post_thumbnail_id(), 'wam-card', false, [
    'class' => 'card-cours__photo',
    'alt'   => esc_attr(get_the_title()),
]);
```
 
Tailles déclarées dans `functions.php` :
- `wam-card` 800×600 crop — cards articles et cours
- `wam-thumb` 400×300 crop — grilles
- `wam-portrait` 480×640 crop — photos profs
- `wam-hero` 1536×800 no-crop — heroes
 
Overlay mix-blend systématique sur les photos :
```php
<div class="photo-wrapper">
    <?php echo wp_get_attachment_image(...); ?>
    <div class="photo-overlay" aria-hidden="true"></div>
</div>
```
```css
.photo-wrapper { position: relative; overflow: hidden; }
.photo-overlay { position: absolute; inset: 0; background: var(--wp--preset--color--background-800); mix-blend-mode: lighten; pointer-events: none; }
```
 
Ne jamais afficher une zone vide si pas d'image — conditionner avec `has_post_thumbnail()`.
 
---
 
## Templates
 
### Squelette standard
 
```php
get_header();
get_template_part('template-parts/site-header', null, ['variant' => 'center-forced']);
?>
<main id="primary" class="site-main [page-slug]">
    <?php get_template_part('template-parts/[section]'); ?>
</main>
<?php get_footer();
```
 
### Icônes — SVG inline, jamais d'emoji ni de caractère unicode
 
```php
<svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
    <path d="M6 1L11 6L6 11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
</svg>
```
 
---
 
## Accessibilité
 
- `<img>` : `alt` toujours présent (`alt=""` si décoratif)
- Bouton icône seule : `aria-label` obligatoire
- Sections : `aria-label` descriptif
- Éléments décoratifs : `aria-hidden="true"`
- Animations : vérifier `prefers-reduced-motion`
- Ne jamais supprimer `outline` sans le remplacer
- Formulaires : `<label>` lié à chaque `<input>`, erreurs dans un `role="alert"`