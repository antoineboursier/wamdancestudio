<?php
/**
 * Template for single cours CPT
 *
 * Variant detection :
 *   enfant  — terme slug 'danse-enfant' dans la taxonomie cat_cours → Cholo Rhita 68px, jour vert
 *   adulte  — défaut → Mallia 46px (title-sign-lg), jour blanc
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
?>

<main id="primary" class="site-main">
    <div class="page-layout__inner">

        <?php
        /*
         * Boucle WP standard. Requise avant tout appel ACF (get_field())
         * et toute fonction contextuelle (the_title, the_post_thumbnail…).
         * Sur un single CPT, have_posts() retourne true une seule fois.
         */
        while (have_posts()):
            the_post();

            /*
             * Détection de la variante enfant / adulte.
             * Taxonomie : cat_cours (créée via ACF, assignée aux CPT cours et stage).
             * Règle : si le terme slug "danse-enfant" est présent → variante enfant.
             * Tous les autres termes alimentent les chips d'affichage.
             */
            $is_enfant = wamv1_is_enfant_variant();
            $chips = [];
            // URL de la page listing — calculée une fois, réutilisée dans la boucle
            $cours_listing_url = get_permalink(get_page_by_path('cours-collectifs'));
            $cat_cours_terms   = get_the_terms(get_the_ID(), 'cat_cours');
            if ($cat_cours_terms && !is_wp_error($cat_cours_terms)) {
                foreach ($cat_cours_terms as $term) {
                    // Pointer vers /cours-collectifs/?cat={slug} pour le pré-filtre JS
                    $term_link = $cours_listing_url
                        ? add_query_arg('cat', $term->slug, $cours_listing_url)
                        : get_term_link($term); // fallback si la page n'existe pas
                    if (!is_wp_error($term_link)) {
                        $chips[] = [
                            'name' => $term->name,
                            'link' => $term_link,
                        ];
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
            $has_acf = function_exists('get_field');
            $sous_titre = $has_acf ? get_field('sous_titre') : '';
            $prof_cours = $has_acf ? get_field('prof_cours') : [];
            $jour_value = $has_acf ? get_field('jour_de_cours') : '';
            $heure_debut = $has_acf ? get_field('heure_debut') : '';
            $heure_fin = $has_acf ? get_field('heure_de_fin') : '';
            $tarif_obj = $has_acf ? get_field('tarif_cours') : null;
            $info_comp = $has_acf ? get_field('info_complementaire') : '';
            $complet = $has_acf ? get_field('complete_cours') : false;
            $description = $has_acf ? get_field('description_cours') : '';
            $photo_cours = $has_acf ? get_field('photo_cours') : null;
            $pedagogie = $has_acf ? get_field('pedagogie') : '';
            $video_1 = $has_acf ? get_field('video_1') : '';
            $video_2 = $has_acf ? get_field('video_2') : '';
            $video_3 = $has_acf ? get_field('video_3') : '';
            $echauf_time = $has_acf ? get_field('echauffement_time') : '10 min';
            $echauf_desc = $has_acf ? get_field('echauffement_description') : '';
            $exo_time = $has_acf ? get_field('exercice_time') : '20/30 min';
            $exo_desc = $has_acf ? get_field('exercice_description') : '';
            $choreo_time = $has_acf ? get_field('choregraphie_time') : '20/30 min';
            $choreo_desc = $has_acf ? get_field('choregraphie_description') : '';
            $styles_mus = $has_acf ? get_field('styles_musiques') : '';
            $photo_tenue = $has_acf ? get_field('photo_tenue') : null;
            $tenue = $has_acf ? get_field('tenue') : '';

            /*
             * Mapping select ACF → label lisible.
             * ACF retourne la "value" de l'option (ex: "03day"), pas le "label".
             * On construit le libellé manuellement pour éviter toute dépendance à la config ACF.
             */
            // — Correspondance jour_de_cours value → label
            $jour_label = wamv1_get_day_label($jour_value);

            // — Horaires formatés
            $horaires = '';
            if ($heure_debut && $heure_fin) {
                $horaires = $heure_debut . ' – ' . $heure_fin;
            } elseif ($heure_debut) {
                $horaires = $heure_debut;
            }

            // — Professeurs : champ user (array), extraire display_name et lien vers profil wam_membre
            $prof_html_links = [];
            foreach ((array) $prof_cours as $prof) {
                $u_id = is_array($prof) ? ($prof['ID'] ?? null) : (is_object($prof) ? $prof->ID : null);
                $u_name = is_array($prof) ? ($prof['display_name'] ?? '') : (is_object($prof) ? $prof->display_name : '');

                if ($u_id) {
                    $member_posts = get_posts([
                        'post_type' => 'wam_membre',
                        'meta_query' => [
                            [
                                'key' => 'user_prof',
                                'value' => $u_id,
                                'compare' => '='
                            ]
                        ],
                        'posts_per_page' => 1
                    ]);

                    if ($member_posts) {
                        $prof_html_links[] = '<a href="' . get_permalink($member_posts[0]->ID) . '" class="prof-link">' . esc_html($u_name) . '</a>';
                    } else {
                        $prof_html_links[] = esc_html($u_name);
                    }
                }
            }

            // — Tarif : peut être un champ texte direct ("240€") ou un post_object lié.
            $tarif_label = is_string($tarif_obj) ? $tarif_obj : (($tarif_obj instanceof WP_Post) ? $tarif_obj->post_title : '');

            // — Vidéos : on filtre les URLs vides (champs non renseignés dans ACF)
            $videos = array_filter([$video_1, $video_2, $video_3]);

            // Stocké avant la boucle secondaire (WP_Query cours similaires) pour éviter
            // que get_the_ID() retourne l'ID d'un autre post après wp_reset_postdata().
            $current_id = get_the_ID();

            // Répertoire des icônes pour utilisation dans les styles inline
            $icon_dir = get_template_directory_uri() . '/assets/images/';
            ?>

            <!-- Breadcrumb : chemin Accueil > Cours > [Titre du cours] -->
            <?php get_template_part('template-parts/breadcrumb', null, [
                'id'   => 'breadcrumb-cours',
                'full' => true,
            ]); ?>

            <!-- ============ HERO : Infos cours ============ -->
            <?php
            $photo_id = has_post_thumbnail() ? get_post_thumbnail_id() : ($photo_cours ? $photo_cours['id'] : null);
            $has_sidebar = ($photo_id || $complet);
            ?>
            <div id="section-hero-cours" class="page-hero <?php echo !$has_sidebar ? 'page-hero--no-image' : ''; ?>">

                <!-- Photo (colonne masquée si ni image à la une, ni photo ACF, ni badge cours complet) -->
                <?php
                if ($has_sidebar):
                    ?>
                    <div id="section-hero-photo" class="page-hero__image">
                        <?php if ($photo_id): ?>
                            <?php echo wp_get_attachment_image($photo_id, 'wam-card', false, [
                                'class' => 'page-hero__image-img',
                                'data-no-overlay' => 'true' // Désactive le wrapper automatique de functions.php
                            ]); ?>
                            <div class="page-hero__image-overlay"></div>
                        <?php endif; ?>

                        <?php if ($complet): ?>
                            <!-- Badge cours complet -->
                            <div class="cours-complet">
                                <!-- Icône triste -->
                                <img src="<?php echo esc_url($icon_dir); ?>sad-emoji.svg"
                                     width="40" height="40" alt="" aria-hidden="true">
                                <div class="cours-complet__body">
                                    <p class="cours-complet__title">Cours complet</p>
                                    <p class="cours-complet__text">Malheureusement, ce cours est déjà rempli.</p>
                                    <a href="<?php echo esc_url($cours_listing_url ?: home_url('/')); ?>"
                                        class="cours-complet__link">
                                        <span>Voir tous les cours de danse</span>
                                        <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                                            <path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                                stroke-linejoin="round" />
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; // has_post_thumbnail || complet ?>

                <!-- Infos : titre, sous-titre, prof, carte infos, chips, description, CTAs -->
                <div id="section-hero-infos" class="page-hero__content">

                    <!-- Titre + sous-titre -->
                    <div class="page-hero__heading">
                        <?php if ($is_enfant): ?>
                            <h1 class="page-hero__title is-style-title-sign-lg page-hero__title--enfant">
                                <?php the_title(); ?>
                            </h1>
                        <?php else: ?>
                            <h1 class="page-hero__title is-style-title-sign-lg">
                                <?php the_title(); ?>
                            </h1>
                        <?php endif; ?>

                        <?php if ($sous_titre): ?>
                            <p class="page-hero__subtitle text-lg">
                                <?php echo esc_html($sous_titre); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <?php if ($prof_html_links): ?>
                        <p class="page-hero__profs text-sm">
                            <?php
                            $profs_string = implode(' et ', $prof_html_links);
                            echo 'avec ' . $profs_string;
                            ?>
                        </p>
                    <?php endif; ?>

                    <?php
                    /*
                     * Couleurs des icônes de la fiche cours.
                     * Variante enfant : chaque icône adopte sa propre couleur accent.
                     * Variante standard : toutes en icon-light (neutre).
                     */
                    $ic = $is_enfant ? [
                        'calendar' => 'var(--wam-color-green)',
                        'map' => 'var(--wam-color-orange)',
                        'piggybank' => 'var(--wam-color-pink)',
                        'thumbs' => 'var(--wam-color-yellow)',
                    ] : [
                        'calendar' => 'var(--wam-color-icon-light)',
                        'map' => 'var(--wam-color-icon-light)',
                        'piggybank' => 'var(--wam-color-icon-light)',
                        'thumbs' => 'var(--wam-color-icon-light)',
                    ];
                    ?>

                    <!-- Info card -->
                    <div class="cours-info-card">

                        <?php if ($jour_label): ?>
                            <div class="cours-info-card__row">
                                <span class="btn-icon"
                                    style="--icon-url: url('<?php echo esc_url($icon_dir); ?>calendar.svg'); --icon-size: 24px; color: <?php echo $ic['calendar']; ?>;"></span>
                                <div class="cours-info-card__cell">
                                    <p
                                        class="cours-info-card__day text-lg fw-bold <?php echo $is_enfant ? 'cours-info-card__day--enfant' : ''; ?>">
                                        <?php echo esc_html($jour_label); ?>
                                    </p>
                                    <?php if ($horaires): ?>
                                        <p class="cours-info-card__time text-md fw-bold">
                                            <?php echo esc_html($horaires); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (wam_adresse_visible()): ?>
                            <div class="cours-info-card__row wam-adresse-globale">
                                <span class="btn-icon"
                                    style="--icon-url: url('<?php echo esc_url($icon_dir); ?>map.svg'); --icon-size: 24px; color: <?php echo $ic['map']; ?>;"></span>
                                <div class="cours-info-card__cell">
                                    <p class="cours-info-card__lieu text-md"><?php echo esc_html(wam_nom_lieu()); ?></p>
                                    <p class="cours-info-card__adresse text-sm"><?php echo nl2br(esc_html(wam_adresse_lieu())); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($tarif_label): ?>
                            <div class="cours-info-card__row">
                                <span class="btn-icon"
                                    style="--icon-url: url('<?php echo esc_url($icon_dir); ?>piggy-bank.svg'); --icon-size: 24px; color: <?php echo $ic['piggybank']; ?>;"></span>
                                <p class="cours-info-card__tarif text-lg fw-bold">
                                    <?php echo esc_html($tarif_label); ?>
                                </p>
                            </div>
                        <?php endif; ?>

                        <?php if ($info_comp): ?>
                            <div class="cours-info-card__row">
                                <span class="btn-icon"
                                    style="--icon-url: url('<?php echo esc_url($icon_dir); ?>thumbs-up.svg'); --icon-size: 24px; color: <?php echo $ic['thumbs']; ?>;"></span>
                                <p class="cours-info-card__info text-md">
                                    <?php echo esc_html($info_comp); ?>
                                </p>
                            </div>
                        <?php endif; ?>

                    </div><!-- /info card -->

                    <?php if ($chips): ?>
                        <!-- Chips taxonomie -->
                        <div class="cours-chips">
                            <?php foreach ($chips as $chip): ?>
                                <a href="<?php echo esc_url($chip['link']); ?>" class="wam-chip">
                                    <span class="wam-chip__label text-sm">
                                        <?php echo esc_html($chip['name']); ?>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Description générale -->
                    <?php if ($description): ?>
                        <div class="cours-description wam-prose">
                            <?php echo wp_kses_post(wpautop($description)); ?>
                        </div>
                    <?php endif; ?>

                    <!-- CTAs — inscription + réserver un essai -->
                    <!-- Priorité : cours complet > inscriptions globalement fermées > bouton actif -->
                    <div id="section-ctas" class="cours-ctas">
                        <?php if ($complet): ?>
                            <!-- Inscription désactivée : cours complet -->
                            <div id="btn-inscription" class="btn-inscription btn-inscription--disabled">
                                <span class="btn-inscription__label"><?php echo wam_btn_inscription_texte(); ?></span>
                            </div>
                        <?php elseif (!wam_inscriptions_actives()): ?>
                            <!-- Inscription désactivée : globalement fermées (Réglages > Configuration WAM) -->
                            <div id="btn-inscription" class="btn-inscription btn-inscription--disabled">
                                <span class="btn-inscription__label"><?php echo wam_message_inscriptions_fermees(); ?></span>
                            </div>
                        <?php else: ?>
                            <a id="btn-inscription" href="<?php echo esc_url(wam_btn_inscription_url()); ?>" class="btn-primary btn-inscription">
                                <span class="btn-inscription__label"><?php echo wam_btn_inscription_texte(); ?></span>
                                <span class="btn-icon btn-icon--sm"
                                    style="--icon-url: url('<?php echo esc_url($icon_dir); ?>chevron-right.svg');"></span>
                            </a>
                        <?php endif; ?>

                    </div>

                </div><!-- /infos -->
            </div><!-- /hero -->

            <!-- Séparateur pattern danseurs (entre hero et sections) -->
            <?php get_template_part('template-parts/separator'); ?>

            <!-- ============ SECTIONS DESCRIPTION ============ -->
            <div id="section-description" class="cours-sections">

                <!-- Pédagogie : "Mais qu'apprend on en cours ?" -->
                <?php if ($pedagogie || $photo_cours): ?>
                    <div id="section-pedagogie" class="cours-section">
                        <div class="cours-section__col">
                            <div class="cours-section__heading">
                                <span class="btn-icon"
                                    style="--icon-url: url('<?php echo esc_url($icon_dir); ?>dancer_pedagogie.svg'); --icon-size: 72px; color: var(--wam-color-icon-light);"></span>
                                <h2 class="cours-section__title is-style-title-cool-md has-text-normal-color">
                                    Mais qu'apprend on en cours ?
                                </h2>
                            </div>
                            <?php if ($pedagogie): ?>
                                <div class="cours-section__text wam-prose text-md has-text-subtext-color">
                                    <?php echo wp_kses_post(wpautop($pedagogie)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($photo_cours): ?>
                            <div class="cours-section__photo">
                                <?php echo wp_get_attachment_image($photo_cours['id'], 'wam-card', false, [
                                    'class' => 'cours-section__photo-img',
                                    'alt' => esc_attr($photo_cours['alt']),
                                    'data-no-overlay' => 'true', // Désactive le wrapper auto
                                ]); ?>
                                <div class="cours-section__photo-overlay"></div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Déroulé : "Comment se déroule un cours ?" -->
                <?php if ($echauf_time || $exo_time || $choreo_time): ?>
                    <div id="section-deroulement" class="cours-deroulement">

                        <div class="cours-deroulement__heading">
                            <span class="btn-icon"
                                style="--icon-url: url('<?php echo esc_url($icon_dir); ?>dancer_deroulement.svg'); --icon-size: 72px; color: var(--wam-color-icon-light);"></span>
                            <h2 class="cours-deroulement__title is-style-title-cool-md has-text-normal-color">
                                Comment se déroule un cours ?
                            </h2>
                        </div>

                        <!-- 3 étapes -->
                        <div class="cours-etapes">

                            <!-- Échauffement — pill gauche -->
                            <div class="cours-etape cours-etape--left">
                                <div class="cours-etape__icon-wrap">
                                    <span class="btn-icon"
                                        style="--icon-url: url('<?php echo esc_url($icon_dir); ?>dancer_warmup.svg'); --icon-size: 82px; color: var(--wam-color-icon-light);"></span>
                                    <p class="cours-etape__time text-md has-text-normal-color">
                                        <?php echo esc_html($echauf_time); ?>
                                    </p>
                                </div>
                                <p class="cours-etape__title is-style-title-cool-md has-accent-yellow-color">Échauffement</p>
                                <?php if ($echauf_desc): ?>
                                    <p class="cours-etape__desc text-sm">
                                        <?php echo esc_html($echauf_desc); ?>
                                    </p>
                                <?php endif; ?>
                            </div>

                            <!-- Exercices — card normale -->
                            <div class="cours-etape">
                                <div class="cours-etape__icon-wrap">
                                    <span class="btn-icon"
                                        style="--icon-url: url('<?php echo esc_url($icon_dir); ?>dancer_exotech.svg'); --icon-size: 80px; color: var(--wam-color-icon-light);"></span>
                                    <p class="cours-etape__time text-md has-text-normal-color">
                                        <?php echo esc_html($exo_time); ?>
                                    </p>
                                </div>
                                <p class="cours-etape__title is-style-title-cool-md has-accent-yellow-color">Exercices
                                    techniques</p>
                                <?php if ($exo_desc): ?>
                                    <p class="cours-etape__desc text-sm">
                                        <?php echo esc_html($exo_desc); ?>
                                    </p>
                                <?php endif; ?>
                            </div>

                            <!-- Chorégraphie — pill droite -->
                            <div class="cours-etape cours-etape--right">
                                <div class="cours-etape__icon-wrap">
                                    <span class="btn-icon"
                                        style="--icon-url: url('<?php echo esc_url($icon_dir); ?>dancer_chore.svg'); --icon-size: 103px; color: var(--wam-color-icon-light);"></span>
                                    <p class="cours-etape__time text-md has-text-normal-color">
                                        <?php echo esc_html($choreo_time); ?>
                                    </p>
                                </div>
                                <p class="cours-etape__title is-style-title-cool-md has-accent-yellow-color">Chorégraphie</p>
                                <?php if ($choreo_desc): ?>
                                    <p class="cours-etape__desc text-sm">
                                        <?php echo esc_html($choreo_desc); ?>
                                    </p>
                                <?php endif; ?>
                            </div>

                        </div><!-- /3 étapes -->

                        <?php if ($styles_mus): ?>
                            <div class="cours-musiques">
                                <span class="btn-icon"
                                    style="--icon-url: url('<?php echo esc_url($icon_dir); ?>musicnote.svg'); --icon-size: 46px; color: var(--wam-color-icon-light);"></span>
                                <p class="cours-musiques__text text-md has-text-normal-color">
                                    <strong>Styles de musiques :</strong>
                                    <?php echo ' ' . esc_html($styles_mus); ?>
                                </p>
                            </div>
                        <?php endif; ?>

                    </div>
                <?php endif; ?>

                <!-- Tenue : "Quelle tenue ?" -->
                <?php if ($tenue || $photo_tenue): ?>
                    <div id="section-tenue" class="cours-section cours-section--tenue">
                        <div class="cours-section__col">
                            <div class="cours-section__heading">
                                <span class="btn-icon"
                                    style="--icon-url: url('<?php echo esc_url($icon_dir); ?>dancer_tenue.svg'); --icon-size: 80px; color: var(--wam-color-icon-light);"></span>
                                <h2 class="cours-section__title is-style-title-cool-md has-text-normal-color">Quelle tenue ?
                                </h2>
                            </div>
                            <?php if ($tenue): ?>
                                <div class="cours-section__text wam-prose text-md has-text-subtext-color">
                                    <?php echo wp_kses_post(wpautop($tenue)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="cours-section__photo cours-section__photo--tenue">
                            <?php
                            if ($photo_tenue):
                                echo wp_get_attachment_image($photo_tenue['id'], 'wam-card', false, [
                                    'class' => 'cours-section__photo-img',
                                    'alt' => esc_attr($photo_tenue['alt']),
                                    'data-no-overlay' => 'true', // Désactive le wrapper auto
                                ]);
                            else: ?>
                                <img src="<?php echo esc_url($icon_dir . 'photodefaulttenue.jpg'); ?>"
                                    class="cours-section__photo-img" alt="Tenue par défaut">
                            <?php endif; ?>
                            <div class="cours-section__photo-overlay"></div>
                        </div>
                    </div>
                <?php endif; ?>

            </div><!-- /description sections -->

            <!-- ============ VIDÉOS ============ -->
            <?php if ($videos): ?>
                <div id="section-videos" class="cours-videos">
                    <?php
                    /*
                     * wp_oembed_get() contacte le provider (YouTube, Vimeo…) et retourne
                     * le HTML de l'iframe. Retourne false si l'URL n'est pas supportée.
                     * On passe 'width' pour que WordPress calcule la hauteur en respectant
                     * le ratio 16:9 (aspect-video en CSS fait le reste).
                     */
                    foreach ($videos as $video_url):
                        $embed = wp_oembed_get(esc_url($video_url), ['width' => 1200]);
                        if ($embed): ?>
                            <div class="cours-video">
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
         * Inclusion du template réutilisable 'related-content'
         * Il gère automatiquement la WP_Query et l'affichage de la grille
         * pour des cours similaires (triés aléatoirement, hors cours courant).
         */
        get_template_part('template-parts/related-content');
        ?>

    </div>
</main>

<?php get_footer(); ?>