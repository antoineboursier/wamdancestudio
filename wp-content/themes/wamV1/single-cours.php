<?php
/**
 * Template for single cours CPT
 *
 * Variant detection :
 *   enfant  — terme slug 'danse-enfant' dans la taxonomie cat_cours → Cholo Rhita 68px, jour vert
 *   adulte  — défaut → Mallia 46px (text-wam-sign-lg), jour blanc
 *
 * Taxonomie : cat_cours (assignée à Cours et Stage via ACF)
 *   Slug "danse-enfant" → réservé à la détection enfant/adulte.
 *   Autres termes → affichés en chips.
 *
 * Champs ACF (groupe "Métadonnées Cours") :
 *   sous_titre          (text)        — sous-titre affiché sous le H1
 *   prof_cours          (user, multi) — professeur·es (retourne array d'users)
 *   jour_de_cours       (select)      — valeur "01day"…"07day"
 *   heure_debut         (text)        — ex. "12h30"
 *   heure_de_fin        (text)        — ex. "14h30"
 *   tarif_cours         (post_object) — lié à un post Tarif, affiche post_title
 *   info_complementaire (textarea)    — ex. "Prévoir une tenue adaptée"
 *   complete_cours      (true_false)  — badge orange + bouton désactivé
 *   description_cours   (textarea)    — texte principal du cours
 *   photo_cours         (image)       — illustration "Pédagogie" (retourne array)
 *   pedagogie           (textarea)    — "Mais qu'apprend on en cours ?"
 *   video_1/2/3         (url)         — URLs vidéo (oEmbed)
 *   echauffement_time   (text)        — durée échauffement
 *   echauffement_description (text)   — description échauffement
 *   exercice_time       (text)        — durée exercices
 *   exercice_description (text)       — description exercices
 *   choregraphie_time   (text)        — durée chorégraphie
 *   choregraphie_description (text)   — description chorégraphie
 *   styles_musiques     (text)        — styles musicaux
 *   photo_tenue         (image)       — illustration "Quelle tenue ?" (retourne array)
 *   tenue               (textarea)    — conseils tenue
 *
 * @package wamv1
 */

get_header();
get_template_part('template-parts/site-header', null, ['variant' => 'center-forced']);
?>

<main id="primary"
    class="site-main bg-wam-bg800 flex flex-col items-center w-full min-h-screen">
    <div class="flex flex-col gap-14 items-center py-12 w-full">

        <?php
        /*
         * Boucle WP standard. Requise avant tout appel ACF (get_field())
         * et toute fonction contextuelle (the_title, the_post_thumbnail…).
         * Sur un single CPT, have_posts() retourne true une seule fois.
         */
        while (have_posts()) : the_post();

        /*
         * Détection de la variante enfant / adulte.
         * Taxonomie : cat_cours (créée via ACF, assignée aux CPT cours et stage).
         * Règle : si le terme slug "danse-enfant" est présent → variante enfant.
         * Tous les autres termes alimentent les chips d'affichage.
         */
        $cat_cours_terms = get_the_terms(get_the_ID(), 'cat_cours');
        $is_enfant       = false;
        $chips           = [];
        if ($cat_cours_terms && ! is_wp_error($cat_cours_terms)) {
            foreach ($cat_cours_terms as $term) {
                if ($term->slug === 'danse-enfant') {
                    $is_enfant = true; // → titre Cholo Rhita 68px, jour en vert
                } else {
                    $chips[] = $term->name; // → affiché en chip sous la description
                }
            }
        }

        /*
         * Récupération des champs ACF (groupe "Métadonnées Cours").
         * get_field() retourne null si ACF n'est pas actif → guard $has_acf.
         * Types spéciaux :
         *   prof_cours   → user field (multi), retourne array d'arrays avec 'display_name'
         *   tarif_cours  → post_object, retourne un WP_Post (ou null)
         *   photo_cours / photo_tenue → image, retourne array ('url', 'alt', …)
         *   complete_cours → true_false (bool)
         *   video_1/2/3   → url, passé à wp_oembed_get() pour l'embed
         */
        $has_acf     = function_exists('get_field');
        $sous_titre  = $has_acf ? get_field('sous_titre')               : '';
        $prof_cours  = $has_acf ? get_field('prof_cours')               : [];
        $jour_value  = $has_acf ? get_field('jour_de_cours')            : '';
        $heure_debut = $has_acf ? get_field('heure_debut')              : '';
        $heure_fin   = $has_acf ? get_field('heure_de_fin')             : '';
        $tarif_obj   = $has_acf ? get_field('tarif_cours')              : null;
        $info_comp   = $has_acf ? get_field('info_complementaire')      : '';
        $complet     = $has_acf ? get_field('complete_cours')           : false;
        $description = $has_acf ? get_field('description_cours')        : '';
        $photo_cours = $has_acf ? get_field('photo_cours')              : null;
        $pedagogie   = $has_acf ? get_field('pedagogie')                : '';
        $video_1     = $has_acf ? get_field('video_1')                  : '';
        $video_2     = $has_acf ? get_field('video_2')                  : '';
        $video_3     = $has_acf ? get_field('video_3')                  : '';
        $echauf_time = $has_acf ? get_field('echauffement_time')        : '10 min';
        $echauf_desc = $has_acf ? get_field('echauffement_description') : '';
        $exo_time    = $has_acf ? get_field('exercice_time')            : '20/30 min';
        $exo_desc    = $has_acf ? get_field('exercice_description')     : '';
        $choreo_time = $has_acf ? get_field('choregraphie_time')        : '20/30 min';
        $choreo_desc = $has_acf ? get_field('choregraphie_description') : '';
        $styles_mus  = $has_acf ? get_field('styles_musiques')          : '';
        $photo_tenue = $has_acf ? get_field('photo_tenue')              : null;
        $tenue       = $has_acf ? get_field('tenue')                    : '';

        /*
         * Mapping select ACF → label lisible.
         * ACF retourne la "value" de l'option (ex: "03day"), pas le "label".
         * On construit le libellé manuellement pour éviter toute dépendance à la config ACF.
         */
        // — Correspondance jour_de_cours value → label
        $jour_map   = [
            '01day' => 'Lundi', '02day' => 'Mardi', '03day' => 'Mercredi',
            '04day' => 'Jeudi', '05day' => 'Vendredi', '06day' => 'Samedi', '07day' => 'Dimanche',
        ];
        $jour_label = $jour_map[$jour_value] ?? $jour_value;

        // — Horaires formatés
        $horaires = '';
        if ($heure_debut && $heure_fin) {
            $horaires = $heure_debut . ' – ' . $heure_fin;
        } elseif ($heure_debut) {
            $horaires = $heure_debut;
        }

        // — Professeurs : champ user (array), extraire display_name
        $prof_names = [];
        foreach ((array) $prof_cours as $prof) {
            if (isset($prof['display_name']) && $prof['display_name']) {
                $prof_names[] = $prof['display_name'];
            }
        }

        // — Tarif : champ post_object, afficher le titre du post lié
        $tarif_label = ($tarif_obj instanceof WP_Post) ? $tarif_obj->post_title : '';

        // — Vidéos : on filtre les URLs vides (champs non renseignés dans ACF)
        $videos = array_filter([$video_1, $video_2, $video_3]);

        // Stocké avant la boucle secondaire (WP_Query cours similaires) pour éviter
        // que get_the_ID() retourne l'ID d'un autre post après wp_reset_postdata().
        $current_id = get_the_ID();
        ?>

        <!-- Breadcrumb : chemin Accueil > Cours > [Titre du cours] -->
        <div id="breadcrumb-cours" class="max-w-wam-screen w-full px-24">
            <p class="font-outfit text-wam-xs text-wam-muted truncate [&_a]:text-wam-muted hover:[&_a]:text-wam-text [&_a]:transition-colors">
                <a href="<?php echo esc_url(home_url('/')); ?>">Accueil</a>
                &gt;
                <a href="<?php echo esc_url(get_post_type_archive_link('cours')); ?>">Cours</a>
                &gt;
                <?php the_title(); ?>
            </p>
        </div>

        <!-- ============ HERO : Infos cours ============ -->
        <div id="section-hero-cours" class="flex flex-col lg:flex-row gap-24 items-start max-w-wam-screen w-full px-24">

            <!-- Photo (colonne masquée si ni image à la une ni badge cours complet) -->
            <?php if (has_post_thumbnail() || $complet) : ?>
            <div id="section-hero-photo" class="flex-1 relative rounded-wam-3xl overflow-hidden self-stretch min-h-[420px]">
                <?php if (has_post_thumbnail()) : ?>
                    <?php the_post_thumbnail('large', ['class' => 'absolute inset-0 object-cover w-full h-full']); ?>
                    <div class="absolute inset-0 bg-wam-bg800 mix-blend-lighten pointer-events-none"></div>
                <?php endif; ?>

                <?php if ($complet) : ?>
                    <!-- Badge cours complet -->
                    <div
                        class="absolute top-4 left-4 bg-wam-orange flex gap-4 items-center px-6 py-4 rounded-wam-3xl max-w-[calc(100%-32px)] z-10">
                        <!-- Icône triste -->
                        <svg width="40" height="40" viewBox="0 0 40 40" fill="none"
                            class="shrink-0 text-wam-bg800" aria-hidden="true">
                            <circle cx="20" cy="20" r="18" stroke="currentColor" stroke-width="2" />
                            <circle cx="14" cy="16" r="2" fill="currentColor" />
                            <circle cx="26" cy="16" r="2" fill="currentColor" />
                            <path d="M13 27c1.8-3 5.2-4.5 7-4.5s5.2 1.5 7 4.5" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" />
                        </svg>
                        <div class="flex flex-col gap-2 items-start">
                            <p class="font-outfit font-bold leading-[1.1] text-[32px] text-wam-bg800 m-0">Cours complet</p>
                            <p class="font-outfit text-wam-sm text-wam-bg800 m-0">Malheureusement, ce cours est déjà rempli.</p>
                            <a href="<?php echo esc_url(get_post_type_archive_link('cours')); ?>"
                                class="bg-wam-bg800 flex gap-2 items-center px-4 py-2 rounded-wam-md no-underline">
                                <span class="font-outfit text-wam-sm text-wam-text">Voir tous les cours de danse</span>
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none"
                                    class="-rotate-90 text-wam-text" aria-hidden="true">
                                    <path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.5"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; // has_post_thumbnail || complet ?>

            <!-- Infos : titre, sous-titre, prof, carte infos, chips, description, CTAs -->
            <div id="section-hero-infos" class="flex flex-1 flex-col gap-8 items-start">

                <!-- Titre + sous-titre -->
                <div class="flex flex-col gap-4 items-start w-full">
                    <?php if ($is_enfant) : ?>
                        <h1 class="font-cholo leading-none text-[68px] text-wam-yellow m-0 w-full">
                            <?php the_title(); ?>
                        </h1>
                    <?php else : ?>
                        <h1 class="font-mallia text-wam-sign-lg text-wam-yellow m-0 w-full">
                            <?php the_title(); ?>
                        </h1>
                    <?php endif; ?>

                    <?php if ($sous_titre) : ?>
                        <p class="font-outfit text-wam-lg text-wam-text m-0 w-full">
                            <?php echo esc_html($sous_titre); ?>
                        </p>
                    <?php endif; ?>
                </div>

                <?php if ($prof_names) : ?>
                    <p class="font-outfit text-wam-sm text-wam-muted m-0">
                        avec <?php echo esc_html(implode(' et ', $prof_names)); ?>
                    </p>
                <?php endif; ?>

                <!-- Info card -->
                <div
                    class="bg-gradient-to-r from-wam-bg600 to-wam-bg800 flex flex-col gap-4 items-start px-10 py-6 rounded-wam-3xl w-full">

                    <?php if ($jour_label) : ?>
                        <div class="flex gap-6 items-center w-full">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                class="shrink-0 text-wam-subtext" aria-hidden="true">
                                <rect x="3" y="4" width="18" height="18" rx="3" stroke="currentColor"
                                    stroke-width="1.5" />
                                <path d="M3 9h18M8 2v4M16 2v4" stroke="currentColor" stroke-width="1.5"
                                    stroke-linecap="round" />
                            </svg>
                            <div class="flex flex-col gap-1 items-start flex-1">
                                <p class="font-outfit font-bold text-wam-lg m-0 w-full
                                    <?php echo $is_enfant ? 'text-wam-green' : 'text-wam-text'; ?>">
                                    <?php echo esc_html($jour_label); ?>
                                </p>
                                <?php if ($horaires) : ?>
                                    <p class="font-outfit font-bold text-wam-md text-wam-text m-0 w-full">
                                        <?php echo esc_html($horaires); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="flex gap-6 items-center w-full">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                            class="shrink-0 text-wam-subtext" aria-hidden="true">
                            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"
                                stroke="currentColor" stroke-width="1.5" />
                            <circle cx="12" cy="9" r="2.5" stroke="currentColor" stroke-width="1.5" />
                        </svg>
                        <div class="flex flex-col items-start flex-1">
                            <p class="font-outfit text-wam-md text-wam-text m-0 w-full">WAM Dance Studio</p>
                            <p class="font-outfit text-wam-sm text-wam-subtext m-0 w-full">202 rue Jean Jaurès à Villeneuve d'Ascq</p>
                        </div>
                    </div>

                    <?php if ($tarif_label) : ?>
                        <div class="flex gap-6 items-center w-full">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                class="shrink-0 text-wam-subtext" aria-hidden="true">
                                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5" />
                                <path
                                    d="M15 8.5C14.1 7.6 12.9 7 11.5 7 8.5 7 6 9.5 6 12.5S8.5 18 11.5 18c1.4 0 2.6-.6 3.5-1.5"
                                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                <path d="M9 11h5M9 14h5" stroke="currentColor" stroke-width="1.5"
                                    stroke-linecap="round" />
                            </svg>
                            <p class="font-outfit font-bold text-wam-md text-wam-text m-0 flex-1">
                                <?php echo esc_html($tarif_label); ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <?php if ($info_comp) : ?>
                        <div class="flex gap-6 items-center w-full">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                class="shrink-0 text-wam-subtext" aria-hidden="true">
                                <path
                                    d="M14 9V5a3 3 0 00-3-3l-4 9v11h11.28a2 2 0 002-1.7l1.38-9a2 2 0 00-2-2.3H14z"
                                    stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                                <path d="M7 22H4a2 2 0 01-2-2v-7a2 2 0 012-2h3" stroke="currentColor"
                                    stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <p class="font-outfit text-wam-md text-wam-text m-0 flex-1">
                                <?php echo esc_html($info_comp); ?>
                            </p>
                        </div>
                    <?php endif; ?>

                </div><!-- /info card -->

                <?php if ($chips) : ?>
                    <!-- Chips taxonomie -->
                    <div class="flex flex-wrap gap-2 items-center w-full">
                        <?php foreach ($chips as $chip_name) : ?>
                            <span
                                class="border border-wam-subtext flex items-center justify-center px-3 py-2 rounded-wam-lg">
                                <span class="font-outfit text-wam-sm text-wam-subtext whitespace-nowrap">
                                    <?php echo esc_html($chip_name); ?>
                                </span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Description générale -->
                <?php if ($description) : ?>
                    <div
                        class="font-outfit text-wam-md text-wam-text flex flex-col gap-4 w-full [&>p]:m-0">
                        <?php echo wp_kses_post(wpautop($description)); ?>
                    </div>
                <?php endif; ?>

                <!-- CTAs — inscription (désactivé si cours complet) + réserver un essai -->
                <div id="section-ctas" class="flex flex-wrap gap-4 items-start w-full">
                    <?php if ($complet) : ?>
                        <!-- Inscription désactivée (cours complet) -->
                        <div id="btn-inscription"
                            class="bg-wam-bg600 flex items-center justify-center px-8 py-4 rounded-wam-lg cursor-not-allowed">
                            <span
                                class="font-outfit font-bold text-wam-md text-wam-muted whitespace-nowrap">Inscription
                                2024/25</span>
                        </div>
                    <?php else : ?>
                        <a id="btn-inscription" href="#inscription"
                            class="bg-wam-yellow flex gap-2 items-center justify-center px-8 py-4 rounded-wam-lg no-underline">
                            <span
                                class="font-outfit font-bold text-wam-md text-wam-bg800 whitespace-nowrap">Inscription
                                2024/25</span>
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none"
                                class="-rotate-90 text-wam-bg800" aria-hidden="true">
                                <path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.5"
                                    stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </a>
                    <?php endif; ?>

                    <a id="btn-essai" href="#essai"
                        class="border-2 border-wam-yellow flex gap-2 items-center justify-center px-8 py-4 rounded-wam-lg no-underline hover:bg-wam-glass-yellow transition-colors">
                        <span
                            class="font-outfit font-bold text-wam-md text-wam-yellow whitespace-nowrap">Réserver
                            un essai</span>
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="none"
                            class="-rotate-90 text-wam-yellow" aria-hidden="true">
                            <path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.5"
                                stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </a>
                </div>

            </div><!-- /infos -->
        </div><!-- /hero -->

        <!-- Séparateur pattern danseurs (entre hero et sections) -->
        <?php get_template_part('template-parts/separator'); ?>

        <!-- ============ SECTIONS DESCRIPTION ============ -->
        <div id="section-description" class="flex flex-col gap-24 items-start max-w-wam-screen w-full px-24">

            <!-- Pédagogie : "Mais qu'apprend on en cours ?" -->
            <?php if ($pedagogie || $photo_cours) : ?>
                <div id="section-pedagogie" class="flex gap-16 items-center w-full">
                    <div class="flex flex-1 flex-col gap-10 items-start">
                        <div class="flex gap-4 items-center w-full">
                            <svg width="54" height="72" viewBox="0 0 54 72" fill="none"
                                class="shrink-0 text-wam-text" aria-hidden="true">
                                <circle cx="27" cy="8" r="7" stroke="currentColor" stroke-width="2" />
                                <path
                                    d="M27 15v22M15 25l12 12 12-12M20 55l7-18 7 18M16 72l4-17M38 72l-4-17"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                            </svg>
                            <h2 class="font-cholo text-wam-cool-md text-wam-text flex-1 m-0 leading-none">
                                Mais qu'apprend on en cours ?
                            </h2>
                        </div>
                        <?php if ($pedagogie) : ?>
                            <p class="font-outfit text-wam-md text-wam-subtext m-0 w-full">
                                <?php echo esc_html($pedagogie); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <?php if ($photo_cours) : ?>
                        <div class="relative rounded-wam-3xl overflow-hidden shrink-0 w-[440px] h-[240px]">
                            <img src="<?php echo esc_url($photo_cours['url']); ?>"
                                alt="<?php echo esc_attr($photo_cours['alt']); ?>"
                                class="absolute inset-0 object-cover w-full h-full">
                            <div
                                class="absolute inset-0 bg-wam-bg800 mix-blend-lighten pointer-events-none">
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Déroulé : "Comment se déroule un cours ?" -->
            <?php if ($echauf_time || $exo_time || $choreo_time) : ?>
                <div id="section-deroulement" class="flex flex-col gap-8 items-center w-full">

                    <div class="flex gap-4 items-center justify-center pb-4 w-full">
                        <svg width="60" height="72" viewBox="0 0 60 72" fill="none"
                            class="shrink-0 text-wam-text" aria-hidden="true">
                            <circle cx="30" cy="8" r="7" stroke="currentColor" stroke-width="2" />
                            <path
                                d="M30 15v22M10 32l20-8 20 8M20 58l10-22 10 22M15 72l5-14M45 72l-5-14"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                        </svg>
                        <h2 class="font-cholo text-wam-cool-md text-wam-text flex-1 m-0 leading-none">
                            Comment se déroule un cours ?
                        </h2>
                    </div>

                    <!-- 3 étapes -->
                    <div class="flex gap-4 items-stretch w-full min-h-[301px]">

                        <!-- Échauffement — pill gauche -->
                        <div
                            class="bg-gradient-to-r from-wam-bg800 to-wam-bg600 flex flex-1 flex-col gap-6 items-center justify-center pl-14 pr-6 py-10 rounded-l-[200px] rounded-r-wam-3xl self-stretch">
                            <div class="flex flex-col gap-2 items-center">
                                <svg width="80" height="64" viewBox="0 0 80 64" fill="none"
                                    class="text-wam-text" aria-hidden="true">
                                    <circle cx="40" cy="20" r="16" stroke="currentColor"
                                        stroke-width="2" />
                                    <path d="M40 8v12l8 4" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" />
                                </svg>
                                <p
                                    class="font-outfit font-bold text-wam-md text-wam-text text-center m-0">
                                    <?php echo esc_html($echauf_time); ?>
                                </p>
                            </div>
                            <p class="font-cholo text-wam-cool-lg text-wam-yellow text-center m-0 w-full leading-none">
                                Échauffement
                            </p>
                            <?php if ($echauf_desc) : ?>
                                <p
                                    class="font-outfit text-wam-sm text-wam-subtext text-center m-0 w-full">
                                    <?php echo esc_html($echauf_desc); ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <!-- Exercices — card normale -->
                        <div
                            class="bg-wam-bg600 flex flex-1 flex-col gap-4 items-center justify-center px-10 py-10 rounded-wam-3xl self-stretch">
                            <div class="flex flex-col gap-2 items-center">
                                <svg width="50" height="80" viewBox="0 0 50 80" fill="none"
                                    class="text-wam-text" aria-hidden="true">
                                    <circle cx="25" cy="10" r="8" stroke="currentColor"
                                        stroke-width="2" />
                                    <path d="M25 18v30M10 35l15 13 15-13M18 72l7-24 7 24"
                                        stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" />
                                </svg>
                                <p
                                    class="font-outfit font-bold text-wam-md text-wam-text text-center m-0">
                                    <?php echo esc_html($exo_time); ?>
                                </p>
                            </div>
                            <p class="font-cholo text-wam-cool-lg text-wam-yellow text-center m-0 w-full leading-none">
                                Exercices techniques
                            </p>
                            <?php if ($exo_desc) : ?>
                                <p
                                    class="font-outfit text-wam-sm text-wam-subtext text-center m-0 w-full">
                                    <?php echo esc_html($exo_desc); ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <!-- Chorégraphie — pill droite -->
                        <div
                            class="bg-gradient-to-l from-wam-bg800 to-wam-bg600 flex flex-1 flex-col gap-6 items-center justify-center pl-6 pr-14 py-10 rounded-l-wam-3xl rounded-r-[200px] self-stretch">
                            <div class="flex flex-col gap-2 items-center">
                                <svg width="103" height="64" viewBox="0 0 103 64" fill="none"
                                    class="text-wam-text" aria-hidden="true">
                                    <path d="M20 48 Q51 8 82 48" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" fill="none" />
                                    <path d="M8 56l12-8M95 56l-12-8" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" />
                                </svg>
                                <p
                                    class="font-outfit font-bold text-wam-md text-wam-text text-center m-0">
                                    <?php echo esc_html($choreo_time); ?>
                                </p>
                            </div>
                            <p class="font-cholo text-wam-cool-lg text-wam-yellow text-center m-0 w-full leading-none">
                                Chorégraphie
                            </p>
                            <?php if ($choreo_desc) : ?>
                                <p
                                    class="font-outfit text-wam-sm text-wam-subtext text-center m-0 w-full">
                                    <?php echo esc_html($choreo_desc); ?>
                                </p>
                            <?php endif; ?>
                        </div>

                    </div><!-- /3 étapes -->

                    <?php if ($styles_mus) : ?>
                        <div
                            class="bg-wam-bg600 flex gap-8 items-center max-w-[1024px] px-14 py-6 rounded-wam-pill w-full">
                            <svg width="40" height="45" viewBox="0 0 40 45" fill="none"
                                class="shrink-0 text-wam-subtext" aria-hidden="true">
                                <path d="M15 35a5 5 0 100-10 5 5 0 000 10zM35 30a5 5 0 100-10 5 5 0 000 10z"
                                    stroke="currentColor" stroke-width="2" />
                                <path d="M20 30V10l15-5v20" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <p class="font-outfit flex-1 text-wam-sm text-wam-text m-0 leading-[1.25]">
                                <strong class="font-bold text-wam-subtext">Styles de musiques :</strong>
                                <?php echo ' ' . esc_html($styles_mus); ?>
                            </p>
                        </div>
                    <?php endif; ?>

                </div>
            <?php endif; ?>

            <!-- Tenue : "Quelle tenue ?" -->
            <?php if ($tenue || $photo_tenue) : ?>
                <div id="section-tenue" class="flex gap-16 items-center w-full">
                    <div class="flex flex-1 flex-col gap-10 items-start">
                        <div class="flex gap-4 items-center w-full">
                            <svg width="37" height="80" viewBox="0 0 37 80" fill="none"
                                class="shrink-0 text-wam-text" aria-hidden="true">
                                <circle cx="18" cy="8" r="7" stroke="currentColor"
                                    stroke-width="2" />
                                <path d="M18 15v28M5 30l13 15 13-15M12 72l6-28 6 28"
                                    stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" />
                            </svg>
                            <h2 class="font-cholo text-wam-cool-md text-wam-text flex-1 m-0 leading-none">
                                Quelle tenue ?
                            </h2>
                        </div>
                        <?php if ($tenue) : ?>
                            <div
                                class="font-outfit text-wam-md text-wam-subtext flex flex-col gap-6 w-full [&>p]:m-0">
                                <?php echo wp_kses_post(wpautop($tenue)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($photo_tenue) : ?>
                        <div class="relative rounded-wam-3xl overflow-hidden shrink-0 w-[440px] h-[248px]">
                            <img src="<?php echo esc_url($photo_tenue['url']); ?>"
                                alt="<?php echo esc_attr($photo_tenue['alt']); ?>"
                                class="absolute inset-0 object-cover w-full h-full">
                            <div
                                class="absolute inset-0 bg-wam-bg800 mix-blend-lighten pointer-events-none">
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div><!-- /description sections -->

        <!-- ============ VIDÉOS ============ -->
        <?php if ($videos) : ?>
            <div id="section-videos" class="w-full max-w-wam-screen px-24 flex flex-col gap-6">
                <?php
                /*
                 * wp_oembed_get() contacte le provider (YouTube, Vimeo…) et retourne
                 * le HTML de l'iframe. Retourne false si l'URL n'est pas supportée.
                 * On passe 'width' pour que WordPress calcule la hauteur en respectant
                 * le ratio 16:9 (aspect-video en CSS fait le reste).
                 */
                foreach ($videos as $video_url) :
                    $embed = wp_oembed_get(esc_url($video_url), ['width' => 1200]);
                    if ($embed) : ?>
                        <div class="relative w-full aspect-video rounded-wam-3xl overflow-hidden">
                            <?php echo $embed; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php endwhile; ?>

        <!-- ============ SÉPARATEUR + COURS SIMILAIRES ============ -->
        <?php
        /*
         * get_template_part() inclut template-parts/separator.php.
         * Ce template affiche le motif SVG de danseurs qui sépare les sections.
         */
        get_template_part('template-parts/separator');

        /*
         * WP_Query secondaire : 3 cours aléatoires hors cours courant.
         * 'post__not_in' exclut le post affiché (stocké dans $current_id avant
         * la boucle principale pour rester valide ici, après endwhile).
         * wp_reset_postdata() est impératif après la boucle pour restaurer
         * le post global ($post) et éviter tout bug d'affichage en aval.
         */
        $related = new WP_Query([
            'post_type'      => 'cours',
            'posts_per_page' => 3,
            'post__not_in'   => [$current_id],
            'orderby'        => 'rand',
        ]);
        if ($related->have_posts()) :
        ?>
            <div id="section-similaires" class="flex flex-col gap-10 items-center max-w-wam-screen w-full px-24">
                <p class="font-cholo text-wam-cool-md text-wam-yellow text-center w-full leading-none">
                    ça peut vous faire kiffer :
                </p>
                <div class="flex flex-wrap gap-8 items-stretch justify-center w-full">
                    <?php
                    while ($related->have_posts()) : $related->the_post();
                        /*
                         * Inclut la card réutilisable avec variante 'cours'.
                         * Le template-part reçoit $args['variant'] pour adapter son rendu.
                         */
                        get_template_part('template-parts/card-article', null, ['variant' => 'cours']);
                    endwhile;
                    // Restaure le post global après la WP_Query secondaire.
                    wp_reset_postdata();
                    ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
</main>

<?php get_footer(); ?>
