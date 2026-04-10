<?php
/**
 * Template part: Card cours (horizontale)
 *
 * Réutilisable dans page-cours-collectifs.php et partout où on liste des cours.
 * Doit être appelé dans une WP_Query loop (the_post() requis).
 *
 * Champs ACF (groupe "Métadonnées Cours") :
 *   sous_titre       (text)       — tranche d'âge / niveau
 *   jour_de_cours    (select)     — "01day"…"07day" → mappé Lundi…Dimanche
 *   heure_debut      (text)       — ex. "12h30"
 *   heure_de_fin     (text)       — ex. "14h30"
 *   complete_cours   (true_false) — badge orange "Complet"
 *   dernieres_places (true_false) — badge jaune "Dernières places" (optionnel)
 *
 * Variante enfant : terme slug 'danse-enfant' dans cat_cours
 *   → titre en Cholo Rhita (is-style-title-cool-md)
 *
 * @package wamv1
 */

$has_acf = function_exists('get_field');

/* ---- Variante enfant ---- */
$is_enfant = wamv1_is_enfant_variant();

/* ---- Champs ACF ---- */
$sous_titre   = $has_acf ? get_field('sous_titre')        : '';
$jour_value   = $has_acf ? get_field('jour_de_cours')     : '';
$heure_debut  = $has_acf ? get_field('heure_debut')       : '';
$heure_fin    = $has_acf ? get_field('heure_de_fin')      : '';
$complet      = $has_acf ? get_field('complete_cours')    : false;
$dernieres    = $has_acf ? get_field('dernieres_places')  : false;

/* ---- Label jour ---- */
$jour_label = wamv1_get_day_label($jour_value);

/* ---- Horaires ---- */
$horaires = ($heure_debut && $heure_fin) ? esc_html($heure_debut) . ' – ' . esc_html($heure_fin) : '';

/* ---- data-cat pour le filtre JS ---- */
$terms      = get_the_terms(get_the_ID(), 'cat_cours');
$term_slugs = [];
if ($terms && ! is_wp_error($terms)) {
    foreach ($terms as $t) {
        $term_slugs[] = esc_attr($t->slug);
    }
}
$data_cat = implode(' ', $term_slugs);

/* ---- Image de substitution selon variante ---- */
$icons_path      = get_template_directory_uri() . '/assets/images/';
$placeholder_svg = $is_enfant ? 'dancer_warmup.svg' : 'dancer_courssolo.svg';

/* ---- Classes ---- */
$card_classes = ['card-cours'];
if ($is_enfant) $card_classes[] = 'card-cours--enfant';
if ($complet)   $card_classes[] = 'card-cours--complet';
?>

<article id="post-<?php the_ID(); ?>"
         class="<?php echo esc_attr(implode(' ', $card_classes)); ?>"
         data-cat="<?php echo $data_cat; ?>"
         data-title="<?php echo esc_attr(get_the_title()); ?>">

    <!-- Badge statut — position:absolute relative à l'article (.card-cours) -->
    <?php if ($complet) : ?>
        <div class="card-cours__badge card-cours__badge--complet" aria-label="Cours complet">
            <img src="<?php echo $icons_path; ?>sad-emoji.svg" 
                 width="24" height="24" alt="" aria-hidden="true">
            <span>Complet</span>
        </div>
    <?php elseif ($dernieres) : ?>
        <div class="card-cours__badge card-cours__badge--places" aria-label="Dernières places disponibles">
            <span>Dernières places</span>
        </div>
    <?php endif; ?>

    <!-- Media — uniquement si image à la une disponible -->
    <?php if (has_post_thumbnail()) : ?>
    <div class="card-cours__media">
        <!-- Lien cliquable sur la zone image -->
        <a href="<?php the_permalink(); ?>" style="position: absolute; inset: 0; z-index: 1;" aria-hidden="true" tabindex="-1"></a>

        <?php echo wamv1_get_image_with_overlay(
            get_post_thumbnail_id(),
            'wam-card-thumbnail',
            'card-cours__img-wrapper',
            ['class' => 'card-cours__img']
        ); ?>
    </div><!-- .card-cours__media -->
    <?php endif; ?>

    <!-- Body -->
    <div class="card-cours__body">

        <div class="card-cours__header">
            <?php
            /*
             * Le lien est porté par le titre.
             * Le ::after CSS s'étend sur toute la carte (position:absolute; inset:0)
             * pour rendre la zone cliquable sans bloquer l'inspecteur.
             */
            ?>
            <h3 class="card-cours__title <?php echo $is_enfant ? 'is-style-title-cool-md' : 'is-style-title-norm-md'; ?>">
                <a href="<?php the_permalink(); ?>" class="card-cours__link">
                    <?php the_title(); ?>
                </a>
                <?php if ($sous_titre) : ?>
                    <span class="text-md color-subtitle" style="display: block;"><?php echo esc_html($sous_titre); ?></span>
                <?php endif; ?>
            </h3>
        </div>

        <div class="card-cours__footer">

            <div class="card-cours__schedule">
                <?php if ($jour_label) : ?>
                    <span class="card-cours__day text-lg color-text"><?php echo esc_html($jour_label); ?></span>
                <?php endif; ?>
                <?php if ($horaires) : ?>
                    <span class="card-cours__time text-sm color-text"><?php echo $horaires; ?></span>
                <?php endif; ?>
            </div>

            <span class="card-cours__cta <?php echo $complet ? 'card-cours__cta--disabled' : ''; ?>"
                  aria-hidden="true">
                <span class="btn-icon btn-icon--sm"
                      style="--icon-url: url('<?php echo $icons_path; ?>chevron-right.svg');"></span>
            </span>

        </div><!-- .card-cours__footer -->

    </div><!-- .card-cours__body -->

</article>
