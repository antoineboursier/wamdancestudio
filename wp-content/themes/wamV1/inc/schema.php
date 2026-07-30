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
 *                heure_de_fin, tarif_cours (text — ex: "250€"), complete_cours, description_cours
 *   stages     → sous_titre, description, date_stage (d/m/Y), heure_debut, heure_de_fin,
 *                tarifs{tarif_1,tarif_2,tarif_3}, intervenant·e{stage_intervenant_inout,
 *                stage_intervenant (user), stage_intervenant_out}, complete_cours
 *   wam_membre → micro_description_prof, description_prof, user_prof (user),
 *                reseaux_sociaux_prof{instagram_link_prof, facebook_link_prof,
 *                tiktok_link_prof, linkedin_link_prof}
 *
 * @package wamv1
 */

// Polyfill pour PHP < 8.1 (compatibilité avec les versions antérieures en production)
if ( ! function_exists( 'array_is_list' ) ) {
    function array_is_list( array $arr ): bool {
        if ( $arr === [] ) {
            return true;
        }
        return array_keys( $arr ) === range( 0, count( $arr ) - 1 );
    }
}

// =========================================================================
// 1. YOAST — Corriger Organization → DanceSchool
// =========================================================================

/**
 * Yoast génère un nœud Organization dans son @graph.
 * On y ajoute DanceSchool (sous-type de LocalBusiness > EntertainmentBusiness)
 * et on complète les champs recommandés d'un LocalBusiness que Yoast ne fournit
 * pas nativement (priceRange, address structurée, image). On n'écrase JAMAIS une
 * valeur déjà présente : chaque champ n'est ajouté que s'il est absent/vide.
 * Yoast conserve tous ses autres champs (logo, sameAs, url…).
 */
add_filter( 'wpseo_schema_organization', 'wamv1_schema_fix_organization_type' );

if ( ! function_exists( 'wamv1_schema_fix_organization_type' ) ) {
    function wamv1_schema_fix_organization_type( array $data ): array {
        $data['@type'] = [ 'Organization', 'DanceSchool' ];

        // priceRange — fourchette indicative (réservation à l'heure → cours annuel).
        if ( empty( $data['priceRange'] ) ) {
            $data['priceRange'] = '35–285€';
        }

        // address — réutilise l'adresse structurée déjà définie pour le lieu WAM.
        if ( empty( $data['address'] ) && function_exists( 'wamv1_schema_place_wam' ) ) {
            $place = wamv1_schema_place_wam();
            if ( ! empty( $place['address'] ) ) {
                $data['address'] = $place['address'];
            }
        }

        // image — logo du site (custom_logo puis site_icon). Absent si aucun défini.
        if ( empty( $data['image'] ) ) {
            $logo_id = (int) get_theme_mod( 'custom_logo' );
            if ( ! $logo_id ) {
                $logo_id = (int) get_option( 'site_icon' );
            }
            if ( $logo_id ) {
                $img_url = wp_get_attachment_image_url( $logo_id, 'full' );
                if ( $img_url ) {
                    $data['image'] = $img_url;
                }
            }
        }

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
            $schema = wamv1_schema_merge_with_faq( wamv1_schema_article_author() );
        } elseif ( is_tax( 'cat_cours' ) ) {
            $schema = wamv1_schema_cat_cours();
        } elseif ( is_page( 'tarifs' ) ) {
            // Page de prix plutôt que page de service : un OfferCatalog est plus
            // exploitable qu'un Service, et la FAQ y est rendue en PHP.
            $schema = wamv1_schema_merge_with_faq( wamv1_schema_tarifs() );
        } elseif ( is_page() ) {
            $schema = wamv1_schema_merge_with_faq( wamv1_schema_page_service() );
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
 * Nom du studio, avec repli garanti non vide.
 *
 * wam_nom_lieu() (wam-custom-plugin) EXISTE mais retourne une chaîne vide tant
 * que l'option n'est pas renseignée — un simple function_exists() ne suffit donc
 * pas : sans ce repli, les nœuds Place et Organization sortaient avec "name": "".
 */
if ( ! function_exists( 'wamv1_schema_nom_studio' ) ) {
    function wamv1_schema_nom_studio(): string {
        $nom = function_exists( 'wam_nom_lieu' ) ? trim( (string) wam_nom_lieu() ) : '';
        if ( ! $nom ) {
            $nom = trim( (string) get_bloginfo( 'name' ) );
        }
        return $nom ?: 'WAM Dance Studio';
    }
}

/**
 * Nettoie un texte destiné au JSON-LD : suppression des balises, décodage des
 * entités HTML (&nbsp;, &#8211;, &rsquo;…) et normalisation des espaces.
 * Sans décodage, les entités ressortent telles quelles dans les données
 * structurées et polluent la lecture par les moteurs.
 */
if ( ! function_exists( 'wamv1_schema_text' ) ) {
    function wamv1_schema_text( $texte, int $max = 0 ): string {
        if ( ! is_string( $texte ) || $texte === '' ) return '';

        $texte = wp_strip_all_tags( $texte );
        $texte = html_entity_decode( $texte, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        // Espaces insécables issus du décodage de &nbsp; puis espaces multiples.
        $texte = str_replace( "\xC2\xA0", ' ', $texte );
        $texte = trim( preg_replace( '/\s+/u', ' ', $texte ) );

        if ( $max > 0 && function_exists( 'mb_strlen' ) && mb_strlen( $texte, 'UTF-8' ) > $max ) {
            $texte = rtrim( mb_substr( $texte, 0, $max, 'UTF-8' ) ) . '…';
        }

        return $texte;
    }
}

/**
 * Référence vers l'Organization du site — utilisée comme provider / organizer /
 * seller / worksFor dans les autres schémas (jamais comme nœud autonome).
 *
 * Quand Yoast est actif, il publie déjà un nœud complet portant le même @id
 * (nom, logo, sameAs, priceRange, adresse…). Les consommateurs de JSON-LD
 * fusionnent les nœuds de même @id : redéclarer name/url ici produisait des
 * propriétés en double (« Champ url en double » au Rich Results Test).
 * On n'émet donc qu'une **référence pure** et Yoast reste seul propriétaire du
 * contenu du nœud.
 *
 * Sans Yoast, plus personne ne décrit l'Organization : on retombe alors sur un
 * nœud minimal complet pour que le graphe reste autoportant.
 */
if ( ! function_exists( 'wamv1_schema_organization_ref' ) ) {
    function wamv1_schema_organization_ref(): array {
        $ref = [ '@id' => home_url( '/#organization' ) ];

        if ( defined( 'WPSEO_VERSION' ) ) {
            return $ref;
        }

        return $ref + [
            '@type' => [ 'Organization', 'DanceSchool' ],
            'name'  => wamv1_schema_nom_studio(),
            'url'   => home_url( '/' ),
        ];
    }
}

/**
 * Place fixe WAM — réutilisé dans Course et Event.
 */
if ( ! function_exists( 'wamv1_schema_place_wam' ) ) {
    function wamv1_schema_place_wam(): array {
        $nom = wamv1_schema_nom_studio();
        // On ne garde que la 1re ligne (la rue) : addressLocality porte déjà la ville.
        // Décodage des entités HTML pour un JSON-LD propre (ex: &#039; → ').
        $adresse_brute = function_exists('wam_adresse_lieu') ? wam_adresse_lieu() : "202 rue Jean Jaurès\nVilleneuve-d'Ascq";
        $adresse = html_entity_decode( trim( strtok( $adresse_brute, "\n" ) ), ENT_QUOTES, 'UTF-8' );
        return [
            '@type'   => 'Place',
            'name'    => $nom,
            'address' => [
                '@type'           => 'PostalAddress',
                'streetAddress'   => $adresse,
                'addressLocality' => 'Villeneuve-d\'Ascq',
                'postalCode'      => '59650',
                'addressRegion'   => 'Nord',
                'addressCountry'  => 'FR',
            ],
            'geo' => [
                '@type'     => 'GeoCoordinates',
                'latitude'  => 50.665313,
                'longitude' => 3.141649,
            ],
            'hasMap' => 'https://www.google.com/maps/search/?api=1&query=WAM+Dance+Studio+Villeneuve+d\'Ascq',
            'openingHoursSpecification' => [
                [
                    '@type'     => 'OpeningHoursSpecification',
                    'dayOfWeek' => [
                        'https://schema.org/Monday',
                        'https://schema.org/Tuesday',
                        'https://schema.org/Wednesday',
                        'https://schema.org/Thursday',
                        'https://schema.org/Friday',
                        'https://schema.org/Saturday'
                    ],
                    'opens'  => '09:00',
                    'closes' => '22:00'
                ],
                [
                    '@type'     => 'OpeningHoursSpecification',
                    'dayOfWeek' => 'https://schema.org/Sunday',
                    'opens'  => '13:00',
                    'closes' => '22:00'
                ]
            ]
        ];
    }
}

/**
 * Zone desservie par un service, sous forme de liste de nœuds City.
 *
 * Remplace la chaîne unique « Villeneuve-d'Ascq, Roubaix, Wasquehal… » qui
 * agglomérait toutes les communes dans un seul `name` : des City distinctes sont
 * nettement plus exploitables, et surtout la zone peut désormais être
 * **spécifique à la page** — c'est tout l'intérêt des pages géo.
 *
 * @param string $cle 'mel' (défaut) | 'roubaix' | 'wasquehal' | 'belgique'
 */
if ( ! function_exists( 'wamv1_schema_area_served' ) ) {
    function wamv1_schema_area_served( string $cle = 'mel' ): array {
        $mel = [
            '@type' => 'AdministrativeArea',
            'name'  => 'Métropole Européenne de Lille',
        ];
        $belgique = [
            '@type' => 'Country',
            'name'  => 'Belgique',
        ];

        $zones = [
            'mel'       => [ [ "Villeneuve-d'Ascq", 'Lille', 'Roubaix', 'Wasquehal', 'Croix', 'Hem' ], $mel ],
            'roubaix'   => [ [ 'Roubaix', 'Croix', 'Hem', 'Lys-lez-Lannoy' ], $mel ],
            'wasquehal' => [ [ 'Wasquehal', 'Croix', 'Marcq-en-Barœul' ], $mel ],
            'belgique'  => [ [ 'Tournai', 'Mouscron', 'Kortrijk' ], $belgique ],
        ];

        [ $villes, $parent ] = $zones[ $cle ] ?? $zones['mel'];

        $area = [];
        foreach ( $villes as $ville ) {
            $area[] = [
                '@type'            => 'City',
                'name'             => $ville,
                'containedInPlace' => $parent,
            ];
        }

        return $area;
    }
}

/**
 * Construit une liste d'Offer à partir d'IDs de produits WooCommerce.
 *
 * Les montants ne sont jamais écrits en dur dans le thème : ils suivent
 * WooCommerce, donc la prod reste la source de vérité et le schema ne se périme
 * pas d'une saison à l'autre. Les produits sans prix exploitable (0 € ou vide —
 * simples conteneurs panier, ex. « Location » ou « Stage ») sont ignorés.
 *
 * @param int[]  $ids IDs de produits WooCommerce.
 * @param string $url URL de la page portant l'offre.
 */
if ( ! function_exists( 'wamv1_schema_offers_from_products' ) ) {
    function wamv1_schema_offers_from_products( array $ids, string $url ): array {
        if ( ! $ids || ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_product' ) ) {
            return [];
        }

        $offers = [];
        foreach ( $ids as $id ) {
            $product = wc_get_product( $id );
            if ( ! $product || (float) $product->get_price() <= 0 ) continue;

            $offers[] = [
                '@type'         => 'Offer',
                'name'          => wamv1_schema_text( $product->get_name() ),
                'price'         => (string) $product->get_price(),
                'priceCurrency' => 'EUR',
                'url'           => $url,
                'availability'  => 'https://schema.org/InStock',
                'seller'        => wamv1_schema_organization_ref(),
            ];
        }

        return $offers;
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
        $time_str = trim( $time_str );
        if ( $time_str === '' ) return '';

        // Les CPT n'utilisent pas la même notation : "18h", "18h30" (cours)
        // et "18:00" (stages). Les deux doivent produire "18:00:00".
        $sep = ( strpos( $time_str, 'h' ) !== false ) ? 'h' : ( ( strpos( $time_str, ':' ) !== false ) ? ':' : '' );
        if ( $sep === '' ) return $time_str;

        $parts = explode( $sep, $time_str );
        $h     = str_pad( intval( $parts[0] ), 2, '0', STR_PAD_LEFT );
        $m     = str_pad( intval( $parts[1] ?? 0 ), 2, '0', STR_PAD_LEFT );
        return "{$h}:{$m}:00";
    }
}

/**
 * Assemble une date ISO (Y-m-d) et une heure ACF en un datetime ISO 8601
 * complet, décalage horaire du site inclus (ex : 2026-07-30T18:00:00+02:00).
 * Google recommande explicitement le fuseau sur startDate/endDate d'un Event :
 * sans lui, l'heure est ambiguë pour les moteurs.
 */
if ( ! function_exists( 'wamv1_schema_datetime_iso' ) ) {
    function wamv1_schema_datetime_iso( string $date_iso, string $heure = '' ): string {
        if ( ! $date_iso ) return '';

        $heure_iso = $heure ? wamv1_schema_time_iso( $heure ) : '';
        if ( ! $heure_iso ) return $date_iso;

        try {
            $dt = new DateTime( $date_iso . ' ' . $heure_iso, wp_timezone() );
            return $dt->format( 'c' );
        } catch ( Exception $e ) {
            return $date_iso . 'T' . $heure_iso;
        }
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
        } else {
            // ID unique global pour cette personne afin d'unifier les graphes (SEO/GEO)
            $person['@id'] = home_url( '/#person-' . sanitize_title( $name ) );
        }

        return $person;
    }
}

/**
 * Construit une liste de VideoObject pour le schéma JSON-LD
 */
if ( ! function_exists( 'wamv1_schema_video_objects' ) ) {
    function wamv1_schema_video_objects( $post_id, $title, $permalink, $desc ): array {
        $video_1 = get_field( 'video_1', $post_id );
        $video_2 = get_field( 'video_2', $post_id );
        $video_3 = get_field( 'video_3', $post_id );
        $videos  = array_filter( [ $video_1, $video_2, $video_3 ] );
        $video_schemas = [];

        if ( ! empty( $videos ) ) {
            $index = 1;
            $desc_str = ( is_string( $desc ) && ! empty( $desc ) ) ? wp_strip_all_tags( $desc ) : '';
            foreach ( $videos as $video_url ) {
                preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=|shorts/)|youtu\.be/)([^"&?/ ]{11})%i', $video_url, $match);
                $video_id = $match[1] ?? null;
                if ( $video_id ) {
                    $video_title = $title . ' - Vidéo de démonstration' . ( count( $videos ) > 1 ? ' ' . $index : '' );
                    $video_schemas[] = [
                        '@type'        => 'VideoObject',
                        '@id'          => $permalink . '#video-' . $video_id,
                        'name'         => $video_title,
                        'description'  => $desc_str ?: $video_title,
                        'thumbnailUrl' => [
                            "https://img.youtube.com/vi/{$video_id}/maxresdefault.jpg",
                            "https://img.youtube.com/vi/{$video_id}/hqdefault.jpg"
                        ],
                        'uploadDate'   => get_the_date( 'c', $post_id ),
                        'embedUrl'     => "https://www.youtube-nocookie.com/embed/{$video_id}",
                    ];
                    $index++;
                }
            }
        }
        return $video_schemas;
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
        $tarif_obj   = get_field( 'tarif_cours',       $post_id );   // text — ex: "250€"
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

        /* ---- Offer — Extraction dynamique depuis WooCommerce ---- */
        $offer = [
            '@type'         => 'Offer',
            'url'           => $permalink,
            'seller'        => wamv1_schema_organization_ref(),
            'priceCurrency' => 'EUR',
            'availability'  => $complet
                ? 'https://schema.org/SoldOut'
                : 'https://schema.org/InStock',
        ];

        /* Prix depuis WooCommerce.
         * ⚠️ La meta est `wc_product_id` — l'ancienne clé `_wam_linked_product`
         * n'a jamais existé, d'où des Offer sans prix sur les 34 fiches cours.
         * Même garde-fou que pour les stages : on ignore les prix nuls ou vides,
         * certains produits ne servant que de conteneur panier. */
        if ( class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' ) ) {
            $product_id = get_post_meta( $post_id, 'wc_product_id', true );
            $product    = $product_id ? wc_get_product( $product_id ) : null;
            if ( $product && (float) $product->get_price() > 0 ) {
                $offer['price'] = (string) $product->get_price();
                $offer['name']  = wamv1_schema_text( $product->get_name() );
            }
        }

        if ( empty($offer['price']) && $tarif_obj && is_string( $tarif_obj ) ) {
            $offer['name'] = $tarif_obj;
            // Fallback : essayer d'extraire le nombre du texte (ex: "250€")
            preg_match('/\d+/', $tarif_obj, $matches);
            if (!empty($matches[0])) {
                $offer['price'] = $matches[0];
            }
        }
        $schema['offers'] = $offer;

        /* ---- Public cible (enfants / adultes via helper thème) ---- */
        if ( function_exists( 'wamv1_is_enfant_variant' ) && wamv1_is_enfant_variant() ) {
            $schema['audience'] = [
                '@type'        => 'EducationalAudience',
                'audienceType' => 'Enfants',
            ];
        }

        // Ajout des VideoObjects associés dans le graphe JSON-LD si présents
        $video_schemas = wamv1_schema_video_objects( $post_id, $title, $permalink, $desc );
        if ( ! empty( $video_schemas ) ) {
            return array_merge( [ $schema ], $video_schemas );
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

        $desc_txt = wamv1_schema_text( $desc );
        if ( $desc_txt ) {
            $schema['description'] = $desc_txt;
        }
        if ( $sous_titre ) {
            $schema['abstract'] = wamv1_schema_text( $sous_titre );
        }

        /* ---- Dates — ACF retourne d/m/Y, schema.org attend ISO 8601 ---- */
        $date_iso = $date_raw ? wamv1_schema_date_iso( $date_raw ) : '';
        if ( $date_iso ) {
            $schema['startDate'] = wamv1_schema_datetime_iso( $date_iso, $heure_debut );
            $schema['endDate']   = wamv1_schema_datetime_iso( $date_iso, $heure_fin );
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

        /* ---- Offers — groupe ACF « tarifs » (source de vérité par stage) ----
         *
         * Structure réelle du groupe (cf. acf-json/group_stages.json) :
         *   nom_tarif_N     — libellé affiché ("Place 1 personne")
         *   prix_tarif_N    — prix en euros
         *   quota_tarif_N   — nombre total de places
         *   quota_reserve_N — places déjà réservées
         *   tarif_2 / tarif_3 — booléens activant les tarifs 2 et 3
         *
         * Le code précédent lisait des sous-champs "tarif_1/2/3" qui n'ont
         * jamais porté de prix (ce sont des interrupteurs) : aucun stage ne
         * sortait donc avec d'offers. On lit désormais nom_/prix_ directement.
         */
        $offers          = [];
        $capacite_totale = 0;
        $places_prises   = 0;

        if ( $tarifs_grp && is_array( $tarifs_grp ) ) {
            foreach ( [ 1, 2, 3 ] as $n ) {
                $prix = $tarifs_grp[ 'prix_tarif_' . $n ] ?? '';
                if ( $prix === '' || $prix === null || ! is_numeric( $prix ) ) continue;

                $quota   = (int) ( $tarifs_grp[ 'quota_tarif_' . $n ]   ?? 0 );
                $reserve = (int) ( $tarifs_grp[ 'quota_reserve_' . $n ] ?? 0 );
                $restant = max( 0, $quota - $reserve );

                $capacite_totale += $quota;
                $places_prises   += $reserve;

                // Complet si le stage est marqué complet, ou si le quota est atteint.
                $epuise = $complet || ( $quota > 0 && $restant === 0 );

                $offer = [
                    '@type'         => 'Offer',
                    'price'         => (string) ( 0 + $prix ),
                    'priceCurrency' => 'EUR',
                    'url'           => $permalink,
                    'availability'  => $epuise
                        ? 'https://schema.org/SoldOut'
                        : 'https://schema.org/InStock',
                    'seller'        => wamv1_schema_organization_ref(),
                ];

                $nom_tarif = wamv1_schema_text( $tarifs_grp[ 'nom_tarif_' . $n ] ?? '' );
                if ( $nom_tarif ) {
                    $offer['name'] = $nom_tarif;
                }
                if ( $quota > 0 ) {
                    $offer['inventoryLevel'] = [
                        '@type' => 'QuantitativeValue',
                        'value' => $restant,
                    ];
                }
                // L'offre court jusqu'au début du stage.
                if ( ! empty( $schema['startDate'] ) ) {
                    $offer['validThrough'] = $schema['startDate'];
                }

                $offers[] = $offer;
            }
        }

        /* Repli WooCommerce : uniquement si aucun tarif ACF n'est renseigné.
         * Le produit générique « Stage » (wc_product_id) est à 0 € — il ne sert
         * que de conteneur panier — donc on ignore tout prix nul ou vide. */
        if ( ! $offers && class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' ) ) {
            $product_id = get_post_meta( $post_id, 'wc_product_id', true );
            $product    = $product_id ? wc_get_product( $product_id ) : null;

            if ( $product && $product->is_type( 'variable' ) ) {
                $prices = $product->get_variation_prices();
                if ( ! empty( $prices['price'] ) ) {
                    $offers[] = [
                        '@type'         => 'AggregateOffer',
                        'lowPrice'      => min( $prices['price'] ),
                        'highPrice'     => max( $prices['price'] ),
                        'priceCurrency' => 'EUR',
                        'offerCount'    => count( $prices['price'] ),
                        'url'           => $permalink,
                        'seller'        => wamv1_schema_organization_ref(),
                    ];
                }
            } elseif ( $product && (float) $product->get_price() > 0 ) {
                $offers[] = [
                    '@type'         => 'Offer',
                    'price'         => (string) $product->get_price(),
                    'priceCurrency' => 'EUR',
                    'url'           => $permalink,
                    'availability'  => $complet ? 'https://schema.org/SoldOut' : 'https://schema.org/InStock',
                    'seller'        => wamv1_schema_organization_ref(),
                ];
            }
        }

        /* ---- validFrom — date d'ouverture des inscriptions ----
         * Aucun champ ACF ne porte cette date : on retient la publication du
         * stage, moment à partir duquel la place est réellement réservable.
         * Garde-fou : on n'écrit rien si la publication est postérieure au
         * début du stage (stage saisi après coup), car validFrom doit précéder
         * validThrough.
         */
        if ( $offers && function_exists( 'get_post_datetime' ) ) {
            $pub_dt = get_post_datetime( $post_id, 'date', 'local' );
            if ( $pub_dt instanceof DateTimeInterface ) {
                $start_ts = ! empty( $schema['startDate'] ) ? strtotime( $schema['startDate'] ) : 0;
                if ( ! $start_ts || $pub_dt->getTimestamp() < $start_ts ) {
                    $valid_from = $pub_dt->format( 'c' );
                    foreach ( $offers as &$offre_ref ) {
                        $offre_ref['validFrom'] = $valid_from;
                    }
                    unset( $offre_ref );
                }
            }
        }

        if ( $offers ) {
            $schema['offers'] = count( $offers ) === 1 ? $offers[0] : $offers;
        }

        /* ---- Jauge de l'événement — dérivée des quotas ACF ---- */
        if ( $capacite_totale > 0 ) {
            $schema['maximumAttendeeCapacity']   = $capacite_totale;
            $schema['remainingAttendeeCapacity'] = $complet
                ? 0
                : max( 0, $capacite_totale - $places_prises );
        }

        // Ajout des VideoObjects associés dans le graphe JSON-LD si présents
        $video_schemas = wamv1_schema_video_objects( $post_id, $title, $permalink, $desc );
        if ( ! empty( $video_schemas ) ) {
            return array_merge( [ $schema ], $video_schemas );
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
// 4d-bis. FAQPage — depuis les blocs Gutenberg « Détails » (core/details)
//     Tout bloc « Détails » d'une page/article devient une paire Q/R.
//     Aucune intervention Gutenberg requise : le simple usage du bloc suffit.
// =========================================================================

/**
 * Aplatit récursivement un arbre de blocs et collecte tous les core/details.
 * Les blocs Détails peuvent être imbriqués dans un core/group, core/columns, etc.
 *
 * @param array $blocks   Résultat de parse_blocks().
 * @param array $collected Accumulateur (passé par référence).
 */
if ( ! function_exists( 'wamv1_collect_details_blocks' ) ) {
    function wamv1_collect_details_blocks( array $blocks, array &$collected ): void {
        foreach ( $blocks as $block ) {
            if ( isset( $block['blockName'] ) && 'core/details' === $block['blockName'] ) {
                $collected[] = $block;
            }
            if ( ! empty( $block['innerBlocks'] ) ) {
                wamv1_collect_details_blocks( $block['innerBlocks'], $collected );
            }
        }
    }
}

/**
 * Construit un nœud FAQPage à partir des blocs « Détails » du contenu courant.
 * Question = texte du <summary>, Réponse = texte du reste du bloc.
 * Retourne null si aucune paire valide (contenu sans accordéon, classic editor…).
 */
if ( ! function_exists( 'wamv1_schema_faqpage' ) ) {
    function wamv1_schema_faqpage(): ?array {
        $post_id = get_queried_object_id();
        if ( ! $post_id ) return null;

        /*
         * Certains gabarits rendent leur FAQ en PHP plutôt qu'en blocs Gutenberg,
         * pour que le contenu se déploie par git et survive aux `ddev pull`
         * (cf. page-tarifs.php). Ils la déclarent via ce filtre, sous forme de
         * paires [ 'q' => …, 'r' => … ] : le nœud reste construit depuis la même
         * source que l'affichage, les deux ne peuvent donc pas diverger.
         */
        $items = apply_filters( 'wamv1_schema_faq_items', [], $post_id );
        if ( $items ) {
            return wamv1_schema_faqpage_from_items( $items, $post_id );
        }

        $content = get_post_field( 'post_content', $post_id );
        if ( empty( $content ) || false === strpos( $content, 'wp:details' ) ) return null;

        $blocks    = parse_blocks( $content );
        $collected = [];
        wamv1_collect_details_blocks( $blocks, $collected );
        if ( empty( $collected ) ) return null;

        $questions = [];

        foreach ( $collected as $block ) {
            $html = render_block( $block );
            if ( empty( $html ) ) continue;

            // Question : contenu du <summary>.
            if ( ! preg_match( '#<summary[^>]*>(.*?)</summary>#is', $html, $m ) ) continue;
            $question = trim( html_entity_decode( wp_strip_all_tags( $m[1] ), ENT_QUOTES, 'UTF-8' ) );

            // Réponse : le reste du bloc, une fois le <summary> retiré.
            $answer_html = preg_replace( '#<summary[^>]*>.*?</summary>#is', '', $html );
            $answer      = html_entity_decode( wp_strip_all_tags( $answer_html ), ENT_QUOTES, 'UTF-8' );
            $answer      = trim( preg_replace( '/\s+/u', ' ', $answer ) );

            if ( '' === $question || '' === $answer ) continue;

            $questions[] = [
                '@type'          => 'Question',
                'name'           => $question,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $answer,
                ],
            ];
        }

        if ( empty( $questions ) ) return null;

        return [
            '@type'      => 'FAQPage',
            '@id'        => get_permalink( $post_id ) . '#faq',
            'mainEntity' => $questions,
        ];
    }
}

/**
 * Construit un nœud FAQPage depuis un tableau de paires question / réponse.
 *
 * @param array<int, array{q:string, r:string}> $items
 * @param int                                   $post_id
 */
if ( ! function_exists( 'wamv1_schema_faqpage_from_items' ) ) {
    function wamv1_schema_faqpage_from_items( array $items, int $post_id ): ?array {
        $questions = [];

        foreach ( $items as $item ) {
            $question = wamv1_schema_text( $item['q'] ?? '' );
            $reponse  = wamv1_schema_text( $item['r'] ?? '' );

            if ( '' === $question || '' === $reponse ) continue;

            $questions[] = [
                '@type'          => 'Question',
                'name'           => $question,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $reponse,
                ],
            ];
        }

        if ( ! $questions ) return null;

        return [
            '@type'      => 'FAQPage',
            '@id'        => get_permalink( $post_id ) . '#faq',
            'mainEntity' => $questions,
        ];
    }
}

/**
 * Combine un nœud schema métier (Service, Article…) avec le FAQPage éventuel.
 * Retourne :
 *   - null                si ni l'un ni l'autre,
 *   - l'objet unique      si un seul des deux existe,
 *   - une liste [base, faq] (emballée en @graph par l'appelant) si les deux existent.
 */
if ( ! function_exists( 'wamv1_schema_merge_with_faq' ) ) {
    function wamv1_schema_merge_with_faq( ?array $base ) {
        $faq = wamv1_schema_faqpage();

        if ( $base && $faq )  return [ $base, $faq ];
        if ( $base )          return $base;
        if ( $faq )           return $faq;
        return null;
    }
}

// =========================================================================
// 4f. ARCHIVE D'UN STYLE DE COURS — CollectionPage + ItemList
//     Sur /cat_cours/<slug>/ : énumère les cours du style, ce qui rend la
//     page directement exploitable par un moteur de réponse (GEO).
// =========================================================================

if ( ! function_exists( 'wamv1_schema_cat_cours' ) ) {
    function wamv1_schema_cat_cours(): ?array {
        $term = get_queried_object();
        if ( ! $term instanceof WP_Term ) return null;

        $url = get_term_link( $term );
        if ( is_wp_error( $url ) ) return null;

        $cours = get_posts( [
            'post_type'      => 'cours',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'tax_query'      => [ [
                'taxonomy' => 'cat_cours',
                'field'    => 'term_id',
                'terms'    => $term->term_id,
            ] ],
        ] );

        if ( ! $cours ) return null;

        $elements = [];
        foreach ( $cours as $i => $post ) {
            $elements[] = [
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'url'      => get_permalink( $post->ID ),
                'name'     => wamv1_schema_text( get_the_title( $post->ID ) ),
            ];
        }

        /* Yoast publie déjà le nœud CollectionPage de cette URL : en émettre un
         * second créerait le même doublon que celui relevé au Rich Results Test
         * sur Organization. On ne publie donc qu'un ItemList autonome, que le
         * filtre wpseo_schema_webpage ci-dessous rattache à Yoast via mainEntity. */
        // Libellé long (« Cours de danse ados ») plutôt que le nom court du
        // terme : c'est ce qui est affiché en H1, le schema doit correspondre.
        $libelle = function_exists( 'wamv1_cat_cours_h1' )
            ? wamv1_cat_cours_h1( $term )
            : $term->name;

        $schema = [
            '@type'           => 'ItemList',
            '@id'             => $url . '#courses',
            'name'            => wamv1_schema_text( $libelle ),
            'numberOfItems'   => count( $elements ),
            'itemListElement' => $elements,
        ];

        // La description du terme est saisie dans l'admin : elle peut être vide.
        $desc = wamv1_schema_text( term_description( $term ), 300 );
        if ( $desc ) {
            $schema['description'] = $desc;
        }

        return $schema;
    }
}

/**
 * Rattache l'ItemList des cours au nœud CollectionPage produit par Yoast, et
 * décrit le sujet de la page (`about`) — sans jamais dupliquer le nœud de page.
 */
add_filter( 'wpseo_schema_webpage', 'wamv1_schema_cat_cours_webpage' );

if ( ! function_exists( 'wamv1_schema_cat_cours_webpage' ) ) {
    function wamv1_schema_cat_cours_webpage( $data ) {
        if ( ! is_array( $data ) || ! is_tax( 'cat_cours' ) ) return $data;

        $term = get_queried_object();
        if ( ! $term instanceof WP_Term ) return $data;

        $url = get_term_link( $term );
        if ( is_wp_error( $url ) ) return $data;

        if ( empty( $data['mainEntity'] ) ) {
            $data['mainEntity'] = [ '@id' => $url . '#courses' ];
        }
        if ( empty( $data['about'] ) ) {
            $libelle = function_exists( 'wamv1_cat_cours_h1' )
                ? wamv1_cat_cours_h1( $term )
                : $term->name;

            $data['about'] = [
                '@type'      => 'Service',
                'name'       => wamv1_schema_text( $libelle ),
                'provider'   => wamv1_schema_organization_ref(),
                'areaServed' => wamv1_schema_area_served( 'mel' ),
            ];
        }

        return $data;
    }
}

/**
 * Rattache l'OfferCatalog de /tarifs/ au nœud WebPage produit par Yoast.
 *
 * Même stratégie que pour l'ItemList des archives de style : le thème n'émet
 * jamais son propre nœud de page (ce qui dupliquerait celui de Yoast), il se
 * contente de le pointer via `mainEntity`.
 */
add_filter( 'wpseo_schema_webpage', 'wamv1_schema_tarifs_webpage' );

if ( ! function_exists( 'wamv1_schema_tarifs_webpage' ) ) {
    function wamv1_schema_tarifs_webpage( $data ) {
        if ( ! is_array( $data ) || ! is_page( 'tarifs' ) ) return $data;

        if ( empty( $data['mainEntity'] ) ) {
            $data['mainEntity'] = [ '@id' => get_permalink() . '#tarifs' ];
        }

        return $data;
    }
}

// =========================================================================
// 4d bis. PAGE TARIFS — OfferCatalog
//     Énumère l'ensemble des prestations tarifées, ce qui rend la grille
//     directement citable par un moteur de réponse (« combien coûte un cours
//     de danse à Villeneuve d'Ascq ? »). Les montants viennent tous de
//     inc/tarifs.php : le déclaré ne peut pas diverger de l'affiché.
// =========================================================================

if ( ! function_exists( 'wamv1_schema_tarifs' ) ) {
    function wamv1_schema_tarifs(): ?array {
        if ( ! function_exists( 'wamv1_tarifs_paliers_cours' ) ) return null;

        $url   = get_permalink();
        $seller = wamv1_schema_organization_ref();
        $items = [];

        /* ---- Cours hebdomadaires : une offre par palier ---- */
        foreach ( wamv1_tarifs_paliers_cours( 5 ) as $palier ) {
            $items[] = [
                '@type'         => 'Offer',
                'name'          => sprintf(
                    _n( '%d cours collectif hebdomadaire, saison complète',
                        '%d cours collectifs hebdomadaires, saison complète',
                        $palier['nb'], 'wamv1' ),
                    $palier['nb']
                ),
                'price'         => (string) round( $palier['total'], 2 ),
                'priceCurrency' => 'EUR',
                'url'           => $url,
                'availability'  => 'https://schema.org/InStock',
                'seller'        => $seller,
                'itemOffered'   => [
                    '@type' => 'Service',
                    'name'  => 'Cours collectif de danse hebdomadaire',
                ],
            ];
        }

        /* ---- Stages : prix d'entrée uniquement (le tarif varie par session) ---- */
        $stage_min = wamv1_tarifs_stage_prix_min();
        if ( $stage_min > 0 ) {
            $items[] = [
                '@type'            => 'AggregateOffer',
                'name'             => 'Stage, workshop ou atelier de danse',
                'lowPrice'         => (string) round( $stage_min, 2 ),
                'priceCurrency'    => 'EUR',
                'url'              => home_url( '/stages-workshop-ateliers/' ),
                'availability'     => 'https://schema.org/InStock',
                'offeredBy'        => $seller,
            ];
        }

        /* ---- Prestations chiffrées dans WooCommerce ---- */
        $produits = [
            675 => [ 'Cours particulier de danse, 1 heure',        '/cours-particulier-prive/' ],
            785 => [ 'Mariage — formule découverte, 1 heure',      '/choregraphie-de-mariage-ouvertures-de-bal/' ],
            681 => [ 'Mariage — formule 5 heures',                 '/choregraphie-de-mariage-ouvertures-de-bal/' ],
            964 => [ 'Mariage — formule 8 heures',                 '/choregraphie-de-mariage-ouvertures-de-bal/' ],
            682 => [ 'Mariage — formule 10 heures',                '/choregraphie-de-mariage-ouvertures-de-bal/' ],
        ];

        foreach ( $produits as $id => [ $nom, $chemin ] ) {
            $prix = wamv1_tarifs_prix_produit( (int) $id );
            if ( $prix <= 0 ) continue;

            $items[] = [
                '@type'         => 'Offer',
                'name'          => $nom,
                'price'         => (string) round( $prix, 2 ),
                'priceCurrency' => 'EUR',
                'url'           => home_url( $chemin ),
                'availability'  => 'https://schema.org/InStock',
                'seller'        => $seller,
            ];
        }

        /* ---- Forfaits sans prix WooCommerce exploitable ---- */
        $forfaits = wamv1_tarifs_forfaits_manuels();

        $items[] = [
            '@type'         => 'Offer',
            'name'          => sprintf(
                'Animation EVJF, EVG ou anniversaire, %s pour le groupe',
                $forfaits['evjf']['duree']
            ),
            'price'         => (string) round( $forfaits['evjf']['prix'], 2 ),
            'priceCurrency' => 'EUR',
            'url'           => home_url( '/evjf-evg-anniversaire/' ),
            'availability'  => 'https://schema.org/InStock',
            'seller'        => $seller,
        ];

        // Location : fourchette horaire → UnitPriceSpecification, plus juste
        // qu'un prix sec puisque le tarif dépend du créneau.
        $items[] = [
            '@type'             => 'Offer',
            'name'              => 'Location du studio de danse, à l’heure',
            'url'               => home_url( '/location-du-studio/' ),
            'availability'      => 'https://schema.org/InStock',
            'seller'            => $seller,
            'priceSpecification' => [
                '@type'         => 'UnitPriceSpecification',
                'minPrice'      => (string) round( $forfaits['location']['prix_bas'], 2 ),
                'maxPrice'      => (string) round( $forfaits['location']['prix_haut'], 2 ),
                'priceCurrency' => 'EUR',
                'unitCode'      => 'HUR',
                'unitText'      => 'heure',
            ],
        ];

        // Team building : aucun prix public, on ne déclare que le point d'entrée.
        $items[] = [
            '@type'         => 'Offer',
            'name'          => 'Team building danse en entreprise, sur devis',
            'priceCurrency' => 'EUR',
            'url'           => home_url( '/team-building/' ),
            'availability'  => 'https://schema.org/InStock',
            'seller'        => $seller,
        ];

        if ( ! $items ) return null;

        return [
            '@type'           => 'OfferCatalog',
            '@id'             => $url . '#tarifs',
            'name'            => 'Tarifs ' . wamv1_schema_nom_studio(),
            'description'     => 'Tarifs des cours de danse hebdomadaires, stages, cours '
                               . 'particuliers, chorégraphies de mariage, animations privées '
                               . 'et location de studio à Villeneuve d’Ascq.',
            'url'             => $url,
            'provider'        => $seller,
            'areaServed'      => wamv1_schema_area_served( 'mel' ),
            'itemListElement' => $items,
        ];
    }
}

// =========================================================================
// 4e. PAGES DE SERVICE — Service + areaServed
//     Uniquement sur les pages identifiées comme services WAM.
// =========================================================================

if ( ! function_exists( 'wamv1_schema_page_service' ) ) {
    function wamv1_schema_page_service(): ?array {
        $slug = get_post_field( 'post_name', get_queried_object_id() );

        /* Clés facultatives par entrée (toutes rétrocompatibles) :
         *   'area'     — clé de zone pour wamv1_schema_area_served() (défaut 'mel')
         *   'type'     — serviceType (défaut 'École de danse')
         *   'products' — IDs produits WooCommerce → offers chiffrées
         *   'quote'    — chemin de la page de devis → Offer sans prix
         *   'audience' — 'business' → BusinessAudience
         *   'catalog'  — [ [name, desc], … ] → hasOfferCatalog
         */
        $service_map = [
            'cours-collectifs' => [
                'name'     => 'Cours collectifs de danse',
                'desc'     => 'Cours hebdomadaires adultes et enfants : danse moderne, salon, WCS, street jazz, orientale, latino.',
                'products' => [ 591 ],
            ],
            'stages-workshop-ateliers' => [
                'name' => 'Stages, Workshops & Ateliers de danse',
                'desc' => 'Stages ponctuels et ateliers thématiques tous niveaux, enfants et adultes.',
            ],
            'choregraphie-de-mariage-ouvertures-de-bal' => [
                'name'  => 'Chorégraphie de mariage & ouverture de bal',
                'desc'  => 'Formules sur mesure pour préparer une ouverture de bal : solo, duo, groupe.',
                'quote' => '/contact/',
            ],
            'prof-wam' => [
                'name' => "Professeur·es WAM Dance Studio",
                'desc' => 'Équipe pédagogique de professeurs diplômés d\'État.',
            ],
            'planning' => [
                'name' => 'Planning hebdomadaire des cours WAM',
                'desc' => 'Grille horaire de tous les cours collectifs de la semaine.',
            ],

            /* --- Pages de service ajoutées (audit SEO/GEO) --- */
            'team-building' => [
                'name'     => 'Team building danse en entreprise',
                'desc'     => "Animations danse pour entreprises, collectivités et comités d'entreprise de la métropole lilloise : cours à deux, chorégraphie de groupe, danses collectives. Au studio ou dans vos locaux.",
                'type'     => "Animation d'entreprise",
                'audience' => 'business',
                'quote'    => '/contact/',
                'catalog'  => [
                    [ 'name' => 'Cours de danse à deux',           'desc' => 'Valse, salsa, West Coast Swing en binômes, avec rotations régulières de partenaires.' ],
                    [ 'name' => 'Chorégraphie de groupe à thème',  'desc' => "Chorégraphie commune sur un univers musical choisi, avec restitution possible en fin de séance ou support vidéo type LipDub." ],
                    [ 'name' => 'Initiation aux danses collectives','desc' => 'Folk, rueda, danses en ligne : tout le groupe danse ensemble, sans partenaire attitré.' ],
                ],
            ],
            'cours-particulier-prive' => [
                'name'     => 'Cours particulier et cours privé de danse',
                'desc'     => "Cours individuels ou en petit comité, à la carte ou en formule, adaptés à vos envies, votre niveau et votre rythme.",
                'products' => [ 675, 681, 964, 682 ],
            ],
            'evjf-evg-anniversaire' => [
                'name'  => 'EVJF, EVG et anniversaire dansant',
                'desc'  => "Animation danse sur mesure pour enterrement de vie de jeune fille ou de garçon, anniversaire et fête privée, à Villeneuve d'Ascq ou dans le lieu de votre choix.",
                'type'  => 'Animation événementielle',
                'quote' => '/contact/',
            ],
            'location-du-studio' => [
                'name'  => 'Location du studio de danse',
                'desc'  => "Location d'une salle équipée (parquet, miroirs, sonorisation) à Villeneuve d'Ascq, pour répétition, cours, casting ou tournage.",
                'type'  => 'Location de salle',
                'quote' => '/contact/',
            ],

            /* --- Pages géo : même service, zone desservie spécifique --- */
            'cours-de-danse-roubaix' => [
                'name'     => 'Cours de danse près de Roubaix',
                'desc'     => "Cours de danse hebdomadaires à 15 minutes de Roubaix : K-Pop, heels, latino solo, swing, comédie musicale. Enfants dès 3 ans et adultes tous niveaux.",
                'area'     => 'roubaix',
                'products' => [ 591 ],
            ],
            'cours-de-danse-wasquehal' => [
                'name'     => 'Cours de danse près de Wasquehal',
                'desc'     => "Cours de danse hebdomadaires à moins de 10 minutes de Wasquehal : K-Pop, heels, urban, swing, danse orientale. Enfants dès 3 ans et adultes tous niveaux.",
                'area'     => 'wasquehal',
                'products' => [ 591 ],
            ],
            'cours-de-danse-belgique' => [
                'name'     => 'Cours de danse depuis la Belgique',
                'desc'     => "Cours de danse hebdomadaires accessibles depuis Tournai, Mouscron et Kortrijk : inscription en ligne, tarifs en euros, créneaux adultes en soirée.",
                'area'     => 'belgique',
                'products' => [ 591 ],
            ],
        ];

        if ( ! isset( $service_map[ $slug ] ) ) return null;

        $data      = $service_map[ $slug ];
        $permalink = get_permalink();

        $schema = [
            '@type'       => 'Service',
            '@id'         => $permalink . '#service',
            'name'        => $data['name'],
            'description' => $data['desc'],
            'url'         => $permalink,
            'provider'    => wamv1_schema_organization_ref(),
            'serviceType' => $data['type'] ?? 'École de danse',
            'areaServed'  => wamv1_schema_area_served( $data['area'] ?? 'mel' ),
        ];

        /* ---- Offers : prix réels WooCommerce, sinon renvoi vers un devis ---- */
        $offers = wamv1_schema_offers_from_products( $data['products'] ?? [], $permalink );

        if ( ! $offers && ! empty( $data['quote'] ) ) {
            // Prestation sur mesure : aucun prix public n'est affirmé, on ne
            // renseigne donc pas `price` — seulement l'URL où demander un devis.
            $offers[] = [
                '@type'         => 'Offer',
                'name'          => 'Prestation sur mesure, sur devis',
                'priceCurrency' => 'EUR',
                'url'           => home_url( $data['quote'] ),
                'availability'  => 'https://schema.org/InStock',
                'seller'        => wamv1_schema_organization_ref(),
            ];
        }

        if ( $offers ) {
            $schema['offers'] = count( $offers ) === 1 ? $offers[0] : $offers;
        }

        /* ---- Catalogue de prestations (formats proposés sur la page) ---- */
        if ( ! empty( $data['catalog'] ) ) {
            $items = [];
            foreach ( $data['catalog'] as $item ) {
                $items[] = [
                    '@type'       => 'Offer',
                    'itemOffered' => [
                        '@type'       => 'Service',
                        'name'        => $item['name'],
                        'description' => $item['desc'],
                        'provider'    => wamv1_schema_organization_ref(),
                    ],
                ];
            }
            $schema['hasOfferCatalog'] = [
                '@type'          => 'OfferCatalog',
                'name'           => $data['name'],
                'itemListElement' => $items,
            ];
        }

        /* ---- Public visé ---- */
        if ( ( $data['audience'] ?? '' ) === 'business' ) {
            $schema['audience'] = [
                '@type'        => 'BusinessAudience',
                'audienceType' => "Entreprises, collectivités et comités d'entreprise",
            ];
        }

        return $schema;
    }
}
