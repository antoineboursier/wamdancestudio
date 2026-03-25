# README.md — WAM Dance Studio · wamV1

Lu automatiquement à chaque session. Règles absolues — ne jamais ignorer.

---

## Philosophie — lire en premier

**Penser système avant de coder.**

Avant tout composant :
> Ce composant peut-il apparaître dans un autre template ?

- **Oui** → `template-parts/` + styles dans `components.css`
- **Non, lié à une page précise** → styles dans `assets/css/[contexte].css`

**Ne jamais commencer sans :**
- Champs ACF du CPT (noms exacts, types)
- Variante de header
- Fichier CSS du contexte créé et enqueué

---

## Contexte du projet

WAM Dance Studio — école de danse, Villeneuve d'Ascq.
Thème WordPress custom `wamV1`, sans page builder.
Stack : PHP, CSS vanilla, JS vanilla, ACF Pro, DDEV local, GitHub.

CPT : `cours`, `stages`, `wam_membre`
Taxonomie : `cat_cours` (ne pas ajouter `category` ou `post_tag`)
Rôles : `professeur`, `directrice`
Polices : Outfit (défaut), Mallia (titres signatures), Cholo Rhita (titres graphiques)
Thème : dark par défaut, light via `html.wam-theme-light`

Variantes header : `home` | `default` | `center-forced`

---

## Stack CSS — vanilla, pas de build

Pas de Tailwind. Pas de Node.js. Pas de build.
Tout le CSS est natif et chargé directement par WordPress.
Modifier un CSS = sauvegarder = visible immédiatement.

```
assets/css/
├── tokens.css       → variables CSS
├── base.css         → reset, typographie, classes Gutenberg
├── components.css   → composants réutilisables
├── layout.css       → mise en page globale
├── home.css         → styles is_front_page()
├── accessibility.css
└── editor.css       → back-office Gutenberg
```

---

## Tokens

### Couleurs

Toujours les variables — jamais les valeurs hex.

| Token Figma | Variable WP | Alias tokens.css |
|---|---|---|
| `background/800` | `--wp--preset--color--background-800` | `--wam-color-page-bg` |
| `background/600` | `--wp--preset--color--background-600` | `--wam-color-card-bg` |
| `background/500` | `--wp--preset--color--background-500` | `--wam-color-input-bg` |
| `text/normal` | `--wp--preset--color--text-normal` | `--wam-color-text` |
| `text/subtext` | `--wp--preset--color--text-subtext` | `--wam-color-subtext` |
| `text/disabled` | `--wp--preset--color--text-disabled` | `--wam-color-disabled` |
| `accent/green` | `--wp--preset--color--accent-green` | `--wam-color-green` |
| `accent/yellow` | `--wp--preset--color--accent-yellow` | `--wam-color-yellow` |
| `accent/orange` | `--wp--preset--color--accent-orange` | `--wam-color-orange` |
| `accent/pink` | `--wp--preset--color--accent-pink` | `--wam-color-pink` |

Semi-transparents (Glassmorphism) & Overlays :
- `--wam-color-glass-white` : rgba(249,244,236,0.06)
- `--wam-color-glass-white-hover` : rgba(249,244,236,0.12)
- `--wam-color-glass-green` : rgba(1,227,189,0.12)
- `--wam-color-glass-yellow` : rgba(253,217,0,0.10)
- `--wam-color-overlay-dark` : rgba(19, 22, 32, 0.7)
- `--wam-color-overlay-photo` : rgba(21, 28, 50, 0.7)

### Espacement → `--wam-spacing-[slug]`
`2xs`=8px · `xs`=12px · `sm`=16px · `md`=20px · `lg`=24px · `xl`=32px · `2xl`=40px · `3xl`=48px · `4xl`=56px · `9xl`=96px

### Typographie → `--wam-font-size-[slug]`
`sm`=14px · `md`=16px · `lg`=20px · `xl`=26px · `h1`=46px · `h2`=32px · `h3`=24px
Display : `display-lg`=48px · `display-md`=36px

### Border radius → `--wam-radius-[taille]`
`xs`=4px · `sm`=8px · `md`=12px · `lg`=16px · `xl`=20px · `2xl`=24px · `3xl`=32px · `pill`=999px

### Transitions → `--wam-transition[-slow|-spring]`

---

## Figma MCP → CSS

Le MCP sort du React+Tailwind. Ne jamais utiliser tel quel. Convertir :

Polices :
- `font-['Outfit:Regular',...]` → `font-family: var(--wam-font-body);`
- `font-['Mallia:Regular',...]` → `font-family: var(--wam-font-graphical-1);`
- `font-['Cholo_Rhita:Regular',...]` → `font-family: var(--wam-font-graphical-2);`

Couleurs : `var(--accent\/yellow,#ffdc08)` → `var(--wam-color-yellow)`
Espacement : `gap-[var(--sm,16px)]` → `gap: var(--wam-spacing-sm)`
Flexbox : `flex flex-col items-start` → `display:flex; flex-direction:column; align-items:flex-start;`

Toujours créer une classe CSS nommée par son rôle, jamais par son apparence.

---

## CSS — conventions

### Nommage BEM souple
```css
.card-cours {}           /* Bloc */
.card-cours__title {}    /* Élément */
.card-cours--complet {}  /* Modificateur */
```

### Pas de style inline — jamais
```php
// Seule exception : valeur dynamique PHP
<div style="--color: <?php echo esc_attr($field); ?>;">
// Exception documentée : style="font-size:6.25rem" dans section-videos
```

### Classes typographiques (dans style.css)
`.title-cool-lg` (Cholo 48px) · `.title-cool-md` (Cholo 36px)
`.title-sign-lg` (Mallia 46px) · `.title-sign-md` (Mallia 32px) · `.title-sign-sm` (Mallia 24px)
`.title-norm-lg` (Outfit Bold 32px) · `.title-norm-md` (Outfit Bold 32px) · `.title-norm-sm` (Outfit Bold 22px)
`.text-lg` (26px) · `.text-md` (20px) · `.text-sm` (16px) · `.text-xs` (14px)
`.fw-bold` · `.color-muted` / `.color-disabled` (alias)

### `is-style-*` vs classe directe — arbitrage
- **`is-style-*`** : blocs Gutenberg uniquement — appliqué via l'éditeur sur `core/paragraph` ou `core/heading`.
- **Classe directe** (`.title-cool-md`, `.text-sm`…) : PHP / HTML statique — à utiliser dans les template-parts et les pages custom.
- ❌ Ne jamais écrire `is-style-*` dans le PHP. ❌ Ne jamais appliquer une classe directe typographique à un bloc Gutenberg.

### Responsive — mobile-first
```css
.el { /* mobile */ }
@media (min-width: 640px)  { /* tablette */ }
@media (min-width: 1024px) { /* desktop */ }
@media (min-width: 1280px) { /* large */ }
```

---

## PHP

```php
// Boucle — the_post() avant get_field()
while (have_posts()) : the_post();
    $val = get_field('champ');
endwhile;

// WP_Query secondaire
$current_id = get_the_ID(); // AVANT endwhile
endwhile;
$q = new WP_Query([...]);
while ($q->have_posts()) : $q->the_post(); endwhile;
wp_reset_postdata(); // TOUJOURS

// ACF — pas de fallback hardcodé
$val = get_field('duree');
if ($val) echo esc_html($val);

// Un champ = une variable
$excerpt = get_the_excerpt();
if ($excerpt) echo wp_kses_post($excerpt);
```

ACF types : `user`→array, `post_object`→WP_Post|null, `image`→array['id','url','alt'], `select`→slug

Règles : préfixe `wamv1_`, `esc_html/attr/url/wp_kses_post`, commentaires français
❌ `query_posts()` · ❌ `get_avatar_url()+<img>` → `get_avatar()`

---

## Images

```php
// Toujours wp_get_attachment_image()
echo wp_get_attachment_image(get_post_thumbnail_id(), 'wam-card', false, [
    'class' => 'card__photo',
    'alt'   => esc_attr(get_the_title()),
]);
```

| Slug | Dimensions | Ratio | Contexte d'usage |
|------|-----------|-------|-----------------|
| `wam-hero` | 1536×800 | 16:10 | Héros single plein écran |
| `wam-card` | 800×600 | 4:3 | Colonne hero singles (cours, stage), cards liste stages/articles |
| `wam-portrait` | 480×640 | 3:4 | Photo profil professeur (`single-wam_membre`) |
| `wam-thumb` | 400×300 | 4:3 | Mini-cards, vignettes cours liés sur single prof |
| `wamv1-page-hero` | 1536×600 | ~5:2 | Banner hero article (`single.php`) |
| `wam-page-thumbnail` | 624×200 | ~3:1 | Header listing pages (`page-cours-collectifs`, `page-stages-tous`) |

> ⚠️ Après ajout d'une nouvelle taille : régénérer les miniatures via `ddev exec wp media regenerate --yes`

Overlay systématique :
```css
.photo-wrapper { position: relative; overflow: hidden; }
.photo-overlay { position: absolute; inset: 0; background: var(--wam-color-page-bg); mix-blend-mode: lighten; pointer-events: none; }
```

---

## Accessibilité

- `alt` toujours sur `<img>` (vide si décoratif)
- `aria-label` sur bouton icône seule
- `aria-label` sur sections
- `aria-hidden="true"` sur éléments décoratifs
- `prefers-reduced-motion` respecté
- Ne jamais supprimer `outline`