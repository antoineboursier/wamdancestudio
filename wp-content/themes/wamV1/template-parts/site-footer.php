<?php
/**
 * Template Part : Footer
 * Structure : barre séparateur → section CTA/logo/réseaux → menu footer → cours → stages → bas de page
 * Aligné sur le Figma node-id 125:828 (desktop) / 129:1372 (mobile)
 * @package wamv1
 */

$logo_url = get_template_directory_uri() . '/assets/images/wam_logo_hero.svg';
$fb_url = get_template_directory_uri() . '/assets/images/logo_facebook.svg';
$insta_url = get_template_directory_uri() . '/assets/images/logo_instagram.svg';
$contact_url = home_url('/contact/');

// Liens dynamiques vers les archives CPT
$cours_url = home_url('/cours-collectifs/');
$stages_url = home_url('/stages-workshop-ateliers/');
$newsletter_url = home_url('/newsletter/');
?>

<footer class="wam-footer" role="contentinfo">

    <?php /* ── Barre séparateur bg-800 ── */ ?>
    <div class="wam-footer__separator" aria-hidden="true"></div>

    <div class="wam-footer__container">

        <?php /* ── Section action : CTAs (gauche) | Réseaux (milieu) | Logo (droite) ── */ ?>
        <div id="footer-action" class="wam-footer__action">

            <?php /* CTAs — gauche */ ?>
            <div class="wam-footer__ctas">
                <a href="<?php echo esc_url($contact_url); ?>" class="btn-outlined">
                    <?php esc_html_e('Contact', 'wamv1'); ?>
                </a>
                <a href="<?php echo esc_url(home_url('/prof-wam/')); ?>" class="btn-outlined">
                    <?php esc_html_e('Nos professeur·es', 'wamv1'); ?>
                </a>
                <a href="<?php echo esc_url(home_url('/tarifs/')); ?>" class="btn-outlined">
                    <?php esc_html_e('Nos tarifs', 'wamv1'); ?>
                </a>
            </div>

            <?php /* Réseaux sociaux — milieu-droite, icônes colorées via CSS mask */ ?>
            <div class="wam-footer__socials">
                <a href="https://www.instagram.com/" class="wam-footer__social-link" target="_blank"
                    rel="noopener noreferrer"
                    aria-label="<?php esc_attr_e('WAM Dance Studio sur Instagram', 'wamv1'); ?>">
                    <span class="wam-footer__social-icon"
                        style="-webkit-mask-image: url('<?php echo esc_url($insta_url); ?>'); mask-image: url('<?php echo esc_url($insta_url); ?>')"
                        aria-hidden="true"></span>
                </a>
                <a href="https://www.facebook.com/" class="wam-footer__social-link" target="_blank"
                    rel="noopener noreferrer"
                    aria-label="<?php esc_attr_e('WAM Dance Studio sur Facebook', 'wamv1'); ?>">
                    <span class="wam-footer__social-icon"
                        style="-webkit-mask-image: url('<?php echo esc_url($fb_url); ?>'); mask-image: url('<?php echo esc_url($fb_url); ?>')"
                        aria-hidden="true"></span>
                </a>
            </div>

            <?php /* Logo — far right */ ?>
            <a href="<?php echo esc_url(home_url('/')); ?>" class="wam-footer__logo"
                aria-label="<?php esc_attr_e('WAM Dance Studio — Accueil', 'wamv1'); ?>">
                <img src="<?php echo esc_url($logo_url); ?>" alt="WAM Dance Studio" width="160" height="73">
            </a>
        </div>

        <?php /* ── Menu footer (3 colonnes de liens) ── */ ?>
        <?php if (has_nav_menu('footer')): ?>
            <nav id="footer-nav" class="wam-footer__nav" aria-label="<?php esc_attr_e('Menu footer', 'wamv1'); ?>">
                <?php
                wp_nav_menu(array(
                    'theme_location' => 'footer',
                    'container' => false,
                    'menu_class' => 'wam-footer__nav-list',
                    'depth' => 1,
                    'fallback_cb' => false,
                ));
                ?>
            </nav>
        <?php endif; ?>

        <div id="footer-cours" class="wam-footer__section">
            <p class="wam-footer__section-title">
                <?php esc_html_e('Les cours de danse collectifs :', 'wamv1'); ?>
            </p>

            <?php
            // Suppression temporaire du transient pour forcer la mise à jour
            delete_transient('wamv1_footer_cours_grouped');
            
            $grouped_cours = get_transient('wamv1_footer_cours_grouped');
            if (false === $grouped_cours) {
                $terms = get_terms(array(
                    'taxonomy' => 'cat_cours',
                    'hide_empty' => true,
                ));

                $grouped_cours = array();
                foreach ($terms as $term) {
                    $cours_query = new WP_Query(array(
                        'post_type' => 'cours',
                        'posts_per_page' => -1,
                        'orderby' => 'title',
                        'order' => 'ASC',
                        'post_status' => 'publish',
                        'tax_query' => array(
                            array(
                                'taxonomy' => 'cat_cours',
                                'field' => 'term_id',
                                'terms' => $term->term_id,
                            ),
                        ),
                    ));
                    if ($cours_query->have_posts()) {
                        $grouped_cours[] = array(
                            'term' => $term,
                            'posts' => $cours_query->posts,
                        );
                    }
                }

                // Récupération des cours SANS catégorie
                $uncategorized_query = new WP_Query(array(
                    'post_type' => 'cours',
                    'posts_per_page' => -1,
                    'orderby' => 'title',
                    'order' => 'ASC',
                    'post_status' => 'publish',
                    'tax_query' => array(
                        array(
                            'taxonomy' => 'cat_cours',
                            'operator' => 'NOT EXISTS', // Depuis WP 4.1, fonctionne pour tax_query
                        ),
                    ),
                ));

                if ($uncategorized_query->have_posts()) {
                    $grouped_cours[] = array(
                        'term' => (object) array('name' => __('Autres', 'wamv1')),
                        'posts' => $uncategorized_query->posts,
                    );
                }

                set_transient('wamv1_footer_cours_grouped', $grouped_cours, DAY_IN_SECONDS);
            }

            if (!empty($grouped_cours)):
                ?>
                <div class="wam-footer__columns">
                    <ul class="wam-footer__flat-list">
                        <?php foreach ($grouped_cours as $group): ?>
                            <li class="wam-footer__category-title-li" role="heading" aria-level="3">
                                <?php echo esc_html($group['term']->name); ?>
                            </li>
                            <?php foreach ($group['posts'] as $c_post): 
                                $c_subtitle = function_exists('get_field') ? get_field('sous_titre', $c_post->ID) : '';
                                $full_label = $c_post->post_title . ($c_subtitle ? ' ' . $c_subtitle : '');
                            ?>
                                <li class="wam-footer__course-li">
                                    <a href="<?php echo esc_url(get_permalink($c_post)); ?>" 
                                       class="wam-footer__simple-link"
                                       aria-label="<?php echo esc_attr($full_label); ?>">
                                        <?php echo esc_html($c_post->post_title); ?>
                                        <?php if ($c_subtitle): ?>
                                            <span class="wam-footer__simple-link-sub" aria-hidden="true"><?php echo esc_html($c_subtitle); ?></span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php else: ?>
                <p class="wam-footer__section-empty">
                    <?php esc_html_e('Aucun cours disponible pour le moment.', 'wamv1'); ?>
                    <a href="<?php echo esc_url($newsletter_url); ?>" class="color-yellow">
                        <?php esc_html_e('Inscrivez-vous à notre newsletter pour ne louper aucune info !', 'wamv1'); ?>
                    </a>
                </p>
            <?php endif; ?>

            <a href="<?php echo esc_url($cours_url); ?>" class="wam-footer__section-all">
                <?php esc_html_e('Tous les cours de danse collectifs', 'wamv1'); ?>
            </a>
        </div>

        <?php /* ── Stages (CPT "stage") ── */ ?>
        <div id="footer-stages" class="wam-footer__section">
            <p class="wam-footer__section-title">
                <?php esc_html_e('Les stages de danse :', 'wamv1'); ?>
            </p>

            <?php
            /*
             * Même mécanique transient que pour les cours.
             * Pour invalider : delete_transient('wamv1_footer_stages')
             */
            // Suppression temporaire du transient pour forcer la mise à jour immédiate
            delete_transient('wamv1_footer_stages');

            $stages_posts = get_transient('wamv1_footer_stages');
            if (false === $stages_posts) {
                $stages_query = new WP_Query(array(
                    'post_type' => 'stage',
                    'posts_per_page' => -1, // Affiche tout sans limite
                    'orderby' => 'title',
                    'order' => 'ASC',
                    'post_status' => 'publish',
                ));
                $stages_posts = $stages_query->posts;
                set_transient('wamv1_footer_stages', $stages_posts, DAY_IN_SECONDS);
            }

            if (!empty($stages_posts)):
                ?>
                <div class="wam-footer__columns">
                    <ul class="wam-footer__flat-list">
                        <?php foreach ($stages_posts as $stage_post):
                            $sous_titre = function_exists('get_field') ? get_field('sous_titre', $stage_post->ID) : '';
                            $full_label = $stage_post->post_title . ($sous_titre ? ' ' . $sous_titre : '');
                            ?>
                            <li class="wam-footer__course-li">
                                <a href="<?php echo esc_url(get_permalink($stage_post)); ?>" 
                                   class="wam-footer__simple-link"
                                   aria-label="<?php echo esc_attr($full_label); ?>">
                                    <?php echo esc_html($stage_post->post_title); ?>
                                    <?php if (!empty($sous_titre)): ?>
                                        <span class="wam-footer__simple-link-sub" aria-hidden="true"><?php echo esc_html($sous_titre); ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php
                wp_reset_postdata();
            else: ?>
                <p class="wam-footer__section-empty">
                    <?php esc_html_e('Aucun stage disponible pour le moment.', 'wamv1'); ?>
                    <a href="<?php echo esc_url($newsletter_url); ?>" class="color-yellow">
                        <?php esc_html_e('Inscrivez-vous à notre newsletter pour ne louper aucune info !', 'wamv1'); ?>
                    </a>
                </p>
            <?php endif; ?>

            <a href="<?php echo esc_url($stages_url); ?>" class="wam-footer__section-all">
                <?php esc_html_e('Tous les stages & workshop', 'wamv1'); ?>
            </a>
        </div>

        <?php /* ── Bas de page — mentions légales ── */ ?>
        <p id="footer-legal" class="wam-footer__legal">
            <?php 
            $legal_text = __("Conception : %s. Toutes les photographies, textes et informations sur ce site appartiennent à l'association WAM Dance Studio. Studio de danse situé à Villeneuve-d'Ascq, proche de Lille, Roubaix, Mouvaux, Wattrelos, Croix et Wasquehal (Hauts de France, Nord Pas-de-Calais)", 'wamv1');
            $link = '<a href="https://www.linkedin.com/in/antoine-boursier-uxui/" target="_blank" rel="external">Antoine Boursier</a>';
            echo sprintf($legal_text, $link);
            ?>
        </p>

    </div><!-- /.wam-footer__container -->
</footer>