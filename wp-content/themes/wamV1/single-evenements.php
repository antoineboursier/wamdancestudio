<?php
/**
 * Template for single evenements CPT
 *
 * @package wamv1
 */

get_header();
?>

<main id="primary" class="site-main">
    <div class="page-layout__inner">

        <?php
        while (have_posts()):
            the_post();

            // Champs ACF
            $has_acf = function_exists('get_field');
            $sous_titre = $has_acf ? get_field('sous_titre') : ''; // Champ standard
            $date_event = $has_acf ? get_field('date_event') : '';
            $h_debut = $has_acf ? get_field('horaire_debut_event') : '';
            $h_fin = $has_acf ? get_field('horaire_fin_event') : '';

            $p_obj = $date_event ? DateTime::createFromFormat('d/m/Y', $date_event) : null;
            $icon_dir = get_template_directory_uri() . '/assets/images/';
            ?>

            <!-- Breadcrumb : Accueil > [Titre de la page] -->
            <?php get_template_part('template-parts/breadcrumb', null, [
                'id' => 'breadcrumb-evenement',
                'full' => true,
            ]); ?>

            <div id="section-evenement-header" class="page-hero">
                <div class="page-hero__content page-hero__content--lg">
                    <h1 class="page-hero__title title-cool-lg has-accent-yellow-color">
                        <?php the_title(); ?>
                    </h1>

                    <?php if ($sous_titre): ?>
                        <p class="page-hero__subtitle title-norm-sm">
                            <?php echo esc_html($sous_titre); ?>
                        </p>
                    <?php endif; ?>

                    <!-- Affichage de la Date, Horaires et Lieu (ACF + Static) -->
                    <?php if ($p_obj): ?>
                        <div class="evenement-infos-meta">

                            <!-- Date et Heure -->
                            <div class="evenement-date-info">
                                <span class="btn-icon" style="--icon-url: url('<?php echo esc_url($icon_dir . 'calendar.svg'); ?>'); --icon-size: 24px;"></span>
                                <div>
                                    <p class="title-norm-sm color-text">
                                        <?php
                                        // Affiche ex: "Samedi 24 Septembre 2025"
                                        echo ucfirst(date_i18n('l d F Y', $p_obj->getTimestamp()));
                                        ?>
                                    </p>
                                    <?php if ($h_debut):
                                        // Conversion horaire g:i a -> H\hi
                                        $debut_formate = date('G\hi', strtotime($h_debut));
                                        $fin_formate = $h_fin ? date('G\hi', strtotime($h_fin)) : '';
                                        ?>
                                        <p class="text-md color-subtext mt-3xs">
                                            De <?php echo esc_html($debut_formate); ?>
                                            <?php if ($fin_formate) echo ' à ' . esc_html($fin_formate); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Lieu -->
                            <?php if (wam_adresse_visible()): ?>
                                <div class="evenement-location-info wam-adresse-globale">
                                    <span class="btn-icon" style="--icon-url: url('<?php echo esc_url($icon_dir . 'map.svg'); ?>'); --icon-size: 24px;"></span>
                                    <div>
                                        <p class="page-cours__address-name"><?php echo esc_html(wam_nom_lieu()); ?></p>
                                        <p class="page-cours__address-street mt-3xs"><?php echo nl2br(esc_html(wam_adresse_lieu())); ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>

                        </div>
                    <?php endif; ?>

                </div>

                <?php if (has_post_thumbnail()): ?>
                    <!-- Image à la une -->
                    <div class="page-hero__image page-hero__image--sm">
                        <?php the_post_thumbnail('wam-page-thumbnail', ['class' => 'page-hero__image-img']); ?>
                        <div class="page-hero__image-overlay"></div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Contenu Gutenberg -->
            <div id="section-evenement-content" class="page-content">
                <div class="page-content__inner wam-prose">
                    <?php the_content(); ?>
                </div>
            </div>

            <!-- Séparateur + Contenus similaires (affiche seulement s'il y en a d'autres) -->
            <?php
            $other_events = get_posts([
                'post_type' => 'evenements',
                'posts_per_page' => 1,
                'post__not_in' => [get_the_ID()],
                'fields' => 'ids',
            ]);

            if (!empty($other_events)): ?>
                <!-- Séparateur -->
                <?php get_template_part('template-parts/separator'); ?>

                <!-- Contenus similaires -->
                <?php get_template_part('template-parts/related-content', null, [
                    'post_type' => 'evenements',
                    'title' => 'D\'autres évènements à venir :',
                    'icon' => 'calendar.svg'
                ]); ?>
            <?php endif; ?>

        <?php endwhile; ?>

    </div>
</main>

<?php get_footer(); ?>