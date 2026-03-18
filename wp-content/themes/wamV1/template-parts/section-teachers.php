<?php
/**
 * Template Part : Section Professeur·es
 * Récupère les comptes avec le rôle 'professeur' ou 'directrice'
 * et affiche une grille de cartes.
 *
 * @package wamv1
 */

$teachers = get_users(array(
    'role__in' => array('professeur', 'directrice'),
    'orderby' => 'display_name',
    'order' => 'ASC',
));
$pattern_url = get_template_directory_uri() . '/assets/images/bg_pattern_color_black.svg';
$icon_dir = get_template_directory_uri() . '/assets/images/';
?>
<section id="section-teachers" class="section-teachers relative flex flex-col items-center gap-12 py-18 w-full overflow-hidden"
    style="background-image: url('<?php echo esc_url($pattern_url); ?>'); background-size: 500px auto; background-repeat: repeat; background-position: center;"
    aria-label="<?php esc_attr_e("L'équipe de professeur·es", 'wamv1'); ?>">

    <?php /* Titre section */ ?>
    <div class="relative z-10 flex flex-col items-center text-center gap-1">
        <?php /* "La belle team" — .title-cool-md Cholo Rhita */ ?>
        <h2 class="title-cool-md text-wam-text m-0">
            <?php esc_html_e('La belle team', 'wamv1'); ?>
        </h2>
        <?php /* "de vos professeur·es de danse" — .title-sign-md Mallia */ ?>
        <p class="title-sign-md text-wam-text m-0">
            <?php esc_html_e('de vos professeur·es de danse', 'wamv1'); ?>
        </p>
    </div>

    <?php
    /*
     * get_users() avec role__in récupère les utilisateurs WP ayant le rôle
     * 'professeur' ou 'directrice' (rôles personnalisés définis dans inc/roles.php).
     * Les métadonnées de spécialité sont stockées sous la clé 'wam_specialite'
     * via le champ user défini dans functions.php.
     */
    ?>
    <?php /* Grille des cartes */ ?>
    <div id="teachers-grid"
        class="teachers-grid relative z-10 flex flex-wrap gap-6 justify-center max-w-wam-content w-full px-24 box-border">
        <?php if (!empty($teachers)): ?>
            <?php foreach ($teachers as $teacher):
                $teacher_url = get_author_posts_url($teacher->ID);
                $avatar_url = get_avatar_url($teacher->ID, array('size' => 400));
                $specialty = get_user_meta($teacher->ID, 'wam_specialite', true);
                $display_name = $teacher->display_name;
                ?>
                <a href="<?php echo esc_url($teacher_url); ?>"
                    class="teacher-card flex flex-col items-center gap-8 p-4 bg-wam-bg600 rounded-wam-lg w-[240px] no-underline transition-all duration-300 hover:-translate-y-1 hover:bg-wam-bg500">

                    <div class="w-full h-[160px] rounded-wam-md overflow-hidden bg-wam-bg500">
                        <img src="<?php echo esc_url($avatar_url); ?>" alt="<?php echo esc_attr($display_name); ?>" width="240"
                            height="160" class="w-full h-full object-cover block" style="mix-blend-mode: luminosity;"
                            loading="lazy">
                    </div>

                    <div class="flex flex-col items-center gap-3 w-full text-center">
                        <?php /* Nom : .title-sign-sm Mallia 24px */ ?>
                        <p class="title-sign-sm text-wam-text m-0">
                            <?php echo esc_html($display_name); ?>
                        </p>
                        <?php if ($specialty): ?>
                            <?php /* Spécialité : .text-wam-md subtext */ ?>
                            <p class="text-wam-md text-wam-subtext m-0">
                                <?php echo esc_html($specialty); ?>
                            </p>
                        <?php endif; ?>
                        <span class="flex items-center justify-center w-7 h-7 rounded-wam-sm transition-colors duration-200"
                            style="background: rgba(249,244,236,0.06);" aria-hidden="true">
                            <svg width="10" height="10" viewBox="0 0 10 10" fill="none">
                                <path d="M5 1L9 5L5 9M1 5h8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                    stroke-linejoin="round" />
                            </svg>
                        </span>
                    </div>
                </a>
            <?php endforeach; ?>

        <?php else: ?>
            <?php /* Placeholder : 4 cartes vides */ ?>
            <?php for ($i = 0; $i < 4; $i++): ?>
                <div class="flex flex-col items-center gap-8 p-4 bg-wam-bg600 rounded-wam-lg w-[240px]"
                    style="pointer-events:none;">
                    <div class="w-full h-[160px] rounded-wam-md bg-wam-bg500 flex items-center justify-center">
                        <svg class="w-[60px] h-[60px] text-wam-muted" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="30" cy="22" r="12" fill="currentColor" opacity=".4" />
                            <path d="M6 56c0-13.255 10.745-24 24-24s24 10.745 24 24" fill="currentColor" opacity=".2" />
                        </svg>
                    </div>
                    <div class="flex flex-col items-center gap-3 w-full text-center">
                        <p class="title-sign-sm text-wam-text m-0" style="opacity:.3;">
                            <?php esc_html_e('Prénom', 'wamv1'); ?>
                        </p>
                        <p class="text-wam-md text-wam-subtext m-0" style="opacity:.2;">
                            <?php esc_html_e('Spécialité…', 'wamv1'); ?>
                        </p>
                    </div>
                </div>
            <?php endfor; ?>
        <?php endif; ?>
    </div>

    <?php /* Bouton vers la page équipe */ ?>
    <a href="<?php echo esc_url(home_url('/notre-equipe')); ?>" class="btn-secondary relative z-10"
        id="link-professors">
        <?php esc_html_e('Découvrir les profs !', 'wamv1'); ?>
        <span class="btn-icon w-3 h-3"
            style="--icon-url: url('<?php echo esc_url($icon_dir . 'chevron right.svg'); ?>');"
            aria-hidden="true"></span>
    </a>
</section>