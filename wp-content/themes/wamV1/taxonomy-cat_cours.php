<?php
/**
 * Archive d'un style de cours (taxonomie cat_cours)
 *
 * Reprend la mise en page de page-cours-collectifs.php, restreinte au terme
 * courant, et affiche la description saisie dans
 * WP Admin > Cours > Styles de cours > Description.
 *
 * L'URL /cat_cours/<slug>/ existe déjà et est indexée : on améliore donc
 * l'existant plutôt que de créer une page concurrente, ce qui provoquerait une
 * cannibalisation avec /cours-collectifs/ et avec cette archive elle-même.
 *
 * La barre de filtre est rendue en mode 'links' : les chips deviennent des
 * liens vers les autres archives de la taxonomie, ce qui crée le maillage
 * interne qui manquait entre ces pages.
 *
 * @package wamv1
 */

get_header();

$term = get_queried_object();

/* Garde-fou : si la requête ne porte pas sur un terme valide, on n'essaie pas
   de rendre la page avec un objet inattendu. */
if ( ! $term instanceof WP_Term ) {
    get_footer();
    return;
}

$icons_path = get_template_directory_uri() . '/assets/images/';
$cat_icons  = function_exists('wamv1_get_cat_cours_icons') ? wamv1_get_cat_cours_icons() : [];

/* Tous les termes non vides — sert aux chips de navigation entre catégories. */
$terms = get_terms([
    'taxonomy'   => 'cat_cours',
    'hide_empty' => true,
    'orderby'    => 'menu_order',
    'order'      => 'ASC',
]);

$description   = trim( (string) term_description( $term ) );
$cours_url     = get_permalink( get_page_by_path('cours-collectifs') );
$planning_page = get_page_by_path('planning');

/* Le nom du terme (« Ados ») est trop court pour un H1 : on utilise le libellé
   long défini dans functions.php. Le <title> reste géré par Yoast. */
$titre_h1 = function_exists('wamv1_cat_cours_h1')
    ? wamv1_cat_cours_h1($term)
    : $term->name;
?>

<main id="primary" class="site-main">
    <div class="page-cours">

        <div class="page-layout__inner">

            <!-- ============================================================
             BREADCRUMB
             ============================================================ -->
            <?php get_template_part('template-parts/breadcrumb', null, [
                'id'   => 'breadcrumb-cat-cours',
                'full' => true,
            ]); ?>

            <!-- ============================================================
             HERO — nom du style en H1 + adresse + lien planning
             ============================================================ -->
            <?php get_template_part('template-parts/page-hero', null, [
                'page'              => null,
                'page_title'        => $titre_h1,
                'page_desc'         => '',
                'icons_path'        => $icons_path,
                'show_planning_btn' => (bool) $planning_page,
                'planning_url'      => $planning_page ? get_permalink($planning_page) : '',
            ]); ?>

        </div><!-- .page-layout__inner -->

        <!-- ============================================================
         NAVIGATION ENTRE CATÉGORIES — chips en liens (maillage interne)
         ============================================================ -->
        <div class="page-cours__filter-wrap wam-container">
            <?php get_template_part('template-parts/filter', null, [
                'terms'      => $terms,
                'icons_path' => $icons_path,
                'mode'       => 'links',
                'current'    => $term->slug,
                'all_url'    => $cours_url ?: home_url('/'),
            ]); ?>
        </div>

        <!-- ============================================================
         GRILLE DES COURS DU STYLE COURANT
         ============================================================ -->
        <?php get_template_part('template-parts/archive-cours-loop', null, [
            'terms'       => [ $term ],
            'cat_icons'   => $cat_icons,
            'icons_path'  => $icons_path,
            'mode'        => 'standard',
            'show_autres' => false,  // archive mono-terme : pas de section « Autres »
            'show_header' => false,  // le H1 nomme déjà le style
        ]); ?>

        <!-- ============================================================
         DESCRIPTION DU STYLE — saisie dans l'admin (champ Description du terme).
         Placée sous la liste : les cours restent l'information principale,
         la description vient en appui, en taille réduite et couleur secondaire.
         ============================================================ -->
        <div class="page-cours__outro wam-container mt-lg mb-lg">
            <div class="wam-prose text-sm color-subtext">
                <?php if ($description): ?>
                    <?php echo wp_kses_post( wpautop( $description ) ); ?>
                <?php else: ?>
                    <p>
                        Retrouvez l'ensemble des styles proposés sur la page
                        <a href="<?php echo esc_url($cours_url ?: home_url('/cours-collectifs/')); ?>">cours collectifs</a>,
                        et le détail de l'adhésion sur la page
                        <a href="<?php echo esc_url(home_url('/tarifs/')); ?>">tarifs</a>.
                    </p>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- .page-cours -->
</main>

<?php
get_footer();
