<?php
/**
 * Template Part : Section Professeur·es
 * Récupère les comptes avec le rôle 'professeur' ou 'directrice'
 * et affiche une grille de cartes.
 * Utilisable via shortcode [wam_teachers] depuis Gutenberg.
 *
 * @package wamv1
 */

$teachers = get_users(array(
    'role__in' => array('professeur', 'directrice'),
    'orderby' => 'display_name',
    'order' => 'ASC',
));
?>
<section class="section-teachers" aria-label="<?php esc_attr_e('L\'équipe de professeur·es', 'wamv1'); ?>">
    <div class="section-teachers__title">
        <h2>
            <?php esc_html_e('La belle team', 'wamv1'); ?>
        </h2>
        <p>
            <?php esc_html_e('de vos professeur·es de danse', 'wamv1'); ?>
        </p>
    </div>

    <div class="teachers-grid">
        <?php if (!empty($teachers)): ?>
            <?php foreach ($teachers as $teacher):
                $teacher_url = get_author_posts_url($teacher->ID);
                $avatar_url = get_avatar_url($teacher->ID, array('size' => 400));
                $specialty = get_user_meta($teacher->ID, 'wam_specialite', true);
                $display_name = $teacher->display_name;
                ?>
                <a href="<?php echo esc_url($teacher_url); ?>" class="teacher-card">
                    <div class="teacher-card__photo">
                        <img src="<?php echo esc_url($avatar_url); ?>" alt="<?php echo esc_attr($display_name); ?>"
                            width="240" height="160" loading="lazy">
                    </div>
                    <div class="teacher-card__info">
                        <p class="teacher-card__name">
                            <?php echo esc_html($display_name); ?>
                        </p>
                        <?php if ($specialty): ?>
                            <p class="teacher-card__specialty">
                                <?php echo esc_html($specialty); ?>
                            </p>
                        <?php endif; ?>
                        <span class="teacher-card__link-icon" aria-hidden="true">
                            <svg width="10" height="10" viewBox="0 0 10 10" fill="none">
                                <path d="M5 1L9 5L5 9M1 5h8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                    stroke-linejoin="round" />
                            </svg>
                        </span>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <?php /* Placeholder si aucun prof enregistré */ ?>
            <?php for ($i = 0; $i < 4; $i++): ?>
                <div class="teacher-card" style="pointer-events:none;">
                    <div class="teacher-card__photo teacher-card__photo--placeholder">
                        <svg viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="30" cy="22" r="12" fill="currentColor" opacity=".4" />
                            <path d="M6 56c0-13.255 10.745-24 24-24s24 10.745 24 24" fill="currentColor" opacity=".2" />
                        </svg>
                    </div>
                    <div class="teacher-card__info">
                        <p class="teacher-card__name" style="opacity:.3;">
                            <?php esc_html_e('Prénom', 'wamv1'); ?>
                        </p>
                        <p class="teacher-card__specialty" style="opacity:.2;">
                            <?php esc_html_e('Spécialité…', 'wamv1'); ?>
                        </p>
                    </div>
                </div>
            <?php endfor; ?>
        <?php endif; ?>
    </div>

    <a href="<?php echo esc_url(get_post_type_archive_link('page')); ?>" class="btn-secondary" id="link-professors">
        <?php esc_html_e('Découvrir les profs !', 'wamv1'); ?>
        <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
            <path d="M6 1L11 6L6 11M1 6h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                stroke-linejoin="round" />
        </svg>
    </a>
</section>