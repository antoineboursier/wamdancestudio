<?php
$args = array(
    'post_type'      => 'wam_membre',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'title',
    'order'          => 'ASC',
);
$teachers_query = new WP_Query($args);
$icon_dir = get_template_directory_uri() . '/assets/images/';
?>
<section id="section-teachers" class="section-teachers"
    aria-label="<?php esc_attr_e("L'équipe de professeur·es", 'wamv1'); ?>">

    <?php /* Titre section */ ?>
    <div class="section-teachers__title">
        <h2 class="title-cool-md color-text">
            <?php esc_html_e('La belle team', 'wamv1'); ?>
        </h2>
        <p class="title-sign-md">
            <?php esc_html_e('de vos professeur·es de danse', 'wamv1'); ?>
        </p>
    </div>

    <?php /* Grille des cartes */ ?>
    <div id="teachers-grid" class="teachers-grid">
        <?php if ($teachers_query->have_posts()): ?>
            <?php while ($teachers_query->have_posts()): $teachers_query->the_post();
                $teacher_url = get_permalink();
                $specialty   = function_exists('get_field') ? get_field('specialite') : get_post_meta(get_the_ID(), 'specialite', true);
                if (!$specialty) {
                    $specialty = get_post_meta(get_the_ID(), 'wam_specialite', true);
                }
                $display_name = get_the_title();
                $has_photo    = has_post_thumbnail();
                ?>
                <a href="<?php echo esc_url($teacher_url); ?>" class="teacher-card">

                    <?php if ($has_photo) : ?>
                        <div class="teacher-card__photo">
                            <?php echo wamv1_get_image_with_overlay(get_post_thumbnail_id(), 'medium_large', 'teacher-card__img-wrapper', array(
                                'class'   => 'teacher-card__avatar',
                                'loading' => 'lazy',
                            )); ?>
                        </div>
                    <?php endif; ?>

                    <div class="teacher-card__info">
                        <?php /* Nom : .title-sign-sm Mallia 24px */ ?>
                        <p class="title-sign-sm">
                            <?php echo esc_html($display_name); ?>
                        </p>

                        <?php 
                        $micro_desc = function_exists('get_field') ? get_field('micro_description_prof') : get_post_meta(get_the_ID(), 'micro_description_prof', true);
                        if ($micro_desc) : ?>
                            <p class="text-md color-subtext">
                                <?php echo esc_html($micro_desc); ?>
                            </p>
                        <?php endif; ?>

                        <?php if ($specialty): ?>
                            <p class="text-md teacher-card__specialty">
                                <?php echo esc_html($specialty); ?>
                            </p>
                        <?php endif; ?>

                        <span class="teacher-card__link-icon" aria-hidden="true">
                            <span class="btn-icon btn-icon--sm" 
                                  style="--icon-url: url('<?php echo esc_url($icon_dir . 'chevron right.svg'); ?>');"></span>
                        </span>
                    </div>
                </a>
            <?php endwhile; wp_reset_postdata(); ?>
        <?php else: ?>
            <?php /* Placeholder : 4 cartes vides */ ?>
            <?php for ($i = 0; $i < 4; $i++): ?>
                <div class="teacher-card teacher-card--placeholder">
                    <div class="teacher-card__photo teacher-card__photo--placeholder">
                        <svg viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <circle cx="30" cy="22" r="12" fill="currentColor" opacity=".4" />
                            <path d="M6 56c0-13.255 10.745-24 24-24s24 10.745 24 24" fill="currentColor" opacity=".2" />
                        </svg>
                    </div>
                    <div class="teacher-card__info">
                        <p class="title-sign-sm">
                            <?php esc_html_e('Prénom', 'wamv1'); ?>
                        </p>
                        <p class="text-md teacher-card__specialty">
                            <?php esc_html_e('Spécialité…', 'wamv1'); ?>
                        </p>
                    </div>
                </div>
            <?php endfor; ?>
        <?php endif; ?>
    </div>

    <?php /* Bouton vers la page équipe */ ?>
    <div class="section-teachers__cta">
        <a href="<?php echo esc_url(home_url('/prof-wam/')); ?>" class="btn-secondary"
            id="link-professors">
            <?php esc_html_e('Découvrir les profs !', 'wamv1'); ?>
            <span class="btn-icon btn-icon--sm"
                style="--icon-url: url('<?php echo esc_url($icon_dir . 'chevron right.svg'); ?>');"
                aria-hidden="true"></span>
        </a>
    </div>
</section>
