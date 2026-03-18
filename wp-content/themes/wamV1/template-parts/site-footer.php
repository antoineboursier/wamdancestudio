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
$contact_url = get_permalink(get_page_by_path('contact'));

// Liens dynamiques vers les archives CPT
$cours_url = get_post_type_archive_link('cours') ?: home_url('/cours-collectifs/');
$stages_url = get_post_type_archive_link('stage') ?: home_url('/stages-workshop-ateliers/');
?>

<footer class="wam-footer" role="contentinfo">

    <?php /* ── Barre séparateur bg-800 ── */ ?>
    <div class="wam-footer__separator" aria-hidden="true"></div>

    <div class="wam-footer__container">

        <?php /* ── Section action : CTAs (gauche) | Réseaux (milieu) | Logo (droite) ── */ ?>
        <div id="footer-action" class="wam-footer__action">

            <?php /* CTAs — gauche */ ?>
            <div class="wam-footer__ctas">
                <a href="<?php echo esc_url($contact_url ?: home_url('/contact/')); ?>" class="btn-outlined">
                    <?php esc_html_e('Contact', 'wamv1'); ?>
                </a>
                <a href="<?php echo esc_url(home_url('/nos-professeurs/')); ?>" class="btn-outlined">
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

        <?php /* ── Cours collectifs (CPT "cours") ── */ ?>
        <div id="footer-cours" class="wam-footer__section">
            <p class="wam-footer__section-title">
                <?php esc_html_e('Les cours de danse collectifs :', 'wamv1'); ?>
            </p>

            <?php
            /*
             * Cache transient : évite une WP_Query à chaque chargement de page.
             * Durée : DAY_IN_SECONDS (86400s).
             * Pour invalider immédiatement après mise à jour :
             *   delete_transient('wamv1_footer_cours')
             */
            $cours_posts = get_transient('wamv1_footer_cours');
            if (false === $cours_posts) {
                $cours_query = new WP_Query(array(
                    'post_type' => 'cours',
                    'posts_per_page' => 9,
                    'orderby' => 'title',
                    'order' => 'ASC',
                    'post_status' => 'publish',
                    'class' => 'ml-40'
                ));
                $cours_posts = $cours_query->posts;
                set_transient('wamv1_footer_cours', $cours_posts, DAY_IN_SECONDS);
            }

            if (!empty($cours_posts)):
                $per_col = ceil(count($cours_posts) / 3);
                $columns = array_chunk($cours_posts, max($per_col, 1));
                ?>
                <div class="wam-footer__section-cols">
                    <?php foreach ($columns as $col): ?>
                        <ul class="wam-footer__section-col">
                            <?php foreach ($col as $cours_post):
                                $sous_titre = function_exists('get_field') ? get_field('sous_titre', $cours_post->ID) : '';
                                ?>
                                <li class="ml-10">
                                    <a href="<?php echo esc_url(get_permalink($cours_post)); ?>" class="wam-footer__section-link">
                                        <span class="wam-footer__link-title"><?php echo esc_html($cours_post->post_title); ?></span>
                                        <?php if (!empty($sous_titre)): ?>
                                            <span class="wam-footer__link-sub"><?php echo esc_html($sous_titre); ?></span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endforeach; ?>
                </div>
                <?php
            else: ?>
                <p class="wam-footer__section-empty">
                    <?php esc_html_e('Aucun cours disponible pour le moment.', 'wamv1'); ?>
                </p>
            <?php endif; ?>

            <a href="<?php echo esc_url($cours_url); ?>" class="wam-footer__section-all">
                <?php esc_html_e('Tous les cours de danse', 'wamv1'); ?>
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
            $stages_posts = get_transient('wamv1_footer_stages');
            if (false === $stages_posts) {
                $stages_query = new WP_Query(array(
                    'post_type' => 'stage',
                    'posts_per_page' => 9,
                    'orderby' => 'title',
                    'order' => 'ASC',
                    'post_status' => 'publish',
                ));
                $stages_posts = $stages_query->posts;
                set_transient('wamv1_footer_stages', $stages_posts, DAY_IN_SECONDS);
            }

            if (!empty($stages_posts)):
                $per_col = ceil(count($stages_posts) / 3);
                $columns = array_chunk($stages_posts, max($per_col, 1));
                ?>
                <div class="wam-footer__section-cols">
                    <?php foreach ($columns as $col): ?>
                        <ul class="wam-footer__section-col">
                            <?php foreach ($col as $stage_post):
                                $sous_titre = function_exists('get_field') ? get_field('sous_titre', $stage_post->ID) : '';
                                ?>
                                <li>
                                    <a href="<?php echo esc_url(get_permalink($stage_post)); ?>" class="wam-footer__section-link">
                                        <span class="wam-footer__link-title"><?php echo esc_html($stage_post->post_title); ?></span>
                                        <?php if (!empty($sous_titre)): ?>
                                            <span class="wam-footer__link-sub"><?php echo esc_html($sous_titre); ?></span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endforeach; ?>
                </div>
                <?php
                wp_reset_postdata();
            else: ?>
                <p class="wam-footer__section-empty">
                    <?php esc_html_e('Aucun stage disponible pour le moment.', 'wamv1'); ?>
                </p>
            <?php endif; ?>

            <a href="<?php echo esc_url($stages_url); ?>" class="wam-footer__section-all">
                <?php esc_html_e('Tous les stages de danse', 'wamv1'); ?>
            </a>
        </div>

        <?php /* ── Bas de page — mentions légales ── */ ?>
        <p id="footer-legal" class="wam-footer__legal text-wam-text font-outfit text-wam-sm">
            <?php esc_html_e("Conception : Antoine Boursier. Toutes les photographies, textes et informations sur ce site appartiennent à l'association WAM Dance Studio. Studio de danse situé à Villeneuve-d'Ascq, proche de Lille, Roubaix, Mouvaux, Wattrelos, Croix et Wasquehal (Hauts de France, Nord Pas-de-Calais)", 'wamv1'); ?>
        </p>

    </div><!-- /.wam-footer__container -->
</footer>