<?php
/**
 * Template part : Grille des professeurs
 *
 * Paramètres via $args (optionnels) :
 *   'show_title' => bool (default: true)
 *   'show_cta'   => bool (default: true)
 *   'no_pattern' => bool (default: false)
 *
 * @package wamv1
 */

$show_title = $args['show_title'] ?? true;
$show_cta   = $args['show_cta']   ?? true;
$no_pattern = $args['no_pattern'] ?? false;

/*
 * Données des profs mises en cache 24h (requête + champs + HTML image).
 * Invalidation à la sauvegarde d'un wam_membre dans inc/performance.php
 * (wamv1_flush_content_transients).
 */
$teachers = get_transient('wamv1_teachers_grid_v1');

if (!is_array($teachers)) {
    $teachers = array();

    $teachers_query = new WP_Query(array(
        'post_type'      => 'wam_membre',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'no_found_rows'  => true,
    ));

    while ($teachers_query->have_posts()) {
        $teachers_query->the_post();

        $user_prof = function_exists('get_field') ? get_field('user_prof') : null;
        $specialty = '';
        if ($user_prof && isset($user_prof['ID'])) {
            $specialty = get_user_meta($user_prof['ID'], 'wam_specialite', true);
        }

        $img_html = '';
        if (has_post_thumbnail()) {
            $img_html = wamv1_get_image_with_overlay(get_post_thumbnail_id(), 'wam-prof-thumb', 'teacher-card__img-wrapper', array(
                'class'   => 'teacher-card__avatar',
                'loading' => 'lazy',
            ));
        }

        $teachers[] = array(
            'url'        => get_permalink(),
            'name'       => get_the_title(),
            'micro_desc' => function_exists('get_field') ? get_field('micro_description_prof') : get_post_meta(get_the_ID(), 'micro_description_prof', true),
            'specialty'  => $specialty,
            'img_html'   => $img_html,
        );
    }
    wp_reset_postdata();

    set_transient('wamv1_teachers_grid_v1', $teachers, DAY_IN_SECONDS);
}

$icon_dir = get_template_directory_uri() . '/assets/images/';
?>
<section id="section-teachers" class="section-teachers <?php echo $no_pattern ? 'section-teachers--no-pattern' : ''; ?>"
    aria-label="<?php esc_attr_e("L'équipe de professeur·es", 'wamv1'); ?>">

    <?php if ($show_title) : ?>
        <?php /* Titre section */ ?>
        <div class="section-teachers__title">
        <h2 class="title-cool-md color-text">
            <?php esc_html_e('La belle team', 'wamv1'); ?>
        </h2>
        <p class="title-sign-md">
            <?php esc_html_e('de vos professeur·es de danse', 'wamv1'); ?>
        </p>
    </div>
    <?php endif; ?>

    <?php /* Grille des cartes */ ?>
    <div id="teachers-grid" class="teachers-grid">
        <?php if (!empty($teachers)): ?>
            <?php foreach ($teachers as $teacher):
                $has_photo = !empty($teacher['img_html']);
                ?>
                <a href="<?php echo esc_url($teacher['url']); ?>" class="teacher-card <?php echo !$has_photo ? 'teacher-card--no-photo' : ''; ?>">

                    <?php if ($has_photo) : ?>
                        <div class="teacher-card__photo">
                            <?php echo $teacher['img_html']; // HTML généré par wamv1_get_image_with_overlay ?>
                        </div>
                    <?php endif; ?>

                    <div class="teacher-card__info">
                        <?php /* Nom : .title-sign-sm Mallia 24px */ ?>
                        <p class="title-sign-sm">
                            <?php echo esc_html($teacher['name']); ?>
                        </p>

                        <?php if (!empty($teacher['micro_desc'])) : ?>
                            <p class="text-md color-subtext">
                                <?php echo esc_html($teacher['micro_desc']); ?>
                            </p>
                        <?php endif; ?>

                        <?php if (!empty($teacher['specialty'])): ?>
                            <p class="text-md teacher-card__specialty">
                                <?php echo esc_html($teacher['specialty']); ?>
                            </p>
                        <?php endif; ?>

                        <span class="teacher-card__link-icon" aria-hidden="true">
                            <span class="btn-icon btn-icon--sm"
                                  style="--icon-url: url('<?php echo esc_url($icon_dir . 'chevron-right.svg'); ?>');"></span>
                        </span>
                    </div>
                </a>
            <?php endforeach; ?>
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

    <?php if ($show_cta) : ?>
        <?php /* Bouton vers la page équipe */ ?>
        <div class="section-teachers__cta">
            <a href="<?php echo esc_url(home_url('/prof-wam/')); ?>" class="btn-secondary"
                id="link-professors">
                <?php esc_html_e('Découvrir les profs !', 'wamv1'); ?>
                <span class="btn-icon btn-icon--sm"
                    style="--icon-url: url('<?php echo esc_url($icon_dir . 'chevron-right.svg'); ?>');"
                    aria-hidden="true"></span>
            </a>
        </div>
    <?php endif; ?>
</section>
