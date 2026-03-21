<?php
/**
 * The template for displaying all single members (single-wam_membre.php)
 *
 * @package wamv1
 */

get_header();
get_template_part('template-parts/site-header');

$micro_desc     = get_field('micro_description_prof');
$socials        = get_field('reseaux_sociaux_prof');
$full_desc       = get_field('description_prof');
$icon_dir       = get_template_directory_uri() . '/assets/images/';

?>

<main id="primary" class="site-main">
    <div class="page-layout__inner">

        <?php
        while (have_posts()) :
            the_post(); ?>

            <!-- Breadcrumb -->
            <div id="breadcrumb-page" class="page-breadcrumb">
                <div class="page-breadcrumb__inner">
                    <?php if (function_exists('yoast_breadcrumb')) : ?>
                        <?php yoast_breadcrumb(); ?>
                    <?php else : ?>
                        <a href="<?php echo esc_url(home_url('/')); ?>">Accueil</a> &gt;
                        <a href="<?php echo esc_url(get_post_type_archive_link('wam_membre')); ?>">L'Équipe</a> &gt;
                        <?php the_title(); ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- En-tête du profil -->
            <article id="post-<?php the_ID(); ?>" <?php post_class('prof-profile'); ?>>
                <div id="section-prof-header" class="page-header prof-header">
                    <div class="page-header__meta prof-header__meta">
                        <h1 class="page-header__title prof-header__title">
                            <?php the_title(); ?>
                        </h1>

                        <?php if ($micro_desc) : ?>
                            <p class="prof-header__micro-desc">
                                <?php echo esc_html($micro_desc); ?>
                            </p>
                        <?php endif; ?>

                        <?php if ($socials) : 
                            $has_social = false;
                            foreach($socials as $link) { if($link) $has_social = true; }
                            if ($has_social) : ?>
                            <div class="prof-header__socials">
                                <?php if ($socials['instagram_link_prof']) : ?>
                                    <a href="<?php echo esc_url($socials['instagram_link_prof']); ?>" class="prof-social-link" target="_blank" rel="noopener" aria-label="Instagram">
                                        <span class="btn-icon" style="--icon-url: url('<?php echo $icon_dir; ?>logo_instagram.svg');"></span>
                                    </a>
                                <?php endif; ?>

                                <?php if ($socials['facebook_link_prof']) : ?>
                                    <a href="<?php echo esc_url($socials['facebook_link_prof']); ?>" class="prof-social-link" target="_blank" rel="noopener" aria-label="Facebook">
                                        <span class="btn-icon" style="--icon-url: url('<?php echo $icon_dir; ?>logo_facebook.svg');"></span>
                                    </a>
                                <?php endif; ?>

                                <?php if ($socials['tiktok_link_prof']) : ?>
                                    <a href="<?php echo esc_url($socials['tiktok_link_prof']); ?>" class="prof-social-link" target="_blank" rel="noopener" aria-label="TikTok">
                                        <span class="btn-icon" style="--icon-url: url('<?php echo $icon_dir; ?>logo_tiktok.svg');"></span>
                                    </a>
                                <?php endif; ?>

                                <?php if ($socials['linkedin_link_prof']) : ?>
                                    <a href="<?php echo esc_url($socials['linkedin_link_prof']); ?>" class="prof-social-link" target="_blank" rel="noopener" aria-label="LinkedIn">
                                        <span class="btn-icon" style="--icon-url: url('<?php echo $icon_dir; ?>logo_linkedin.svg');"></span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; 
                        endif; ?>
                    </div>

                    <?php if (has_post_thumbnail()) : ?>
                        <div class="page-header__photo-outer prof-header__photo-outer">
                            <div class="page-header__photo prof-header__photo">
                                <?php the_post_thumbnail('large', ['class' => 'page-header__photo-img']); ?>
                                <div class="page-header__photo-overlay"></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Bio / Description complète -->
                <div id="section-prof-content" class="page-content prof-content">
                    <div class="page-content__inner wam-prose">
                        <?php 
                        if ($full_desc) {
                            echo $full_desc;
                        } else {
                            the_content();
                        }
                        ?>
                    </div>
                </div>

                <!-- Cours et Stages liés -->
                <?php
                $user_prof = get_field('user_prof');
                $user_id   = $user_prof['ID'] ?? null;

                if ($user_id) :
                    // 1. Les Cours
                    $args_cours = [
                        'post_type'      => 'cours',
                        'posts_per_page' => -1,
                        'meta_query'     => [
                            [
                                'key'     => 'prof_cours',
                                'value'   => '"' . $user_id . '"',
                                'compare' => 'LIKE',
                            ]
                        ]
                    ];
                    $query_cours = new WP_Query($args_cours);

                    if ($query_cours->have_posts()) : ?>
                        <div class="prof-related-section">
                            <h2 class="prof-related-title">Ses cours</h2>
                            <div class="prof-related-grid">
                                <?php while ($query_cours->have_posts()) : $query_cours->the_post(); 
                                    $s_titre = get_field('sous_titre');
                                    $jour    = get_field('jour_de_cours');
                                    $h_deb   = get_field('heure_debut');
                                    $h_fin   = get_field('heure_de_fin');
                                    $jour_map = ['01day'=>'Lundi', '02day'=>'Mardi', '03day'=>'Mercredi', '04day'=>'Jeudi', '05day'=>'Vendredi', '06day'=>'Samedi', '07day'=>'Dimanche'];
                                    $jour_label = $jour_map[$jour] ?? $jour;
                                ?>
                                    <a href="<?php the_permalink(); ?>" class="prof-related-card">
                                        <?php if (has_post_thumbnail()) : ?>
                                            <div class="prof-related-card__photo">
                                                <?php the_post_thumbnail('thumbnail'); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="prof-related-card__content">
                                            <h3 class="prof-related-card__title"><?php the_title(); ?></h3>
                                            <?php if ($s_titre) : ?>
                                                <p class="prof-related-card__subtitle"><?php echo esc_html($s_titre); ?></p>
                                            <?php endif; ?>
                                            <?php if ($jour_label || $h_deb) : ?>
                                                <p class="prof-related-card__meta">
                                                    <?php echo esc_html($jour_label); ?> <?php echo esc_html($h_deb); ?><?php echo $h_fin ? ' – ' . esc_html($h_fin) : ''; ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                <?php endwhile; wp_reset_postdata(); ?>
                            </div>
                        </div>
                    <?php endif;

                    // 2. Les Stages
                    $args_stages = [
                        'post_type'      => 'stages',
                        'posts_per_page' => -1,
                        'meta_query'     => [
                            [
                                'key'     => 'intervenant' . "\xc2\xb7" . 'e_stage_intervenant',
                                'value'   => $user_id,
                                'compare' => '=',
                            ]
                        ]
                    ];
                    $query_stages = new WP_Query($args_stages);

                    if ($query_stages->have_posts()) : ?>
                        <div class="prof-related-section">
                            <h2 class="prof-related-title">Ses stages</h2>
                            <div class="prof-related-grid">
                                <?php while ($query_stages->have_posts()) : $query_stages->the_post(); 
                                    $s_titre  = get_field('sous_titre');
                                    $date_s   = get_field('date_stage');
                                    $h_deb_s  = get_field('heure_debut');
                                    $h_fin_s  = get_field('heure_de_fin');
                                ?>
                                    <a href="<?php the_permalink(); ?>" class="prof-related-card">
                                        <?php if (has_post_thumbnail()) : ?>
                                            <div class="prof-related-card__photo">
                                                <?php the_post_thumbnail('thumbnail'); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="prof-related-card__content">
                                            <h3 class="prof-related-card__title"><?php the_title(); ?></h3>
                                            <?php if ($s_titre) : ?>
                                                <p class="prof-related-card__subtitle"><?php echo esc_html($s_titre); ?></p>
                                            <?php endif; ?>
                                            <?php if ($date_s || $h_deb_s) : ?>
                                                <p class="prof-related-card__meta">
                                                    <?php echo esc_html($date_s); ?> <?php echo esc_html($h_deb_s); ?><?php echo $h_fin_s ? ' – ' . esc_html($h_fin_s) : ''; ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                <?php endwhile; wp_reset_postdata(); ?>
                            </div>
                        </div>
                    <?php endif;
                endif; ?>
            </article>

        <?php endwhile; ?>

    </div>
</main>

<?php get_footer(); ?>
