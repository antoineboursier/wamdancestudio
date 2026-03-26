<?php
/**
 * Template for single evenements CPT
 *
 * @package wamv1
 */

get_header();
get_template_part('template-parts/site-header');
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

            <div id="section-evenement-header" class="page-header">
                <div class="page-header__meta page-header__meta--lg">
                    <!-- H1 : Demandé en title-cool-lg avec accent yellow + subtitle en span -->
                    <h1 class="page-header__title title-cool-lg has-accent-yellow-color">
                        <?php the_title(); ?>
                        <?php if ($sous_titre): ?>
                            <span class="page-header__subtitle title-norm-sm color-subtext"
                                style="display: block; margin-top: var(--wam-spacing-2xs);">
                                <?php echo esc_html($sous_titre); ?>
                            </span>
                        <?php endif; ?>
                    </h1>

                    <!-- Affichage de la Date, Horaires et Lieu (ACF + Static) -->
                    <?php if ($p_obj): ?>
                        <div class="evenement-infos-meta" style="margin-top: var(--wam-spacing-md); display: flex; flex-direction: column; gap: var(--wam-spacing-sm);">
                            
                            <!-- Date et Heure -->
                            <div class="evenement-date-info" style="display: flex; align-items: flex-start; gap: var(--wam-spacing-sm);">
                                <span class="btn-icon" style="--icon-url: url('<?php echo esc_url($icon_dir . 'calendar.svg'); ?>'); --icon-size: 24px; color: var(--wam-color-subtext); margin-top: 2px;"></span>
                                <div>
                                    <p class="title-norm-sm color-text" style="margin: 0;">
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
                                        <p class="text-md color-subtext mt-3xs" style="margin: 0;">
                                            De <?php echo esc_html($debut_formate); ?>
                                            <?php if ($fin_formate) echo ' à ' . esc_html($fin_formate); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Lieu -->
                            <div class="evenement-location-info" style="display: flex; align-items: flex-start; gap: var(--wam-spacing-sm);">
                                <span class="btn-icon" style="--icon-url: url('<?php echo esc_url($icon_dir . 'map.svg'); ?>'); --icon-size: 24px; color: var(--wam-color-subtext); margin-top: 2px;"></span>
                                <div>
                                    <p class="page-cours__address-name" style="margin: 0;">WAM Dance Studio</p>
                                    <p class="page-cours__address-street mt-3xs" style="margin: 0;">202 rue Jean Jaurès à Villeneuve d'Ascq</p>
                                </div>
                            </div>

                        </div>
                    <?php endif; ?>

                </div>

                <?php if (has_post_thumbnail()): ?>
                    <!-- Image à la une -->
                    <div class="page-header__photo-outer">
                        <div class="page-header__photo page-header__photo--sm">
                            <?php the_post_thumbnail('wam-page-thumbnail', ['class' => 'page-header__photo-img']); ?>
                            <div class="page-header__photo-overlay"></div>
                        </div>
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