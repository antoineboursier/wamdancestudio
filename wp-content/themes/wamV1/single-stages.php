<?php
/**
 * Template for single stages CPT (Figma Refactor)
 * 
 * Layout: Flex 2 Columns (Hero)
 * Left: Badge, Title, Teacher, Info Card, Description, CTA
 * Right: Featured Image, Complet Banner Overlay
 *
 * @package wamv1
 */

get_header();
get_template_part('template-parts/site-header');
?>

<main id="primary" class="site-main site-main--stage">
    <?php
    while (have_posts()) :
        the_post();

        // --- Détection Variante Enfant ---
        $cat_cours_terms = get_the_terms(get_the_ID(), 'cat_cours');
        $is_enfant = false;
        $stage_type_label = ''; // STAGE, ATELIER, WORKSHOP
        if ($cat_cours_terms && !is_wp_error($cat_cours_terms)) {
            foreach ($cat_cours_terms as $term) {
                if ($term->slug === 'danse-enfant') {
                    $is_enfant = true;
                } else {
                    $badge_slugs = ['stage', 'atelier', 'workshop'];
                    if (in_array($term->slug, $badge_slugs) || strpos($term->slug, 'stage') !== false || strpos($term->slug, 'atelier') !== false) {
                        $stage_type_label = strtoupper($term->name);
                    }
                }
            }
        }
        if (!$stage_type_label) {
            $stage_type_label = 'STAGE';
        }

        // URL de la page listing stages (utilisée dans le breadcrumb et le badge complet)
        $stages_listing_url = get_permalink(get_page_by_path('stages')) ?: home_url('/');

        // --- Récupération ACF ---
        $has_acf = function_exists('get_field');
        $sous_titre = $has_acf ? get_field('sous_titre') : '';
        
        // Intervenant (Groupe "intervenant·e")
        $intervenant_group = $has_acf ? get_field('intervenant·e') : null;
        $prof_names = [];
        if ($intervenant_group) {
            $in_out = $intervenant_group['stage_intervenant_inout'] ?? 'false';
            if ($in_out === 'true') {
                $u = $intervenant_group['stage_intervenant'] ?? null;
                $u_name = is_array($u) ? ($u['display_name'] ?? '') : (is_object($u) ? $u->display_name : '');
                if ($u_name) $prof_names[] = $u_name;
            } else {
                $u_name = $intervenant_group['stage_intervenant_out'] ?? '';
                if ($u_name) $prof_names[] = $u_name;
            }
        }

        // Dates & Mode
        $multi_mode = $has_acf ? get_field('mult_date_stage') : 'uniquedate';
        $is_multi = ($multi_mode === 'multidate');
        $other_dates = $has_acf ? get_field('other_date') : []; // Relationship
        $date_principale = $has_acf ? get_field('date_stage') : ''; // Format d/m/Y
        
        $complet = $has_acf ? get_field('complete_cours') : false;
        $description = $has_acf ? get_field('description') : get_the_content();
        
        // Tarifs (Groupe "tarifs")
        $tarifs_group = $has_acf ? get_field('tarifs') : null;
        $tarif_labels = [];
        if ($tarifs_group) {
            if (!empty($tarifs_group['tarif_1'])) $tarif_labels[] = $tarifs_group['tarif_1'];
            if (!empty($tarifs_group['tarif_2'])) $tarif_labels[] = $tarifs_group['tarif_2'];
            if (!empty($tarifs_group['tarif_3'])) $tarif_labels[] = $tarifs_group['tarif_3'];
        }
        
        $info_comp = $has_acf ? get_field('info_complementaire') : '';
        
        // Mono-date / Heures
        $heure_debut = $has_acf ? get_field('heure_debut') : '';
        $heure_fin = $has_acf ? get_field('heure_de_fin') : '';

        // Tarifs
        $tarif_label = is_string($tarif_obj) ? $tarif_obj : (($tarif_obj instanceof WP_Post) ? $tarif_obj->post_title : '');

        // Colors matching single-cours.php logic
        $ic = $is_enfant ? [
            'calendar'  => 'var(--wam-color-green)',
            'map'       => 'var(--wam-color-orange)',
            'piggybank' => 'var(--wam-color-pink)',
            'thumbs'    => 'var(--wam-color-yellow)',
        ] : [
            'calendar'  => 'var(--wam-color-subtext)',
            'map'       => 'var(--wam-color-subtext)',
            'piggybank' => 'var(--wam-color-subtext)',
            'thumbs'    => 'var(--wam-color-subtext)',
        ];

        $icon_dir = get_template_directory_uri() . '/assets/images/';
        $current_id = get_the_ID();

        // Vidéos (Groupe "Vidéos" avec name "")
        // On tente en direct d'abord, puis dans le groupe si besoin
        $videos = [];
        for ($i = 1; $i <= 3; $i++) {
            $v = get_field("video_$i");
            if ($v) $videos[] = $v;
        }
    ?>

    <div class="stage-container">
        <!-- Breadcrumb -->
        <nav class="stage-breadcrumb" aria-label="Fil d'Ariane">
            <a href="<?php echo home_url('/'); ?>">Accueil</a> &nbsp;&gt;&nbsp; 
            <a href="<?php echo esc_url($stages_listing_url); ?>">Stages</a> &nbsp;&gt;&nbsp;
            <span><?php the_title(); ?></span>
        </nav>

        <!-- ============ HERO STAGE ============ -->
        <div class="stage-hero <?php echo $is_enfant ? 'stage-hero--enfant' : ''; ?>">
            
            <!-- Colonne Gauche : Infos -->
            <div class="stage-hero__left">
                
                <?php if ($stage_type_label) : ?>
                    <span class="stage-badge-type"><?php echo esc_html($stage_type_label); ?></span>
                <?php endif; ?>

                <h1 class="stage-hero-title <?php echo $is_enfant ? 'is-style-title-cool-lg' : 'is-style-title-sign-lg'; ?>">
                    <?php the_title(); ?>
                </h1>

                <?php if ($sous_titre) : ?>
                    <p class="stage-hero-subtitle text-lg"><?php echo esc_html($sous_titre); ?></p>
                <?php endif; ?>

                <?php if ($prof_names) : ?>
                    <p class="stage-hero-prof text-sm color-disabled">avec <?php echo implode(' & ', $prof_names); ?></p>
                <?php endif; ?>

                <!-- INFO CARD (Dark Gradient) -->
                <div class="stage-hero-card">
                    
                    <!-- Date & Time Section -->
                    <div class="stage-card-row stage-card-row--calendar">
                        <span class="btn-icon" style="--icon-url: url('<?php echo $icon_dir; ?>calendar.svg'); --icon-size: 24px; color: <?php echo $ic['calendar']; ?>;"></span>
                        
                        <div class="stage-date-display">
                            <?php 
                            // Date principale (format d/m/Y)
                            $p_obj = null;
                            if ($date_principale) {
                                $p_obj = DateTime::createFromFormat('d/m/Y', $date_principale);
                            }
                            
                            if ($p_obj) : ?>
                                <div class="stage-date-square">
                                    <span class="date-name"><?php echo date_i18n('l', $p_obj->getTimestamp()); ?></span>
                                    <span class="date-number"><?php echo $p_obj->format('d'); ?></span>
                                    <span class="date-month"><?php echo date_i18n('F', $p_obj->getTimestamp()); ?></span>
                                    <span class="date-year"><?php echo $p_obj->format('Y'); ?></span>
                                </div>
                            <?php endif; ?>

                            <div class="stage-time-display text-lg bold">
                                <?php echo $heure_debut . ($heure_fin ? ' - ' . $heure_fin : ''); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Multi-dates Toggle (Relationship based) -->
                    <?php if ($is_multi && !empty($other_dates)) : ?>
                        <div class="stage-dates-toggle-wrap">
                            <button type="button" class="btn-toggle-dates" id="toggle-dates-list" aria-expanded="false">
                                <span class="btn-label"></span>
                                <span class="btn-icon btn-icon--xs" style="--icon-url: url('<?php echo $icon_dir; ?>chevron_down.svg');"></span>
                            </button>
                        </div>
                        
                        <div class="stage-dates-dropdown" id="dates-list" hidden>
                            <div class="stage-dates-grid">
                                <?php foreach ($other_dates as $linked_post) : 
                                    $l_id = is_object($linked_post) ? $linked_post->ID : $linked_post;
                                    $l_date = get_field('date_stage', $l_id);
                                    $l_h_deb = get_field('heure_debut', $l_id);
                                    $l_h_fin = get_field('heure_de_fin', $l_id);
                                    $l_complet = get_field('complete_cours', $l_id);
                                    $l_obj = DateTime::createFromFormat('d/m/Y', $l_date);
                                    if (!$l_obj) continue;
                                ?>
                                    <a href="<?php echo get_permalink($l_id); ?>" class="stage-mini-date-card <?php echo $l_complet ? 'is-complet' : ''; ?>">
                                        <p class="text-sm"><?php echo date_i18n('l', $l_obj->getTimestamp()); ?></p>
                                        <p class="mini-date-num"><?php echo $l_obj->format('d'); ?></p>
                                        <p class="mini-date-month"><?php echo date_i18n('F', $l_obj->getTimestamp()); ?></p>
                                        <p class="text-xs"><?php echo $l_obj->format('Y'); ?></p>
                                        <p class="mini-date-time"><?php echo $l_h_deb; ?><?php echo $l_h_fin ? '-'.$l_h_fin : ''; ?></p>
                                        <?php if ($l_complet) : ?><span class="mini-badge">COMPLET</span><?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Localisation -->
                    <div class="stage-card-row">
                        <span class="btn-icon" style="--icon-url: url('<?php echo $icon_dir; ?>map.svg'); --icon-size: 24px; color: <?php echo $ic['map']; ?>;"></span>
                        <div class="stage-card-cell">
                            <p class="text-md bold">WAM Dance Studio</p>
                            <p class="text-sm color-subtext">202 rue Jean Jaurès à Villeneuve d'Ascq</p>
                        </div>
                    </div>

                    <!-- Tarifs -->
                    <?php if (!empty($tarif_labels)) : ?>
                        <div class="stage-card-row">
                            <span class="btn-icon" style="--icon-url: url('<?php echo $icon_dir; ?>piggy-bank.svg'); --icon-size: 24px; color: <?php echo $ic['piggybank']; ?>;"></span>
                            <div class="stage-card-cell">
                                <?php foreach ($tarif_labels as $label) : ?>
                                    <p class="text-md bold"><?php echo esc_html($label); ?></p>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Info tenue -->
                    <?php if ($info_comp) : ?>
                        <div class="stage-card-row">
                            <span class="btn-icon" style="--icon-url: url('<?php echo $icon_dir; ?>thumbs-up.svg'); --icon-size: 24px; color: <?php echo $ic['thumbs']; ?>;"></span>
                            <div class="stage-card-cell font-bold text-sm">
                                <?php echo esc_html($info_comp); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                </div><!-- /stage-hero-card -->

                <!-- Short Description -->
                <?php if ($description) : ?>
                    <div class="stage-hero-desc text-md wam-prose">
                        <?php echo wp_kses_post(wpautop(wp_trim_words($description, 60))); ?>
                    </div>
                <?php endif; ?>

                <!-- Booking Button -->
                <div class="stage-cta-wrap">
                    <a href="#resa" class="btn-stage-resa">
                        <span>Réserver ce stage !</span>
                        <span class="btn-icon btn-icon--sm" style="--icon-url: url('<?php echo $icon_dir; ?>chevron-right.svg');"></span>
                    </a>
                </div>

            </div><!-- /left -->

            <!-- Colonne Droite : Image + Banner -->
            <div class="stage-hero__right">
                <div class="stage-featured-image-wrap">
                    <?php if (has_post_thumbnail()) : ?>
                        <?php the_post_thumbnail('large', ['class' => 'stage-featured-img']); ?>
                    <?php else : ?>
                        <div class="stage-placeholder-img"></div>
                    <?php endif; ?>

                    <?php if ($complet) : ?>
                        <!-- COMPLET BANNER (Overlay) -->
                        <div class="stage-banner-complet">
                            <div class="complet-emoji-wrap">
                                <span class="complet-emoji">😥</span>
                            </div>
                            <div class="complet-content">
                                <p class="complet-title">Stage complet</p>
                                <p class="complet-text">Malheureusement, ce cours est déjà rempli.</p>
                                <a href="<?php echo esc_url($stages_listing_url); ?>" class="btn-complet-archive">
                                    <span>Voir tous les cours de danse</span>
                                    <span class="btn-icon" style="--icon-url: url('<?php echo $icon_dir; ?>chevron-right.svg');"></span>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div><!-- /right -->

        </div><!-- /stage-hero -->

        <!-- Pattern Break -->
        <?php get_template_part('template-parts/separator'); ?>

        <!-- Full Content if needed -->
        <?php if ($description && strlen(strip_tags($description)) > 300) : ?>
            <div class="stage-full-desc-section">
                 <div class="wam-prose">
                     <?php echo wp_kses_post(wpautop($description)); ?>
                 </div>
            </div>
            <?php get_template_part('template-parts/separator'); ?>
        <?php endif; ?>

        <!-- Vidéos (Aligné sur cours) -->
        <?php if (!empty($videos)): ?>
            <div id="section-videos" class="cours-videos">
                <?php
                foreach ($videos as $video_url):
                    $embed = wp_oembed_get(esc_url($video_url), ['width' => 1200]);
                    if ($embed): ?>
                        <div class="cours-video">
                            <?php echo $embed; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php get_template_part('template-parts/separator'); ?>
        <?php endif; ?>

        <!-- Similar Stage -->
        <div class="section-similaires">
            <div class="section-similaires__heading">
                <span class="btn-icon" style="--icon-url: url('<?php echo $icon_dir; ?>dancer_kiff.svg'); --icon-size: 48px;"></span>
                <h2 class="section-similaires__title title-cool-md color-yellow">ça peut vous faire kiffer :</h2>
            </div>
            <div class="section-similaires__grid">
                <?php 
                $related = new WP_Query([
                    'post_type' => 'stages',
                    'posts_per_page' => 3,
                    'post__not_in' => [$current_id],
                    'orderby' => 'rand'
                ]);
                if ($related->have_posts()) :
                    while ($related->have_posts()) : $related->the_post();
                        get_template_part('template-parts/card-article', null, ['variant'=>'stage']);
                    endwhile;
                    wp_reset_postdata();
                endif;
                ?>
            </div>
        </div>

    </div><!-- /stage-container -->
    <?php endwhile; ?>
</main>

<?php get_footer(); ?>