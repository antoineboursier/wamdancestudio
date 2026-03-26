<?php
/**
 * WAM — Schémas JSON-LD (Schema.org)
 *
 * Stratégie :
 *   - On ne remplace JAMAIS Yoast — on enrichit uniquement.
 *   - Yoast gère : WebSite, WebPage, BreadcrumbList, Article, Organization de base.
 *   - Ce fichier ajoute : DanceSchool (filtre Yoast), Course, Event, Person, Service.
 *   - Chaque nœud est injecté via <script type="application/ld+json"> dans wp_head (priority 5).
 *   - Les données viennent exclusivement d'ACF (guard function_exists) + WP natif.
 *
 * Champs ACF réels utilisés :
 *   cours      → sous_titre, prof_cours (user multi), jour_de_cours, heure_debut,
 *                heure_de_fin, tarif_cours (post_object), complete_cours, description_cours
 *   stages     → sous_titre, description, date_stage (d/m/Y), heure_debut, heure_de_fin,
 *                tarifs{tarif_1,tarif_2,tarif_3}, intervenant·e{stage_intervenant_inout,
 *                stage_intervenant (user), stage_intervenant_out}, complete_cours
 *   wam_membre → micro_description_prof, description_prof, user_prof (user),
 *                reseaux_sociaux_prof{instagram_link_prof, facebook_link_prof,
 *                tiktok_link_prof, linkedin_link_prof}
 *
 * @package wamv1
 */

// =========================================================================
// 1. YOAST — Corriger Organization → DanceSchool
// =========================================================================

/**
 * Yoast génère un nœud Organization dans son @graph.
 * On y ajoute DanceSchool (sous-type de LocalBusiness > EntertainmentBusiness).
 * Yoast conserve tous ses autres champs (address, logo, sameAs, url…).
 */
add_filter( 'wpseo_schema_organization', 'wamv1_schema_fix_organization_type' );

if ( ! function_exists( 'wamv1_schema_fix_organization_type' ) ) {
    function wamv1_schema_fix_organization_type( array $data ): array {
        $data['@type'] = [ 'Organization', 'DanceSchool' ];
        return $data;
    }
}

// =========================================================================
// 2. HOOK PRINCIPAL — injection JSON-LD selon contexte
// =========================================================================

add_action( 'wp_head', 'wamv1_inject_schema_jsonld', 5 );

if ( ! function_exists( 'wamv1_inject_schema_jsonld' ) ) {
    function wamv1_inject_schema_jsonld(): void {
        if ( is_admin() ) return;

        $schema = null;

        if ( is_singular( 'cours' ) ) {
            $schema = wamv1_schema_cours();
        } elseif ( is_singular( 'stages' ) ) {
            $schema = wamv1_schema_stage();
        } elseif ( is_singular( 'wam_membre' ) ) {
            $schema = wamv1_schema_membre();
        } elseif ( is_singular( 'post' ) ) {
            $schema = wamv1_schema_article_author();
        } elseif ( is_page() ) {
            $schema = wamv1_schema_page_service();
        }

        if ( ! $schema ) return;

        /*
         * Si $schema est une liste de nœuds (tableau indexé), on l'emballe dans @graph.
         * Sinon on l'inclut directement comme objet unique avec @context.
         */
        $is_graph = array_is_list( $schema );
        $output   = $is_graph
            ? [ '@context' => 'https://schema.org', '@graph' => $schema ]
            : array_merge( [ '@context' => 'https://schema.org' ], $schema );

        echo '<script type="application/ld+json">' . "\n";
        echo wp_json_encode( $output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
        echo "\n</script>\n";
    }
}

// =========================================================================
// 3. HELPERS RÉUTILISABLES
// =========================================================================

/**
 * Nœud DanceSchool minimal — utilisé comme référence dans d'autres schémas.
 * @id pointe vers l'Organization Yoast pour relier les graphes.
 */
if ( ! function_exists( 'wamv1_schema_organization_ref' ) ) {
    function wamv1_schema_organization_ref(): array {
        return [
            '@type' => [ 'Organization', 'DanceSchool' ],
            '@id'   => home_url( '/#organization' ),
            'name'  => 'WAM Dance Studio',
            'url'   => home_url( '/' ),
        ];
    }
}

/**
 * Place fixe WAM — réutilisé dans Course et Event.
 */
if ( ! function_exists( 'wamv1_schema_place_wam' ) ) {
    function wamv1_schema_place_wam(): array {
        return [
            '@type'   => 'Place',
            'name'    => 'WAM Dance Studio',
            'address' => [
                '@type'           => 'PostalAddress',
                'streetAddress'   => '202 rue Jean Jaurès',
                'addressLocality' => "Villeneuve-d'Ascq",
                'postalCode'      => '59491',
                'addressCountry'  => 'FR',
            ],
        ];
    }
}

/**
 * Convertit le slug jour ACF ("01day"…"07day") → URI DayOfWeek schema.org.
 */
if ( ! function_exists( 'wamv1_schema_day_of_week' ) ) {
    function wamv1_schema_day_of_week( string $slug ): string {
        $map = [
            '01day' => 'https://schema.org/Monday',
            '02day' => 'https://schema.org/Tuesday',
            '03day' => 'https://schema.org/Wednesday',
            '04day' => 'https://schema.org/Thursday',
            '05day' => 'https://schema.org/Friday',
            '06day' => 'https://schema.org/Saturday',
            '07day' => 'https://schema.org/Sunday',
        ];
        return $map[ $slug ] ?? '';
    }
}

/**
 * Convertit une heure ACF ("12h30", "9h", "9h00") → format ISO 8601 ("12:30:00").
 * Même logique que wamv1_time_to_min() dans page-planning-cours.php.
 */
if ( ! function_exists( 'wamv1_schema_time_iso' ) ) {
    function wamv1_schema_time_iso( string $time_str ): string {
        if ( strpos( $time_str, 'h' ) !== false ) {
            $parts = explode( 'h', trim( $time_str ) );
            $h     = str_pad( intval( $parts[0] ), 2, '0', STR_PAD_LEFT );
            $m     = str_pad( intval( $parts[1] ?? 0 ), 2, '0', STR_PAD_LEFT );
            return "{$h}:{$m}:00";
        }
        return $time_str;
    }
}

/**
 * Convertit une date ACF au format d/m/Y → ISO 8601 (Y-m-d).
 * Le champ date_stage est configuré avec format de retour d/m/Y dans ACF.
 */
if ( ! function_exists( 'wamv1_schema_date_iso' ) ) {
    function wamv1_schema_date_iso( string $date_dmY ): string {
        $dt = DateTime::createFromFormat( 'd/m/Y', $date_dmY );
        return $dt ? $dt->format( 'Y-m-d' ) : '';
    }
}

/**
 * Construit un nœud Person depuis un tableau user ACF (champ type "user").
 * ACF user field retourne un tableau avec 'ID', 'display_name', 'user_email'…
 *
 * @param array  $user       Tableau user ACF.
 * @param string $id_suffix  Si fourni, ajoute un @id unique au nœud.
 */
if ( ! function_exists( 'wamv1_schema_person_from_acf_user' ) ) {
    function wamv1_schema_person_from_acf_user( array $user, string $id_suffix = '' ): array {
        $name = $user['display_name']
            ?? trim( ( $user['first_name'] ?? '' ) . ' ' . ( $user['last_name'] ?? '' ) )
            ?: '';

        $person = [
            '@type' => 'Person',
            'name'  => $name,
        ];

        if ( $id_suffix ) {
            $person['@id'] = home_url( '/#person-' . sanitize_title( $name ) );
        }

        return $person;
    }
}

// =========================================================================
// 4a. COURS — Course + Schedule + Offer + Person (instructor)
// =========================================================================

if ( ! function_exists( 'wamv1_schema_cours' ) ) {
    function wamv1_schema_cours(): ?array {
        if ( ! function_exists( 'get_field' ) ) return null;

        $post_id     = get_the_ID();
        $title       = get_the_title();
        $permalink   = get_permalink();
        $desc        = get_field( 'description_cours', $post_id ) ?: get_the_excerpt();
        $sous_titre  = get_field( 'sous_titre',        $post_id ) ?: '';
        $jour        = get_field( 'jour_de_cours',     $post_id ) ?: '';
        $heure_debut = get_field( 'heure_debut',       $post_id ) ?: '';
        $heure_fin   = get_field( 'heure_de_fin',      $post_id ) ?: '';
        $tarif_obj   = get_field( 'tarif_cours',       $post_id );   // WP_Post|null (post_object)
        $prof_cours  = get_field( 'prof_cours',        $post_id ) ?: []; // user (multi) → array d'arrays
        $complet     = get_field( 'complete_cours',    $post_id );

        $schema = [
            '@type'    => 'Course',
            '@id'      => $permalink . '#course',
            'name'     => $title,
            'url'      => $permalink,
            'provider' => wamv1_schema_organization_ref(),
            'location' => wamv1_schema_place_wam(),
        ];

        if ( $desc ) {
            $schema['description'] = wp_strip_all_tags( $desc );
        }
        if ( $sous_titre ) {
            $schema['abstract'] = $sous_titre;
        }

        // Image mise en avant
        if ( has_post_thumbnail( $post_id ) ) {
            $schema['image'] = get_the_post_thumbnail_url( $post_id, 'wam-card' );
        }

        /* ---- Schedule (jour + horaires) ---- */
        if ( $jour && $heure_debut && $heure_fin ) {
            $day_uri = wamv1_schema_day_of_week( $jour );
            $schedule = [
                '@type'            => 'Schedule',
                'startTime'        => wamv1_schema_time_iso( $heure_debut ),
                'endTime'          => wamv1_schema_time_iso( $heure_fin ),
                'scheduleTimezone' => 'Europe/Paris',
            ];
            if ( $day_uri ) {
                $schedule['byDay'] = $day_uri;
            }
            $schema['courseSchedule'] = $schedule;
        }

        /* ---- Instructor — prof_cours est un user field multi ---- */
        if ( ! empty( $prof_cours ) ) {
            // Normaliser : un seul user retourne aussi un array, mais au premier niveau
            // Si c'est un array d'arrays (multi), chaque item a 'ID'
            $users = isset( $prof_cours['ID'] ) ? [ $prof_cours ] : (array) $prof_cours;
            $instructors = [];
            foreach ( $users as $u ) {
                if ( is_array( $u ) && isset( $u['display_name'] ) ) {
                    $instructors[] = wamv1_schema_person_from_acf_user( $u );
                }
            }
            if ( $instructors ) {
                $schema['instructor'] = count( $instructors ) === 1 ? $instructors[0] : $instructors;
            }
        }

        /* ---- Offer — tarif_cours est un post_object (WP_Post) ---- */
        $offer = [
            '@type'        => 'Offer',
            'url'          => $permalink,
            'seller'       => wamv1_schema_organization_ref(),
            'priceCurrency' => 'EUR',
            'availability' => $complet
                ? 'https://schema.org/SoldOut'
                : 'https://schema.org/InStock',
        ];
        // Le post_title du post Tarif est le libellé (ex. "40€/trimestre")
        if ( $tarif_obj instanceof WP_Post && $tarif_obj->post_title ) {
            $offer['name'] = $tarif_obj->post_title;
        }
        $schema['offers'] = $offer;

        /* ---- Public cible (enfants / adultes via helper thème) ---- */
        if ( function_exists( 'wamv1_is_enfant_variant' ) && wamv1_is_enfant_variant() ) {
            $schema['audience'] = [
                '@type'        => 'EducationalAudience',
                'audienceType' => 'Enfants',
            ];
        }

        return $schema;
    }
}

// =========================================================================
// 4b. STAGE — Event + Place + Offer + Person (performer)
// =========================================================================

if ( ! function_exists( 'wamv1_schema_stage' ) ) {
    function wamv1_schema_stage(): ?array {
        if ( ! function_exists( 'get_field' ) ) return null;

        $post_id          = get_the_ID();
        $title            = get_the_title();
        $permalink        = get_permalink();
        $desc             = get_field( 'description',    $post_id ) ?: get_the_excerpt();
        $sous_titre       = get_field( 'sous_titre',     $post_id ) ?: '';
        $date_raw         = get_field( 'date_stage',     $post_id ) ?: ''; // retourne d/m/Y
        $heure_debut      = get_field( 'heure_debut',    $post_id ) ?: '';
        $heure_fin        = get_field( 'heure_de_fin',   $post_id ) ?: '';
        $tarifs_grp       = get_field( 'tarifs',         $post_id );  // groupe {tarif_1, tarif_2, tarif_3}
        $intervenant_grp  = get_field( 'intervenant·e',  $post_id );  // groupe {stage_intervenant_inout, stage_intervenant, stage_intervenant_out}
        $complet          = get_field( 'complete_cours', $post_id );
        $type_format      = get_field( 'type_format',    $post_id ) ?: 'type_stage';

        // Mapper type_format → sous-type Event schema.org
        $event_type_map = [
            'type_stage' => 'Event',
            'type_atel'  => 'Event',   // Atelier
            'type_wshop' => 'Event',   // Workshop — pas de sous-type plus précis disponible
        ];
        $event_type = $event_type_map[ $type_format ] ?? 'Event';

        $schema = [
            '@type'               => $event_type,
            '@id'                 => $permalink . '#event',
            'name'                => $title,
            'url'                 => $permalink,
            'location'            => wamv1_schema_place_wam(),
            'organizer'           => wamv1_schema_organization_ref(),
            'eventStatus'         => $complet
                ? 'https://schema.org/EventFull'
                : 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        ];

        if ( $desc ) {
            $schema['description'] = wp_strip_all_tags( $desc );
        }
        if ( $sous_titre ) {
            $schema['abstract'] = $sous_titre;
        }

        /* ---- Dates — ACF retourne d/m/Y, schema.org attend ISO 8601 ---- */
        if ( $date_raw ) {
            $date_iso = wamv1_schema_date_iso( $date_raw );
            if ( $date_iso ) {
                $schema['startDate'] = $heure_debut
                    ? $date_iso . 'T' . wamv1_schema_time_iso( $heure_debut )
                    : $date_iso;
                $schema['endDate'] = $heure_fin
                    ? $date_iso . 'T' . wamv1_schema_time_iso( $heure_fin )
                    : $date_iso;
            }
        }

        // Image mise en avant
        if ( has_post_thumbnail( $post_id ) ) {
            $schema['image'] = get_the_post_thumbnail_url( $post_id, 'wam-card' );
        }

        /* ---- Performer — groupe intervenant·e ----
         * stage_intervenant_inout : 'true' (prof WAM) | 'false' (intervenant externe)
         * stage_intervenant       : user ACF (array avec 'ID', 'display_name')
         * stage_intervenant_out   : text (nom de l'intervenant externe)
         */
        if ( $intervenant_grp && is_array( $intervenant_grp ) ) {
            $in_out = $intervenant_grp['stage_intervenant_inout'] ?? 'false';

            if ( $in_out === 'true' ) {
                // Intervenant interne WAM — champ user ACF
                $u = $intervenant_grp['stage_intervenant'] ?? null;
                if ( $u && is_array( $u ) && isset( $u['display_name'] ) ) {
                    $schema['performer'] = wamv1_schema_person_from_acf_user( $u );
                    // Lier au nœud Person si un profil wam_membre existe
                    $member_posts = get_posts( [
                        'post_type'      => 'wam_membre',
                        'posts_per_page' => 1,
                        'post_status'    => 'publish',
                        'meta_query'     => [ [ 'key' => 'user_prof', 'value' => $u['ID'] ] ],
                    ] );
                    if ( $member_posts ) {
                        $schema['performer']['url'] = get_permalink( $member_posts[0]->ID );
                    }
                }
            } else {
                // Intervenant externe (hors WAM)
                $u_name = $intervenant_grp['stage_intervenant_out'] ?? '';
                if ( $u_name ) {
                    $schema['performer'] = [
                        '@type' => 'Person',
                        'name'  => $u_name,
                    ];
                }
            }
        }

        /* ---- Offers — groupe tarifs {tarif_1, tarif_2, tarif_3} ----
         * Chaque sous-champ est un texte libre ("40€ adhérent", "50€ extérieur"…).
         * Schema.org Offer attend un prix numérique — on expose le name uniquement.
         */
        if ( $tarifs_grp && is_array( $tarifs_grp ) ) {
            $offers = [];
            foreach ( [ 'tarif_1', 'tarif_2', 'tarif_3' ] as $key ) {
                if ( ! empty( $tarifs_grp[ $key ] ) ) {
                    $offers[] = [
                        '@type'        => 'Offer',
                        'name'         => $tarifs_grp[ $key ],
                        'url'          => $permalink,
                        'priceCurrency' => 'EUR',
                        'seller'       => wamv1_schema_organization_ref(),
                        'availability' => $complet
                            ? 'https://schema.org/SoldOut'
                            : 'https://schema.org/InStock',
                    ];
                }
            }
            if ( $offers ) {
                $schema['offers'] = count( $offers ) === 1 ? $offers[0] : $offers;
            }
        }

        return $schema;
    }
}

// =========================================================================
// 4c. WAM_MEMBRE — Person + worksFor + sameAs
// =========================================================================

if ( ! function_exists( 'wamv1_schema_membre' ) ) {
    function wamv1_schema_membre(): ?array {
        if ( ! function_exists( 'get_field' ) ) return null;

        $post_id    = get_the_ID();
        $name       = get_the_title();
        $permalink  = get_permalink();
        $micro_desc = get_field( 'micro_description_prof', $post_id ) ?: '';
        $full_desc  = get_field( 'description_prof',       $post_id ) ?: '';
        $reseaux    = get_field( 'reseaux_sociaux_prof',   $post_id ) ?: [];

        $schema = [
            '@type'    => 'Person',
            '@id'      => $permalink . '#person',
            'name'     => $name,
            'url'      => $permalink,
            'worksFor' => wamv1_schema_organization_ref(),
        ];

        // Description : micro en priorité, puis biographie longue
        $description = $micro_desc ?: $full_desc;
        if ( $description ) {
            $schema['description'] = wp_strip_all_tags( $description );
        }

        // Photo profil
        if ( has_post_thumbnail( $post_id ) ) {
            $schema['image'] = get_the_post_thumbnail_url( $post_id, 'wam-portrait' );
        }

        /* ---- sameAs — groupe reseaux_sociaux_prof ----
         * Sous-champs : instagram_link_prof, facebook_link_prof,
         *               tiktok_link_prof, linkedin_link_prof
         */
        if ( is_array( $reseaux ) ) {
            $same_as = [];
            $reseau_keys = [
                'instagram_link_prof',
                'facebook_link_prof',
                'tiktok_link_prof',
                'linkedin_link_prof',
            ];
            foreach ( $reseau_keys as $key ) {
                $url = $reseaux[ $key ] ?? '';
                if ( $url && filter_var( $url, FILTER_VALIDATE_URL ) ) {
                    $same_as[] = esc_url_raw( $url );
                }
            }
            if ( $same_as ) {
                $schema['sameAs'] = $same_as;
            }
        }

        return $schema;
    }
}

// =========================================================================
// 4d. ARTICLE — enrichissement auteur (Person lié à un wam_membre)
//     Yoast gère déjà Article + WebPage — on ne les touche pas.
// =========================================================================

if ( ! function_exists( 'wamv1_schema_article_author' ) ) {
    function wamv1_schema_article_author(): ?array {
        $author_wp_id = (int) get_the_author_meta( 'ID' );
        if ( ! $author_wp_id ) return null;

        // Chercher un profil wam_membre lié à ce compte WP
        // ACF user field stocke l'ID WP en base (meta_value = user ID)
        $member_posts = get_posts( [
            'post_type'      => 'wam_membre',
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'no_found_rows'  => true,
            'meta_query'     => [ [ 'key' => 'user_prof', 'value' => $author_wp_id ] ],
        ] );

        if ( ! $member_posts ) {
            // Pas de profil WAM — Person générique depuis WP
            return [
                '@type' => 'Person',
                'name'  => get_the_author(),
                'url'   => get_author_posts_url( $author_wp_id ),
            ];
        }

        // Profil WAM trouvé — réutiliser wamv1_schema_membre() sur ce post
        $saved_post = $GLOBALS['post'] ?? null;
        $GLOBALS['post'] = $member_posts[0]; // swap temporaire pour get_the_ID() interne
        setup_postdata( $member_posts[0] );

        $schema = wamv1_schema_membre();

        // Restaurer le post courant
        if ( $saved_post ) {
            $GLOBALS['post'] = $saved_post;
            setup_postdata( $saved_post );
        }

        return $schema;
    }
}

// =========================================================================
// 4e. PAGES DE SERVICE — Service + areaServed
//     Uniquement sur les pages identifiées comme services WAM.
// =========================================================================

if ( ! function_exists( 'wamv1_schema_page_service' ) ) {
    function wamv1_schema_page_service(): ?array {
        $slug = get_post_field( 'post_name', get_queried_object_id() );

        $service_map = [
            'cours-collectifs' => [
                'name' => 'Cours collectifs de danse',
                'desc' => 'Cours hebdomadaires adultes et enfants : danse moderne, salon, WCS, street jazz, orientale, latino.',
            ],
            'stages-workshop-ateliers' => [
                'name' => 'Stages, Workshops & Ateliers de danse',
                'desc' => 'Stages ponctuels et ateliers thématiques tous niveaux, enfants et adultes.',
            ],
            'choregraphie-de-mariage-ouvertures-de-bal' => [
                'name' => 'Chorégraphie de mariage & ouverture de bal',
                'desc' => 'Formules sur mesure pour préparer une ouverture de bal : solo, duo, groupe.',
            ],
            'prof-wam' => [
                'name' => "Professeur·es WAM Dance Studio",
                'desc' => 'Équipe pédagogique de professeurs diplômés d\'État.',
            ],
            'planning' => [
                'name' => 'Planning hebdomadaire des cours WAM',
                'desc' => 'Grille horaire de tous les cours collectifs de la semaine.',
            ],
        ];

        if ( ! isset( $service_map[ $slug ] ) ) return null;

        $service_data = $service_map[ $slug ];

        return [
            '@type'      => 'Service',
            '@id'        => get_permalink() . '#service',
            'name'       => $service_data['name'],
            'description' => $service_data['desc'],
            'url'        => get_permalink(),
            'provider'   => wamv1_schema_organization_ref(),
            'serviceType' => 'École de danse',
            'areaServed' => [
                '@type'  => 'AdministrativeArea',
                'name'   => "Villeneuve-d'Ascq, Roubaix, Wasquehal, Croix, Hem, Lille",
                'containedInPlace' => [
                    '@type' => 'AdministrativeArea',
                    'name'  => 'Métropole Européenne de Lille',
                ],
            ],
        ];
    }
}
