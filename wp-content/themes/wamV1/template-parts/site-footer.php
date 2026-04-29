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
$icon_dir       = get_template_directory_uri() . '/assets/images/';
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
                <?php if (wam_url_instagram()) : ?>
                <a href="<?php echo esc_url(wam_url_instagram()); ?>" class="wam-footer__social-link" target="_blank"
                    rel="noopener noreferrer"
                    aria-label="<?php esc_attr_e('WAM Dance Studio sur Instagram', 'wamv1'); ?>">
                    <span class="wam-footer__social-icon"
                        style="-webkit-mask-image: url('<?php echo esc_url($icon_dir . 'logo_insta.svg'); ?>'); mask-image: url('<?php echo esc_url($icon_dir . 'logo_insta.svg'); ?>')"
                        aria-hidden="true"></span>
                </a>
                <?php endif; ?>
                <?php if (wam_url_facebook()) : ?>
                <a href="<?php echo esc_url(wam_url_facebook()); ?>" class="wam-footer__social-link" target="_blank"
                    rel="noopener noreferrer"
                    aria-label="<?php esc_attr_e('WAM Dance Studio sur Facebook', 'wamv1'); ?>">
                    <span class="wam-footer__social-icon"
                        style="-webkit-mask-image: url('<?php echo esc_url($icon_dir . 'logo_fb.svg'); ?>'); mask-image: url('<?php echo esc_url($icon_dir . 'logo_fb.svg'); ?>')"
                        aria-hidden="true"></span>
                </a>
                <?php endif; ?>
                <?php if (wam_url_tiktok()) : ?>
                <a href="<?php echo esc_url(wam_url_tiktok()); ?>" class="wam-footer__social-link" target="_blank"
                    rel="noopener noreferrer"
                    aria-label="<?php esc_attr_e('WAM Dance Studio sur TikTok', 'wamv1'); ?>">
                    <span class="wam-footer__social-icon"
                        style="-webkit-mask-image: url('<?php echo esc_url($icon_dir . 'logo_tiktok.svg'); ?>'); mask-image: url('<?php echo esc_url($icon_dir . 'logo_tiktok.svg'); ?>')"
                        aria-hidden="true"></span>
                </a>
                <?php endif; ?>
                <?php if (wam_url_linkedin()) : ?>
                <a href="<?php echo esc_url(wam_url_linkedin()); ?>" class="wam-footer__social-link" target="_blank"
                    rel="noopener noreferrer"
                    aria-label="<?php esc_attr_e('WAM Dance Studio sur LinkedIn', 'wamv1'); ?>">
                    <span class="wam-footer__social-icon"
                        style="-webkit-mask-image: url('<?php echo esc_url($icon_dir . 'logo_lkn.svg'); ?>'); mask-image: url('<?php echo esc_url($icon_dir . 'logo_lkn.svg'); ?>')"
                        aria-hidden="true"></span>
                </a>
                <?php endif; ?>
                <?php if (wam_url_youtube()) : ?>
                <a href="<?php echo esc_url(wam_url_youtube()); ?>" class="wam-footer__social-link" target="_blank"
                    rel="noopener noreferrer"
                    aria-label="<?php esc_attr_e('WAM Dance Studio sur YouTube', 'wamv1'); ?>">
                    <span class="wam-footer__social-icon"
                        style="-webkit-mask-image: url('<?php echo esc_url($icon_dir . 'logo_youtube.svg'); ?>'); mask-image: url('<?php echo esc_url($icon_dir . 'logo_youtube.svg'); ?>')"
                        aria-hidden="true"></span>
                </a>
                <?php endif; ?>
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
                    'container'      => false,
                    'menu_class'     => 'wam-footer__nav-list wam-footer__flat-list',
                    'depth'          => 1,
                    'fallback_cb'    => false,
                ));
                ?>
            </nav>
        <?php endif; ?>

        <div id="footer-cours" class="wam-footer__section">
            <p class="wam-footer__section-title mb-md">
                <?php esc_html_e('Les cours de danse collectifs :', 'wamv1'); ?>
            </p>

            <?php
            $grouped_cours = get_transient('wamv1_footer_cours_grouped_v2');
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

                set_transient('wamv1_footer_cours_grouped_v2', $grouped_cours, DAY_IN_SECONDS);
            }

            if (!empty($grouped_cours)):
                ?>
                <div class="wam-footer__columns">
                    <ul class="wam-footer__flat-list js-footer-accordion"
                        data-chevron-url="<?php echo esc_url(get_template_directory_uri() . '/assets/images/chevron_down.svg'); ?>">
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
            <p class="wam-footer__section-title mb-md">
                <?php esc_html_e('Les stages de danse :', 'wamv1'); ?>
            </p>

            <?php
            /*
             * Même mécanique transient que pour les cours.
             * Pour invalider : delete_transient('wamv1_footer_stages')
             */
            /*
             * Même mécanique transient que pour les cours.
             * Pour invalider : delete_transient('wamv1_footer_stages_v2')
             */
            $stages_posts = get_transient('wamv1_footer_stages_v3');
            if (false === $stages_posts) {
                $stages_query = new WP_Query(array(
                    'post_type' => 'stages',
                    'posts_per_page' => -1, // Affiche tout sans limite
                    'orderby' => 'meta_value',
                    'meta_key' => 'date_stage',
                    'order' => 'ASC',
                    'post_status' => 'publish',
                ));
                $stages_posts = $stages_query->posts;
                set_transient('wamv1_footer_stages_v3', $stages_posts, DAY_IN_SECONDS);
            }

            if (!empty($stages_posts)):
                ?>
                <div class="wam-footer__columns">
                    <ul class="wam-footer__flat-list js-footer-accordion"
                        data-chevron-url="<?php echo esc_url(get_template_directory_uri() . '/assets/images/chevron_down.svg'); ?>">
                        <?php foreach ($stages_posts as $stage_post):
                            $id = $stage_post->ID;
                            $sous_titre = function_exists('get_field') ? get_field('sous_titre', $id) : '';
                            $date_raw   = function_exists('get_field') ? get_field('date_stage', $id) : '';
                            
                            $date_formatted = '';
                            if ($date_raw) {
                                $date_obj = DateTime::createFromFormat('Ymd', $date_raw);
                                if ($date_obj) {
                                    $date_formatted = $date_obj->format('d/m/y');
                                }
                            }

                            // Format : Titre + subtitle + " - " + date
                            $display_title = $stage_post->post_title;
                            if ($sous_titre) $display_title .= ' <span class="wam-footer__simple-link-sub">' . esc_html($sous_titre) . '</span>';
                            if ($date_formatted) $display_title .= ' — ' . $date_formatted;
                            ?>
                            <li class="wam-footer__course-li">
                                <a href="<?php echo esc_url(get_permalink($stage_post)); ?>" 
                                   class="wam-footer__simple-link">
                                    <?php echo $display_title; ?>
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
        <div class="wam-footer__partners" style="margin-bottom: 1.5rem;">
            <a rel='nofollow' href='https://www.mariages.net' title='Mariages.net' target="_blank">
                <img alt='Mariages.net' src='https://www.mariages.net/images/sellos/label-partenaire--pp421197.png' style='border-width:0px; width: 120px; height: auto;' />
            </a>
        </div>

        <p id="footer-legal" class="wam-footer__legal">
            <?php 
            $legal_text = __("Conception : %s. Toutes les photographies, textes et informations sur ce site appartiennent à l'association WAM Dance Studio. Studio de danse situé à Villeneuve-d'Ascq, proche de Lille, Roubaix, Mouvaux, Wattrelos, Croix et Wasquehal (Hauts de France, Nord Pas-de-Calais)", 'wamv1');
            $link = '<a href="https://www.linkedin.com/in/antoine-boursier-uxui/" target="_blank" rel="external">Antoine Boursier</a>';
            echo sprintf($legal_text, $link);
            ?>
        </p>


        <?php /* Bouton Modifier flottant (uniquement pour admins/éditeurs) */ 
        if ( function_exists('wamv1_admin_floating_edit') ) wamv1_admin_floating_edit(); ?>

    </div><!-- /.wam-footer__container -->
</footer>