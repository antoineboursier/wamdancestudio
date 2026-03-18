<?php
/**
 * The template for displaying all single pages (page.php)
 *
 * @package wamv1
 */

get_header();
get_template_part('template-parts/site-header', null, array('variant' => 'center-forced'));
?>

<main id="primary"
    class="site-main bg-wam-bg800 flex flex-col items-center relative w-full min-h-screen">
    <div class="flex flex-col gap-14 items-center py-12 relative w-full">

        <?php
        /*
         * Boucle WP standard — sur une page, have_posts() retourne true une seule fois.
         * Requise avant tout appel à the_title(), the_content(), etc.
         */
        while (have_posts()) :
            the_post(); ?>

            <!-- Breadcrumb : Accueil > [Titre de la page] -->
            <div id="breadcrumb-page" class="flex items-center justify-start max-w-wam-screen w-full px-24 relative">
                <div class="flex-1 font-outfit font-normal leading-[1.25] text-wam-xs text-wam-muted text-ellipsis whitespace-nowrap overflow-hidden [&_a]:text-wam-muted hover:[&_a]:text-wam-text [&_a]:transition-colors">
                    <?php if (function_exists('yoast_breadcrumb')) : ?>
                        <?php yoast_breadcrumb(); ?>
                    <?php else : ?>
                        <a href="<?php echo esc_url(home_url('/')); ?>"
                            class="hover:text-wam-text transition-colors">Accueil</a> &gt;
                        <?php the_title(); ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- En-tête de page : titre (Mallia) + temps de lecture + image à la une optionnelle -->
            <div id="section-page-header"
                class="flex flex-col lg:flex-row gap-8 lg:gap-24 items-center max-w-wam-screen w-full px-24 relative">
                <div class="flex flex-1 flex-col gap-8 items-start w-full">
                    <h1 class="font-mallia leading-[1.3] text-[clamp(32px,4vw,46px)] text-wam-text w-full m-0">
                        <?php the_title(); ?>
                    </h1>
                    <?php if ($reading_time = wamv1_get_reading_time(get_the_content())) : ?>
                        <p class="font-outfit font-normal leading-[1.25] text-wam-text text-wam-md w-full m-0">
                            <?php echo esc_html($reading_time); ?>
                        </p>
                    <?php endif; ?>
                </div>

                <?php if (has_post_thumbnail()) : ?>
                    <!-- Image à la une (conditionnelle — non affichée si absente) -->
                    <div class="flex flex-1 items-center self-stretch w-full mt-8 lg:mt-0">
                        <div class="flex-1 h-full min-h-[200px] relative rounded-wam-3xl overflow-hidden w-full">
                            <?php the_post_thumbnail('wamv1-page-hero', ['class' => 'absolute inset-0 object-cover w-full h-full']); ?>
                            <div class="absolute inset-0 bg-wam-bg800 mix-blend-lighten pointer-events-none"></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Contenu Gutenberg -->
            <div id="section-page-content"
                class="flex flex-col items-start max-w-wam-screen w-full px-24 relative text-wam-text text-wam-md font-outfit leading-[1.25]">
                <div class="w-full">
                    <?php the_content(); ?>
                </div>
            </div>

        <?php endwhile; ?>

    </div>
</main>

<?php get_footer(); ?>
