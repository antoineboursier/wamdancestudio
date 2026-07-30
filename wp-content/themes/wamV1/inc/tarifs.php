<?php
/**
 * Données tarifaires WAM — source unique de vérité pour la page /tarifs/.
 *
 * Aucun montant n'est écrit en dur ici : tout est lu depuis les endroits où les
 * tarifs sont réellement administrés, pour que la page ne se périme pas d'une
 * saison à l'autre et qu'elle ne puisse pas contredire le panier.
 *
 *   Cours hebdomadaires  → produit WooCommerce lié aux CPT `cours` (ACF wc_product_id)
 *                          + option `coulisses_reduction_multi_cours` (remise par cours
 *                          supplémentaire, appliquée au panier par wam-custom-plugin)
 *   Stages               → champs ACF `tarifs_prix_tarif_N` des CPT `stages` publiés
 *   Prestations          → prix des produits WooCommerce correspondants
 *   Saison               → dates extraites de l'option `cours_tarif_texte`
 *
 * @package wamv1
 */

/**
 * Prix d'une adhésion à un cours hebdomadaire, en euros.
 *
 * Cherche le produit WooCommerce le plus fréquemment rattaché aux cours publiés
 * (meta ACF `wc_product_id`) : si la saison change de produit, la page suit sans
 * qu'on ait à toucher au thème. Repli sur le produit par défaut configuré côté
 * plugin, puis sur le premier montant lisible dans `cours_tarif_texte`.
 *
 * @return float 0.0 si aucun prix exploitable n'a pu être déterminé.
 */
if ( ! function_exists( 'wamv1_tarifs_prix_cours' ) ) {
    function wamv1_tarifs_prix_cours(): float {
        $product_id = wamv1_tarifs_produit_cours_id();

        if ( $product_id && function_exists( 'wc_get_product' ) ) {
            $product = wc_get_product( $product_id );
            if ( $product && (float) $product->get_price() > 0 ) {
                return (float) $product->get_price();
            }
        }

        // Dernier repli : premier montant du texte administré dans Configuration WAM.
        $texte = (string) get_option( 'cours_tarif_texte', '' );
        if ( preg_match( '/(\d[\d\s]*)\s*€/u', $texte, $m ) ) {
            return (float) preg_replace( '/\s+/', '', $m[1] );
        }

        return 0.0;
    }
}

/**
 * ID du produit WooCommerce porteur de l'adhésion annuelle.
 *
 * Déduit des cours eux-mêmes plutôt que codé en dur : on retient le produit
 * majoritaire parmi les cours publiés.
 */
if ( ! function_exists( 'wamv1_tarifs_produit_cours_id' ) ) {
    function wamv1_tarifs_produit_cours_id(): int {
        $cached = get_transient( 'wamv1_tarifs_produit_cours_v1' );
        if ( false !== $cached ) {
            return (int) $cached;
        }

        global $wpdb;

        $product_id = (int) $wpdb->get_var(
            "SELECT pm.meta_value
               FROM {$wpdb->postmeta} pm
               JOIN {$wpdb->posts} p ON p.ID = pm.post_id
              WHERE pm.meta_key = 'wc_product_id'
                AND pm.meta_value > 0
                AND p.post_type = 'cours'
                AND p.post_status = 'publish'
           GROUP BY pm.meta_value
           ORDER BY COUNT(*) DESC
              LIMIT 1"
        );

        if ( ! $product_id ) {
            $product_id = (int) get_option( 'wam_default_wc_product_id', 0 );
        }

        set_transient( 'wamv1_tarifs_produit_cours_v1', $product_id, DAY_IN_SECONDS );

        return $product_id;
    }
}

/**
 * Remise consentie par cours supplémentaire, en euros.
 *
 * Même option que celle lue par wam-custom-plugin dans
 * `woocommerce_cart_calculate_fees` : la page affiche donc exactement la remise
 * que le panier appliquera.
 */
if ( ! function_exists( 'wamv1_tarifs_remise_multi_cours' ) ) {
    function wamv1_tarifs_remise_multi_cours(): float {
        return (float) get_option( 'coulisses_reduction_multi_cours', 90 );
    }
}

/**
 * Grille dégressive des cours hebdomadaires.
 *
 * Reproduit la formule appliquée au panier par wam-custom-plugin
 * (`coulisses_calcul_total` / hook `woocommerce_cart_calculate_fees`) :
 *
 *     total = N × prix_unitaire − (N − 1) × remise
 *
 * Calculer la grille plutôt que la recopier garantit que la page ne peut pas
 * annoncer un montant que le panier ne facturera pas.
 *
 * @param int $max Nombre de paliers à produire.
 * @return array<int, array{nb:int, total:float, moyen:float, economie:float}>
 */
if ( ! function_exists( 'wamv1_tarifs_paliers_cours' ) ) {
    function wamv1_tarifs_paliers_cours( int $max = 5 ): array {
        $unitaire = wamv1_tarifs_prix_cours();
        if ( $unitaire <= 0 ) {
            return [];
        }

        $remise  = wamv1_tarifs_remise_multi_cours();
        $paliers = [];

        for ( $nb = 1; $nb <= $max; $nb++ ) {
            $total = max( 0.0, ( $nb * $unitaire ) - ( ( $nb - 1 ) * $remise ) );

            $paliers[] = [
                'nb'       => $nb,
                'total'    => $total,
                'moyen'    => $total / $nb,
                'economie' => ( $nb - 1 ) * $remise,
            ];
        }

        return $paliers;
    }
}

/**
 * Bornes de la saison, extraites du texte administré dans Configuration WAM.
 *
 * @return array{debut:string, fin:string} Chaînes vides si non déterminables.
 */
if ( ! function_exists( 'wamv1_tarifs_saison' ) ) {
    function wamv1_tarifs_saison(): array {
        $saison = [ 'debut' => '', 'fin' => '' ];
        $texte  = (string) get_option( 'cours_tarif_texte', '' );

        if ( preg_match( '#du\s+(\d{2}/\d{2}/\d{4})\s+au\s+(\d{2}/\d{2}/\d{4})#u', $texte, $m ) ) {
            $saison['debut'] = wamv1_tarifs_date_lisible( $m[1] );
            $saison['fin']   = wamv1_tarifs_date_lisible( $m[2] );
            return $saison;
        }

        // Repli : la date de rentrée est saisie séparément, déjà en clair.
        $rentree = trim( (string) get_option( 'wam_setting_date_rentree', '' ) );
        if ( $rentree ) {
            $saison['debut'] = $rentree;
        }

        return $saison;
    }
}

/**
 * Convertit une date jj/mm/aaaa en date lisible en français ("14 septembre 2026").
 * Retourne la chaîne d'origine si elle n'est pas exploitable.
 */
if ( ! function_exists( 'wamv1_tarifs_date_lisible' ) ) {
    function wamv1_tarifs_date_lisible( string $date ): string {
        $dt = DateTime::createFromFormat( 'd/m/Y', $date, wp_timezone() );
        if ( ! $dt ) {
            return $date;
        }

        return wp_date( 'j F Y', $dt->getTimestamp() );
    }
}

/**
 * Prix d'entrée des stages : plus petit tarif renseigné sur un stage publié.
 *
 * Les stages portent jusqu'à trois tarifs (groupe ACF `tarifs`), dont les prix
 * sont stockés sous les clés `tarifs_prix_tarif_1..3`. Les valeurs vides ou
 * nulles sont ignorées.
 *
 * @return float 0.0 si aucun stage publié ne porte de tarif.
 */
if ( ! function_exists( 'wamv1_tarifs_stage_prix_min' ) ) {
    function wamv1_tarifs_stage_prix_min(): float {
        $cached = get_transient( 'wamv1_tarifs_stage_min_v1' );
        if ( false !== $cached ) {
            return (float) $cached;
        }

        global $wpdb;

        $prix = $wpdb->get_col(
            "SELECT pm.meta_value
               FROM {$wpdb->postmeta} pm
               JOIN {$wpdb->posts} p ON p.ID = pm.post_id
              WHERE pm.meta_key IN ( 'tarifs_prix_tarif_1', 'tarifs_prix_tarif_2', 'tarifs_prix_tarif_3' )
                AND p.post_type = 'stages'
                AND p.post_status = 'publish'"
        );

        $montants = array_filter( array_map( 'floatval', (array) $prix ), static function ( $v ) {
            return $v > 0;
        } );

        $min = $montants ? min( $montants ) : 0.0;

        set_transient( 'wamv1_tarifs_stage_min_v1', $min, DAY_IN_SECONDS );

        return (float) $min;
    }
}

/**
 * Formate un montant pour l'affichage : « 285 € », « 63,50 € ».
 *
 * Les décimales ne sont affichées que si elles existent — tous les tarifs WAM
 * sont ronds aujourd'hui, mais un prix à la virgule resterait lisible.
 */
if ( ! function_exists( 'wamv1_tarifs_montant' ) ) {
    function wamv1_tarifs_montant( float $montant ): string {
        $decimales = ( fmod( $montant, 1.0 ) === 0.0 ) ? 0 : 2;

        return number_format_i18n( $montant, $decimales ) . '&nbsp;€';
    }
}

/**
 * Prix d'un produit WooCommerce, ou 0.0 s'il n'est pas exploitable.
 *
 * Les produits « conteneurs » du tunnel (Stage, Location, Préinscription…) sont
 * à 0 € : ils ne portent pas de tarif public et sont donc écartés.
 */
if ( ! function_exists( 'wamv1_tarifs_prix_produit' ) ) {
    function wamv1_tarifs_prix_produit( int $id ): float {
        if ( ! $id || ! function_exists( 'wc_get_product' ) ) {
            return 0.0;
        }

        $product = wc_get_product( $id );
        if ( ! $product ) {
            return 0.0;
        }

        $prix = (float) $product->get_price();

        return $prix > 0 ? $prix : 0.0;
    }
}

/**
 * Formules d'une prestation, construites depuis WooCommerce.
 *
 * Chaque entrée attendue : [ 'id' => int, 'label' => string, 'detail' => string ].
 * Le libellé passé en paramètre prime sur le nom du produit (les noms WooCommerce
 * sont pensés pour le panier, pas pour une grille tarifaire), mais le **prix**
 * vient toujours du produit. Les formules sans prix exploitable sont écartées :
 * mieux vaut ne rien afficher qu'un « 0 € » trompeur.
 *
 * @param array<int, array{id:int, label:string, detail?:string, note?:string}> $formules
 * @return array<int, array{label:string, detail:string, note:string, prix:float, url:string}>
 */
if ( ! function_exists( 'wamv1_tarifs_formules' ) ) {
    function wamv1_tarifs_formules( array $formules ): array {
        $sortie = [];

        foreach ( $formules as $formule ) {
            $prix = wamv1_tarifs_prix_produit( (int) ( $formule['id'] ?? 0 ) );
            if ( $prix <= 0 ) {
                continue;
            }

            $permalink = get_permalink( (int) $formule['id'] );

            $sortie[] = [
                'label'  => $formule['label'] ?? get_the_title( (int) $formule['id'] ),
                'detail' => $formule['detail'] ?? '',
                'note'   => $formule['note'] ?? '',
                'prix'   => $prix,
                'url'    => $permalink ? $permalink : '',
            ];
        }

        return $sortie;
    }
}

/**
 * Forfaits qui ne sont pas administrables depuis WooCommerce.
 *
 * ⚠️ Seuls tarifs écrits en dur dans le thème, et à contrecœur : les produits
 * WooCommerce correspondants sont des conteneurs de panier à 0 €
 * (« Location » #2080, « Cours privé de groupe » #1988), les montants réels ne
 * vivent que dans le contenu Gutenberg des pages concernées. Les centraliser ici
 * évite au moins qu'ils se contredisent d'une page à l'autre.
 *
 * Pour les faire évoluer : modifier ces valeurs **et** le contenu des pages
 * /location-du-studio/, /reservation-location-du-studio/ et
 * /evjf-evg-anniversaire/. Le jour où ces prestations reçoivent un vrai prix
 * WooCommerce, basculer sur wamv1_tarifs_formules() et supprimer l'entrée.
 *
 * @return array<string, array<string, mixed>>
 */
if ( ! function_exists( 'wamv1_tarifs_forfaits_manuels' ) ) {
    function wamv1_tarifs_forfaits_manuels(): array {
        return [
            'evjf' => [
                'prix'         => 220.0,
                'duree'        => '1 h 30',
                'participants' => 25,
                'option_choreo' => 100.0,
            ],
            'location' => [
                'prix_bas'      => 25.0,
                'prix_haut'     => 35.0,
                'remise_2h'     => 3,
                'remise_4h'     => 5,
                'surface'       => 150,
                'capacite'      => 49,
            ],
        ];
    }
}

/**
 * Le paiement en 3 fois sans frais est-il proposé, et sur quoi ?
 *
 * Lu depuis la configuration réelle de la passerelle HelloAsso côté plugin :
 * le filtre `coulisses_helloasso_3x` ne l'active que pour une liste de produits
 * éligibles. Inutile d'annoncer un facilité de paiement qui serait désactivée.
 *
 * @return array{actif:bool, produits:int[], mention:string}
 */
if ( ! function_exists( 'wamv1_tarifs_paiement_3x' ) ) {
    function wamv1_tarifs_paiement_3x(): array {
        $actif    = (bool) get_option( 'coulisses_helloasso_3x_filtre_active', 0 );
        $produits = get_option( 'coulisses_helloasso_3x_produits_eligibles', [] );
        $mention  = '';

        if ( (bool) get_option( 'coulisses_helloasso_description_active', 0 ) ) {
            $mention = (string) get_option( 'coulisses_helloasso_description', '' );
        }

        return [
            'actif'    => $actif,
            'produits' => array_map( 'intval', (array) $produits ),
            'mention'  => $mention,
        ];
    }
}

/**
 * Identité de la structure associative porteuse de l'école.
 *
 * @return array{nom:string, mention:string, rna:string, siret:string, tva:string}
 */
if ( ! function_exists( 'wamv1_tarifs_association' ) ) {
    function wamv1_tarifs_association(): array {
        // « par l'Association Entre2Danses » → on ne garde que la raison sociale.
        $mention = trim( (string) get_option( 'wam_asso_sous_titre', '' ) );
        $nom     = trim( preg_replace( '/^\s*par\s+l[\'’]?\s*/iu', '', $mention ) );

        return [
            'nom'     => $nom,
            'mention' => $mention,
            'rna'     => trim( (string) get_option( 'wam_asso_rna', '' ) ),
            'siret'   => trim( (string) get_option( 'wam_asso_siret', '' ) ),
            'tva'     => trim( (string) get_option( 'wam_asso_tva_numero', '' ) ),
        ];
    }
}

/**
 * FAQ tarifaire — source unique partagée par l'affichage et le schema.
 *
 * Volontairement en PHP et non en blocs Gutenberg : le contenu en base est
 * écrasé à chaque `ddev pull`, alors que le thème se déploie par git. Le nœud
 * FAQPage est construit depuis ce même tableau par
 * `wamv1_schema_faqpage_tarifs()`, il ne peut donc pas diverger de l'affiché.
 *
 * Les réponses n'avancent que des faits vérifiables dans la configuration du
 * site : aucune réduction famille ou étudiante n'existe aujourd'hui, elle n'est
 * donc pas annoncée.
 *
 * @return array<int, array{q:string, r:string}>
 */
if ( ! function_exists( 'wamv1_tarifs_faq' ) ) {
    function wamv1_tarifs_faq(): array {
        $paliers  = wamv1_tarifs_paliers_cours( 2 );
        $unitaire = wamv1_tarifs_prix_cours();
        $remise   = wamv1_tarifs_remise_multi_cours();
        $asso     = wamv1_tarifs_association();
        $paiement = wamv1_tarifs_paiement_3x();
        $saison   = wamv1_tarifs_saison();

        $faq = [];

        /* --- Essai --- */
        $faq[] = [
            'q' => 'Puis-je essayer un cours avant de m’inscrire ?',
            'r' => 'Oui. Le premier cours est un essai gratuit : vous réservez votre créneau, '
                 . 'vous venez danser, et vous décidez ensuite de vous inscrire ou non.',
        ];

        /* --- HelloAsso --- */
        if ( $paiement['actif'] ) {
            $reponse = 'HelloAsso est la plateforme de paiement dédiée aux associations que nous utilisons '
                     . 'pour encaisser les adhésions. Elle nous permet de vous proposer le règlement '
                     . 'en 3 fois sans frais, sans surcoût pour vous ni commission prélevée sur votre adhésion. '
                     . 'L’option apparaît au moment du paiement, dans le panier.';

            if ( false !== strpos( $paiement['mention'], '29 au 31' ) ) {
                $reponse .= ' Elle n’est pas proposée du 29 au 31 de chaque mois, pour que les trois '
                          . 'prélèvements tombent à des dates qui existent dans tous les mois.';
            }

            $faq[] = [
                'q' => 'HelloAsso, qu’est-ce que c’est ?',
                'r' => $reponse,
            ];

            $faq[] = [
                'q' => 'Puis-je régler mon adhésion en plusieurs fois ?',
                'r' => 'Oui, en 3 fois sans frais via HelloAsso, sur les inscriptions à la saison. '
                     . 'Le choix se fait dans le panier, au moment de régler : le premier tiers est '
                     . 'prélevé immédiatement, les deux suivants les mois d’après. Aucun frais de dossier '
                     . 'ne s’ajoute.',
            ];
        }

        /* --- Dégressivité --- */
        if ( count( $paliers ) === 2 && $remise > 0 ) {
            $faq[] = [
                'q' => 'Y a-t-il une réduction si je prends plusieurs cours ?',
                'r' => sprintf(
                    'Oui. Chaque cours supplémentaire est remisé de %1$s : le deuxième cours revient '
                    . 'donc à %2$s au lieu de %3$s, et la remise se cumule au-delà. Elle est appliquée '
                    . 'automatiquement dans le panier, vous n’avez aucun code à saisir. '
                    . 'Il n’existe en revanche pas de tarif famille ou étudiant distinct : '
                    . 'la dégressivité multi-cours est la seule réduction proposée.',
                    wp_strip_all_tags( wamv1_tarifs_montant( $remise ) ),
                    wp_strip_all_tags( wamv1_tarifs_montant( $unitaire - $remise ) ),
                    wp_strip_all_tags( wamv1_tarifs_montant( $unitaire ) )
                ),
            ];
        }

        /* --- Cadre associatif --- */
        if ( $asso['nom'] ) {
            $faq[] = [
                'q' => 'À quoi correspond l’adhésion ?',
                'r' => sprintf(
                    'Le studio est porté par l’%s, association à but non lucratif. '
                    . 'En vous inscrivant à un cours, vous adhérez à l’association pour la saison : '
                    . 'l’adhésion couvre l’accès à votre cours hebdomadaire pour toute l’année, '
                    . 'encadré par un·e professeur·e de l’équipe. '
                    . 'Il n’y a pas de frais d’inscription à régler en plus, ni de cotisation séparée.',
                    $asso['nom']
                ),
            ];
        }

        /* --- Non-remboursement --- */
        $reponse_rembours = 'L’adhésion couvre la saison entière et n’est pas remboursable : '
                          . 'une absence, un déménagement ou un arrêt en cours d’année ne donnent pas lieu '
                          . 'à remboursement ni à report sur la saison suivante. '
                          . 'En cas de difficulté sérieuse, écrivez-nous : on regarde chaque situation.';

        if ( $saison['debut'] && $saison['fin'] ) {
            $reponse_rembours = sprintf(
                'Les cours ont lieu du %1$s au %2$s, hors vacances scolaires et jours fériés. %3$s',
                $saison['debut'],
                $saison['fin'],
                $reponse_rembours
            );
        }

        $faq[] = [
            'q' => 'L’adhésion est-elle remboursable si j’arrête en cours d’année ?',
            'r' => $reponse_rembours,
        ];

        /* --- Inscription en cours de saison --- */
        $faq[] = [
            'q' => 'Et si je m’inscris en cours de saison ?',
            'r' => 'C’est possible tant qu’il reste de la place dans le cours. '
                 . 'Écrivez-nous avant de régler : on vérifie la disponibilité du créneau et '
                 . 'on vous indique le montant à prévoir selon le moment où vous rejoignez le groupe.',
        ];

        return $faq;
    }
}

/**
 * Déclare la FAQ tarifaire au générateur de schema.
 *
 * Conditionné à la **présence du bloc** `wam/tarifs-faq` et non au slug de la
 * page : le bloc peut être déplacé ou retiré depuis Gutenberg, et le balisage
 * FAQPage suit. `wamv1_schema_faqpage()` construit le nœud depuis le même
 * tableau que celui rendu en accordéons — les deux ne peuvent pas diverger.
 */
add_filter( 'wamv1_schema_faq_items', function ( array $items, int $post_id ): array {
    $content = (string) get_post_field( 'post_content', $post_id );

    if ( '' === $content || ! has_block( 'wam/tarifs-faq', $content ) ) {
        return $items;
    }

    return wamv1_tarifs_faq();
}, 10, 2 );
