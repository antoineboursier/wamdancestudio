<?php
/**
 * Template : fiche profil d'un·e intervenant·e (single-wam_membre.php)
 *
 * Structure :
 *   1. Breadcrumb
 *   2. En-tête profil       — photo, nom, micro-description, réseaux sociaux
 *   3. Bio / Description     — contenu ACF ou contenu Gutenberg (fallback)
 *   4. [separator]
 *   5. Cours liés            — WP_Query par meta prof_cours
 *   6. Stages liés           — WP_Query par meta intervenant·e / stage_intervenant
 *
 * Champs ACF utilisés (groupe "Meta des profs" — group_69aacf7f60713) :
 *   user_prof                (user, return_format:array) — lien vers le compte WP du prof
 *   micro_description_prof   (text)                      — styles de danse pratiqués
 *   reseaux_sociaux_prof     (group)
 *     └ instagram_link_prof  (url)
 *     └ facebook_link_prof   (url)
 *     └ tiktok_link_prof     (url)
 *     └ linkedin_link_prof   (url)
 *   description_prof         (wysiwyg, required)         — bio, parcours, photos/vidéos
 *
 * Typographie (TOKENS.md) :
 *   H1                       → .title-sign-lg
 *   H2 (titres de sections)  → .title-norm-md
 *   H3 (titres de cartes)    → .text-md .fw-bold
 *   Micro-description        → .text-md
 *   Sous-titre carte         → .text-sm
 *   Meta (date, horaire)     → .text-xs
 *
 * Centrage :
 *   .page-layout__inner combiné avec .wam-container apporte
 *   max-width: var(--wam-max-screen), padding: 0 var(--wam-page-mx), margin: auto.
 *
 * @package wamv1
 */

get_header();
get_template_part('template-parts/site-header');
?>

<main id="primary" class="site-main">
    <div class="page-layout__inner wam-container">

        <?php while (have_posts()) : the_post(); ?>

            <?php
            /* ---- Champs ACF ---- */
            $has_acf = function_exists('get_field');

            /* Compte WP lié (pour requêter cours + stages) */
            $user_prof = $has_acf ? get_field('user_prof') : null;
            $user_id   = is_array($user_prof) ? ($user_prof['ID'] ?? null) : (is_object($user_prof) ? $user_prof->ID : null);

            /* Champs directs */
            $micro_desc  = $has_acf ? get_field('micro_description_prof') : '';
            $full_desc   = $has_acf ? get_field('description_prof')       : '';

            /* Réseaux sociaux — champs sous-groupe "reseaux_sociaux_prof" */
            $reseaux         = $has_acf ? (get_field('reseaux_sociaux_prof') ?: []) : [];
            $instagram_link  = $reseaux['instagram_link_prof'] ?? '';
            $facebook_link   = $reseaux['facebook_link_prof']  ?? '';
            $tiktok_link     = $reseaux['tiktok_link_prof']    ?? '';
            $linkedin_link   = $reseaux['linkedin_link_prof']  ?? '';

            $icon_dir = get_template_directory_uri() . '/assets/images/';
            ?>

            <!-- ============ 1. BREADCRUMB ============ -->
            <?php get_template_part('template-parts/breadcrumb', null, [
                'yoast' => true,
                'id'    => 'breadcrumb-membre',
                'full'  => true,
            ]); ?>

            <!-- ============ 2. EN-TÊTE PROFIL ============ -->
            <article id="post-<?php the_ID(); ?>" <?php post_class('prof-profile'); ?>>

                <div id="section-prof-header" class="page-header prof-header">

                    <div class="page-header__meta prof-header__meta">

                        <!-- Nom du prof — Mallia 46px -->
                        <h1 class="page-header__title title-sign-lg prof-header__title">
                            <?php the_title(); ?>
                        </h1>

                        <!-- Micro-description (styles de danse pratiqués) -->
                        <?php if ($micro_desc) : ?>
                            <p class="prof-header__micro-desc text-md">
                                <?php echo esc_html($micro_desc); ?>
                            </p>
                        <?php endif; ?>

                        <!-- Réseaux sociaux (champs du groupe reseaux_sociaux_prof) -->
                        <?php if ($instagram_link || $facebook_link || $tiktok_link || $linkedin_link) : ?>
                            <div class="prof-header__socials">

                                <?php if ($instagram_link) : ?>
                                    <a href="<?php echo esc_url($instagram_link); ?>"
                                       class="prof-social-link"
                                       target="_blank" rel="noopener noreferrer"
                                       aria-label="Instagram de <?php the_title(); ?>">
                                        <span class="btn-icon"
                                              style="--icon-url: url('<?php echo esc_url($icon_dir . 'logo_instagram.svg'); ?>');"
                                              aria-hidden="true"></span>
                                    </a>
                                <?php endif; ?>

                                <?php if ($facebook_link) : ?>
                                    <a href="<?php echo esc_url($facebook_link); ?>"
                                       class="prof-social-link"
                                       target="_blank" rel="noopener noreferrer"
                                       aria-label="Facebook de <?php the_title(); ?>">
                                        <span class="btn-icon"
                                              style="--icon-url: url('<?php echo esc_url($icon_dir . 'logo_facebook.svg'); ?>');"
                                              aria-hidden="true"></span>
                                    </a>
                                <?php endif; ?>

                                <?php if ($tiktok_link) : ?>
                                    <a href="<?php echo esc_url($tiktok_link); ?>"
                                       class="prof-social-link"
                                       target="_blank" rel="noopener noreferrer"
                                       aria-label="TikTok de <?php the_title(); ?>">
                                        <span class="btn-icon"
                                              style="--icon-url: url('<?php echo esc_url($icon_dir . 'logo_tiktok.svg'); ?>');"
                                              aria-hidden="true"></span>
                                    </a>
                                <?php endif; ?>

                                <?php if ($linkedin_link) : ?>
                                    <a href="<?php echo esc_url($linkedin_link); ?>"
                                       class="prof-social-link"
                                       target="_blank" rel="noopener noreferrer"
                                       aria-label="LinkedIn de <?php the_title(); ?>">
                                        <span class="btn-icon"
                                              style="--icon-url: url('<?php echo esc_url($icon_dir . 'logo_linkedin.svg'); ?>');"
                                              aria-hidden="true"></span>
                                    </a>
                                <?php endif; ?>

                            </div><!-- /prof-header__socials -->
                        <?php endif; ?>

                    </div><!-- /page-header__meta -->

                    <!-- Photo de profil (optionnelle — taille wam-portrait définie dans functions.php) -->
                    <?php if (has_post_thumbnail()) : ?>
                        <div class="page-header__photo-outer prof-header__photo-outer">
                            <div class="page-header__photo prof-header__photo">
                                <?php the_post_thumbnail('wam-portrait', ['class' => 'page-header__photo-img']); ?>
                                <div class="page-header__photo-overlay"></div>
                            </div>
                        </div>
                    <?php endif; ?>

                </div><!-- /section-prof-header -->

                <!-- ============ 3. BIO / DESCRIPTION ============ -->
                <!--
                    Priorité : champ ACF description_prof (wysiwyg — requis dans ACF).
                    Fallback  : contenu Gutenberg standard si description_prof vide.
                -->
                <div id="section-prof-content" class="page-content prof-content">
                    <div class="page-content__inner wam-prose">
                        <?php if ($full_desc) : ?>
                            <?php echo wp_kses_post($full_desc); ?>
                        <?php else : ?>
                            <?php the_content(); ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ============ 4. COURS ET STAGES LIÉS ============ -->
                <!--
                    Requiert que le champ ACF 'user_prof' soit rempli.
                    Cours  : meta 'prof_cours'                         — valeur sérialisée contenant user_id.
                    Stages : meta 'intervenant·e_stage_intervenant'    — valeur égale à user_id.
                -->
                <?php if ($user_id) : ?>

                    <?php get_template_part('template-parts/separator'); ?>

                    <?php
                    /* ---- Cours liés ---- */
                    $query_cours = new WP_Query([
                        'post_type'      => 'cours',
                        'posts_per_page' => -1,
                        'meta_query'     => [[
                            'key'     => 'prof_cours',
                            'value'   => '"' . $user_id . '"',
                            'compare' => 'LIKE',
                        ]],
                    ]);
                    ?>

                    <?php if ($query_cours->have_posts()) : ?>
                        <div class="prof-related-section">
                            <h2 class="prof-related-title title-norm-md">Ses cours</h2>
                            <div class="prof-related-grid">
                                <?php while ($query_cours->have_posts()) : $query_cours->the_post();
                                    $s_titre    = get_field('sous_titre');
                                    $jour_label = wamv1_get_day_label(get_field('jour_de_cours'));
                                    $h_deb      = get_field('heure_debut');
                                    $h_fin      = get_field('heure_de_fin');
                                ?>
                                    <a href="<?php the_permalink(); ?>" class="prof-related-card">
                                        <?php if (has_post_thumbnail()) : ?>
                                            <div class="prof-related-card__photo">
                                                <?php the_post_thumbnail('wam-card', ['class' => 'prof-related-card__img']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="prof-related-card__content">
                                            <h3 class="prof-related-card__title text-md fw-bold"><?php the_title(); ?></h3>
                                            <?php if ($s_titre) : ?>
                                                <p class="prof-related-card__subtitle text-sm"><?php echo esc_html($s_titre); ?></p>
                                            <?php endif; ?>
                                            <?php if ($jour_label || $h_deb) : ?>
                                                <p class="prof-related-card__meta text-xs">
                                                    <?php echo esc_html(trim($jour_label . ' ' . $h_deb . ($h_fin ? ' – ' . $h_fin : ''))); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                <?php endwhile; wp_reset_postdata(); ?>
                            </div>
                        </div>
                    <?php endif; /* /query_cours */ ?>

                    <?php
                    /* ---- Stages liés ---- */
                    /*
                     * La clé meta ACF pour un sous-champ de groupe est formée par :
                     * nom_du_groupe + '_' + nom_du_sous_champ.
                     * Ici : 'intervenant·e' (groupe, · = \xc2\xb7 UTF-8) + 'stage_intervenant'.
                     */
                    $query_stages = new WP_Query([
                        'post_type'      => 'stages',
                        'posts_per_page' => -1,
                        'meta_query'     => [[
                            'key'     => "intervenant\xc2\xb7e_stage_intervenant",
                            'value'   => $user_id,
                            'compare' => '=',
                        ]],
                    ]);
                    ?>

                    <?php if ($query_stages->have_posts()) : ?>
                        <div class="prof-related-section">
                            <h2 class="prof-related-title title-norm-md">Ses stages</h2>
                            <div class="prof-related-grid">
                                <?php while ($query_stages->have_posts()) : $query_stages->the_post();
                                    $s_titre = get_field('sous_titre');
                                    $date_s  = get_field('date_stage');
                                    $h_deb_s = get_field('heure_debut');
                                    $h_fin_s = get_field('heure_de_fin');
                                ?>
                                    <a href="<?php the_permalink(); ?>" class="prof-related-card">
                                        <?php if (has_post_thumbnail()) : ?>
                                            <div class="prof-related-card__photo">
                                                <?php the_post_thumbnail('wam-card', ['class' => 'prof-related-card__img']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="prof-related-card__content">
                                            <h3 class="prof-related-card__title text-md fw-bold"><?php the_title(); ?></h3>
                                            <?php if ($s_titre) : ?>
                                                <p class="prof-related-card__subtitle text-sm"><?php echo esc_html($s_titre); ?></p>
                                            <?php endif; ?>
                                            <?php if ($date_s || $h_deb_s) : ?>
                                                <p class="prof-related-card__meta text-xs">
                                                    <?php echo esc_html(trim($date_s . ' ' . $h_deb_s . ($h_fin_s ? ' – ' . $h_fin_s : ''))); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                <?php endwhile; wp_reset_postdata(); ?>
                            </div>
                        </div>
                    <?php endif; /* /query_stages */ ?>

                <?php endif; /* /user_id */ ?>

            </article>

        <?php endwhile; ?>

    </div><!-- /page-layout__inner.wam-container -->
</main>

<?php get_footer(); ?>
