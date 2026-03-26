<?php
/**
 * Template for single stages CPT
 *
 * Structure alignée sur single-cours.php :
 *   - page-layout__inner comme wrapper principal
 *   - cours-hero (infos à gauche, image à droite)
 *   - cours-info-card / __row / __cell pour l'encart date/lieu/tarif
 *   - cours-complet pour le badge "complet" sur l'image
 *   - cours-ctas pour le bouton de réservation
 *   - template-parts/separator + related-content identiques
 *
 * Champs ACF spécifiques stages (en plus des champs communs) :
 *   intervenant·e    (group)   — stage_intervenant_inout, stage_intervenant (user), stage_intervenant_out (text)
 *   mult_date_stage  (radio)   — "uniquedate" | "multidate"
 *   other_date       (relation)— autres sessions (WP_Post[])
 *   date_stage       (date)    — format d/m/Y retourné par ACF
 *   type_format      (radio)   — type_stage | type_atel | type_wshop
 *   tarifs           (group)   — tarif_1, tarif_2, tarif_3
 *
 * Variante enfant : terme slug 'danse-enfant' → titre Cholo Rhita (title-cool-lg), icônes colorées
 *
 * @package wamv1
 */

get_header();
get_template_part('template-parts/site-header');
?>

<main id="primary" class="site-main">
    <div class="page-layout__inner">

        <?php
        while (have_posts()) :
            the_post();

            /* ---- Variante enfant ---- */
            $is_enfant = wamv1_is_enfant_variant();

            /* ---- Badge type ---- */
            $has_acf         = function_exists('get_field');
            $type_format_val = $has_acf ? get_field('type_format') : 'type_stage';
            $type_map = [
                'type_stage' => ['label' => 'Stage',    'color_class' => 'color-yellow'],
                'type_atel'  => ['label' => 'Atelier',  'color_class' => 'color-green'],
                'type_wshop' => ['label' => 'Workshop', 'color_class' => 'color-pink'],
            ];
            $current_type = $type_map[$type_format_val] ?? $type_map['type_stage'];
            $badge_label  = $current_type['label'];
            $badge_color  = $current_type['color_class'];

            /* ---- URL listing stages ---- */
            $stages_listing_url = get_permalink(get_page_by_path('stages-workshop-ateliers')) ?: home_url('/');

            $current_id  = get_the_ID();

            /* ---- Champs ACF ---- */
            $sous_titre   = $has_acf ? get_field('sous_titre')         : '';
            $stage_groupe = $has_acf ? get_field('stage_groupe')       : '';
            $other_dates  = [];
            if ($stage_groupe) {
                $other_posts = get_posts([
                    'post_type'      => 'stages',
                    'posts_per_page' => -1,
                    'post__not_in'   => [$current_id],
                    'meta_query'     => [['key' => 'stage_groupe', 'value' => $stage_groupe]],
                    'orderby'        => 'meta_value',
                    'meta_key'       => 'date_stage',
                    'order'          => 'ASC',
                ]);
                $other_dates = $other_posts ?: [];
            }
            $is_multi = !empty($other_dates);
            $date_princ  = $has_acf ? get_field('date_stage')          : '';
            $complet     = $has_acf ? get_field('complete_cours')      : false;
            $description = $has_acf ? get_field('description')         : get_the_content();
            $tarifs_grp  = $has_acf ? get_field('tarifs')              : null;
            $info_comp   = $has_acf ? get_field('info_complementaire') : '';
            $heure_debut = $has_acf ? get_field('heure_debut')         : '';
            $heure_fin   = $has_acf ? get_field('heure_de_fin')        : '';

            /* ---- Tarifs ---- */
            $tarif_labels = [];
            if ($tarifs_grp) {
                foreach (['tarif_1', 'tarif_2', 'tarif_3'] as $key) {
                    if (!empty($tarifs_grp[$key])) $tarif_labels[] = $tarifs_grp[$key];
                }
            }

            /* ---- Intervenants avec lien vers profil wam_membre ---- */
            $prof_html_links   = [];
            $intervenant_group = $has_acf ? get_field('intervenant·e') : null;
            if ($intervenant_group) {
                $in_out = $intervenant_group['stage_intervenant_inout'] ?? 'false';
                if ($in_out === 'true') {
                    $u      = $intervenant_group['stage_intervenant'] ?? null;
                    $u_id   = is_array($u) ? ($u['ID'] ?? null) : (is_object($u) ? $u->ID : null);
                    $u_name = is_array($u) ? ($u['display_name'] ?? '') : (is_object($u) ? $u->display_name : '');
                    if ($u_id) {
                        $member_posts = get_posts([
                            'post_type'      => 'wam_membre',
                            'meta_query'     => [['key' => 'user_prof', 'value' => $u_id]],
                            'posts_per_page' => 1,
                        ]);
                        $prof_html_links[] = $member_posts
                            ? '<a href="' . get_permalink($member_posts[0]->ID) . '" class="prof-link">' . esc_html($u_name) . '</a>'
                            : esc_html($u_name);
                    }
                } else {
                    $u_name = $intervenant_group['stage_intervenant_out'] ?? '';
                    if ($u_name) $prof_html_links[] = esc_html($u_name);
                }
            }

            /* ---- Parsing date principale ---- */
            $p_obj = $date_princ ? DateTime::createFromFormat('d/m/Y', $date_princ) : null;

            /* ---- Couleurs icônes info-card ---- */
            $ic = $is_enfant ? [
                'calendar'  => 'var(--wam-color-green)',
                'map'       => 'var(--wam-color-orange)',
                'piggybank' => 'var(--wam-color-pink)',
                'thumbs'    => 'var(--wam-color-yellow)',
            ] : [
                'calendar'  => 'var(--wam-color-icon-light)',
                'map'       => 'var(--wam-color-icon-light)',
                'piggybank' => 'var(--wam-color-icon-light)',
                'thumbs'    => 'var(--wam-color-icon-light)',
            ];

            $icon_dir   = get_template_directory_uri() . '/assets/images/';
            $has_photo  = has_post_thumbnail();
            $has_sidebar = ($has_photo || $complet);

            /* ---- Vidéos ---- */
            $videos = [];
            for ($i = 1; $i <= 3; $i++) {
                $v = $has_acf ? get_field("video_$i") : '';
                if ($v) $videos[] = $v;
            }
        ?>

        <!-- Breadcrumb : Accueil > Stages > [Titre du stage] -->
        <?php get_template_part('template-parts/breadcrumb', null, [
            'id'   => 'breadcrumb-stage',
            'full' => true,
        ]); ?>

        <!-- ============ HERO : Infos + Image ============ -->
        <div class="cours-hero <?php echo !$has_sidebar ? 'cours-hero--no-photo' : ''; ?>">

            <!-- Colonne gauche : badge, titre, infos, description, CTA -->
            <div class="cours-hero__infos">

                <div class="cours-hero__heading">

                    <!-- Badge type (STAGE / ATELIER / WORKSHOP) -->
                    <span class="stage-badge-type text-xs fw-bold <?php echo $badge_color; ?>"><?php echo esc_html($badge_label); ?></span>

                    <!-- Titre — variante enfant = Cholo Rhita, adulte = Mallia -->
                    <h1 class="cours-hero__title has-accent-yellow-color <?php echo $is_enfant ? 'title-cool-lg cours-hero__title--enfant' : 'title-sign-lg'; ?>">
                        <?php the_title(); ?>
                    </h1>

                    <?php if ($sous_titre) : ?>
                        <p class="cours-hero__subtitle text-lg"><?php echo esc_html($sous_titre); ?></p>
                    <?php endif; ?>
                </div>

                <?php if ($prof_html_links) : ?>
                    <p class="cours-hero__profs text-sm">
                        avec <?php echo implode(' et ', $prof_html_links); ?>
                    </p>
                <?php endif; ?>

                <!-- Info card -->
                <div class="cours-info-card">

                    <!-- Date + Horaire -->
                    <?php if ($p_obj) : ?>
                        <div class="cours-info-card__row cours-info-card__row--top">
                            <span class="btn-icon" style="--icon-url: url('<?php echo $icon_dir; ?>calendar.svg'); --icon-size: 24px; color: <?php echo $ic['calendar']; ?>;"></span>
                            <div class="cours-info-card__cell">
                                <div class="stage-date-display">
                                    <div class="stage-date-square <?php echo $is_enfant ? 'stage-date-square--enfant' : ''; ?>">
                                        <span class="date-name text-xs color-subtext"><?php echo date_i18n('l', $p_obj->getTimestamp()); ?></span>
                                        <span class="date-number title-norm-lg <?php echo $is_enfant ? 'color-green' : 'color-yellow'; ?>"><?php echo $p_obj->format('d'); ?></span>
                                        <span class="date-month text-md <?php echo $is_enfant ? 'color-green' : 'color-yellow'; ?>"><?php echo date_i18n('F', $p_obj->getTimestamp()); ?></span>
                                        <span class="date-year text-xs color-subtext mt-3xs"><?php echo $p_obj->format('Y'); ?></span>
                                    </div>
                                    <?php if ($heure_debut) : ?>
                                        <p class="stage-time-display text-lg fw-bold">
                                            <?php echo esc_html($heure_debut . ($heure_fin ? ' – ' . $heure_fin : '')); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Multi-dates toggle -->
                    <?php if ($is_multi && !empty($other_dates)) : ?>
                        <div class="stage-dates-toggle-wrap">
                            <button type="button" class="btn-toggle-dates" id="toggle-dates-list" aria-expanded="false">
                                <span class="btn-label"></span>
                                <span class="btn-icon btn-icon--xs" style="--icon-url: url('<?php echo $icon_dir; ?>chevron_down.svg');"></span>
                            </button>
                        </div>
                        <div class="stage-dates-dropdown" id="dates-list" hidden>
                            <?php
                            /* Tri : dates disponibles d'abord, complètes en dernier */
                            usort($other_dates, function ($a, $b) {
                                $a_c = (bool) get_field('complete_cours', is_object($a) ? $a->ID : $a);
                                $b_c = (bool) get_field('complete_cours', is_object($b) ? $b->ID : $b);
                                return ($a_c ? 1 : 0) - ($b_c ? 1 : 0);
                            });
                            ?>
                            <div class="stage-dates-grid <?php echo $is_enfant ? 'stage-dates-grid--enfant' : ''; ?>">
                                <?php foreach ($other_dates as $linked_post) :
                                    $l_id      = is_object($linked_post) ? $linked_post->ID : $linked_post;
                                    $l_date    = get_field('date_stage', $l_id);
                                    $l_h_deb   = get_field('heure_debut', $l_id);
                                    $l_h_fin   = get_field('heure_de_fin', $l_id);
                                    $l_complet = get_field('complete_cours', $l_id);
                                    $l_obj     = $l_date ? DateTime::createFromFormat('d/m/Y', $l_date) : null;
                                    if (!$l_obj) continue;
                                ?>
                                    <a href="<?php echo get_permalink($l_id); ?>"
                                       class="stage-mini-date-card <?php echo $is_enfant ? 'stage-mini-date-card--enfant' : ''; ?> <?php echo $l_complet ? 'is-complet' : ''; ?>">
                                        <p class="date-name text-xs color-subtext"><?php echo date_i18n('l', $l_obj->getTimestamp()); ?></p>
                                        <p class="mini-date-num title-norm-md <?php echo $is_enfant ? 'color-green' : 'color-yellow'; ?>"><?php echo $l_obj->format('d'); ?></p>
                                        <p class="mini-date-month text-md <?php echo $is_enfant ? 'color-green' : 'color-yellow'; ?>"><?php echo date_i18n('F', $l_obj->getTimestamp()); ?></p>
                                        <p class="text-xs color-subtext mt-3xs"><?php echo $l_obj->format('Y'); ?></p>
                                        <?php if ($l_complet) : ?>
                                            <span class="mini-badge text-md color-orange mt-2xs">Complet</span>
                                        <?php else : ?>
                                            <p class="mini-date-time text-md fw-bold color-text"><?php echo esc_html($l_h_deb . ($l_h_fin ? '–' . $l_h_fin : '')); ?></p>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Lieu -->
                    <div class="cours-info-card__row">
                        <span class="btn-icon" style="--icon-url: url('<?php echo $icon_dir; ?>map.svg'); --icon-size: 24px; color: <?php echo $ic['map']; ?>;"></span>
                        <div class="cours-info-card__cell">
                            <p class="cours-info-card__lieu text-md">WAM Dance Studio</p>
                            <p class="cours-info-card__adresse text-sm">202 rue Jean Jaurès à Villeneuve d'Ascq</p>
                        </div>
                    </div>

                    <!-- Tarifs -->
                    <?php if ($tarif_labels) : ?>
                        <div class="cours-info-card__row">
                            <span class="btn-icon" style="--icon-url: url('<?php echo $icon_dir; ?>piggy-bank.svg'); --icon-size: 24px; color: <?php echo $ic['piggybank']; ?>;"></span>
                            <div class="cours-info-card__cell">
                                <?php foreach ($tarif_labels as $label) : ?>
                                    <p class="cours-info-card__tarif text-md"><?php echo esc_html($label); ?></p>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Info complémentaire -->
                    <?php if ($info_comp) : ?>
                        <div class="cours-info-card__row">
                            <span class="btn-icon" style="--icon-url: url('<?php echo $icon_dir; ?>thumbs-up.svg'); --icon-size: 24px; color: <?php echo $ic['thumbs']; ?>;"></span>
                            <p class="cours-info-card__info text-md"><?php echo esc_html($info_comp); ?></p>
                        </div>
                    <?php endif; ?>

                </div><!-- /cours-info-card -->

                <!-- Description courte (tronquée à 60 mots) -->
                <?php if ($description) : ?>
                    <div class="cours-description wam-prose">
                        <?php echo wp_kses_post(wpautop(wp_trim_words($description, 60))); ?>
                    </div>
                <?php endif; ?>

                <!-- CTA réservation -->
                <div class="cours-ctas">
                    <?php if ($complet) : ?>
                        <div class="btn-primary btn-inscription btn-inscription--disabled">
                            <span class="btn-inscription__label">Stage complet</span>
                        </div>
                    <?php else : ?>
                        <a href="#resa" class="btn-primary btn-inscription" id="btn-resa">
                            <span class="btn-inscription__label">Réserver ce stage !</span>
                            <span class="btn-icon btn-icon--sm" style="--icon-url: url('<?php echo $icon_dir; ?>chevron-right.svg');"></span>
                        </a>
                    <?php endif; ?>
                </div>

            </div><!-- /cours-hero__infos -->

            <!-- Colonne droite : image à la une + badge complet -->
            <?php if ($has_sidebar) : ?>
                <div class="cours-hero__photo">

                    <?php if ($has_photo) : ?>
                        <?php the_post_thumbnail('wam-stage-portrait', [
                            'class'          => 'cours-hero__photo-img',
                            'data-no-overlay' => 'true',
                        ]); ?>
                        <div class="cours-hero__photo-overlay"></div>
                    <?php else : ?>
                        <div class="stage-placeholder-img" aria-hidden="true"></div>
                    <?php endif; ?>

                    <?php if ($complet) : ?>
                        <!-- Badge cours complet — réutilise le même composant que single-cours.php -->
                        <div class="cours-complet">
                            <img src="<?php echo $icon_dir; ?>sad-emoji.svg" 
                                 width="40" height="40" alt="" aria-hidden="true">
                            <div class="cours-complet__body">
                                <p class="cours-complet__title">Stage complet</p>
                                <p class="cours-complet__text">Malheureusement, ce stage est déjà rempli.</p>
                                <a href="<?php echo esc_url($stages_listing_url); ?>" class="cours-complet__link">
                                    <span>Voir tous les stages</span>
                                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                                        <path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                </div><!-- /cours-hero__photo -->
            <?php endif; ?>

        </div><!-- /cours-hero -->

        <?php endwhile; ?>

        <!-- Séparateur -->
        <?php get_template_part('template-parts/separator'); ?>

        <!-- Description complète si > 60 mots -->
        <?php if (!empty($description) && str_word_count(strip_tags($description)) > 60) : ?>
            <div class="stage-full-desc-section">
                <div class="wam-prose">
                    <?php echo wp_kses_post(wpautop($description)); ?>
                </div>
            </div>
            <?php get_template_part('template-parts/separator'); ?>
        <?php endif; ?>

        <!-- Vidéos -->
        <?php if (!empty($videos)) : ?>
            <div id="section-videos" class="cours-videos">
                <?php foreach ($videos as $video_url) :
                    $embed = wp_oembed_get(esc_url($video_url), ['width' => 1200]);
                    if ($embed) : ?>
                        <div class="cours-video"><?php echo $embed; ?></div>
                    <?php endif;
                endforeach; ?>
            </div>
        <?php endif; ?>

        <?php get_template_part('template-parts/related-content'); ?>

    </div>
</main>

<?php get_footer(); ?>
