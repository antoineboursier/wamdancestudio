<?php
/**
 * Template part : grille dégressive des cours hebdomadaires.
 *
 * Rendu du bloc Gutenberg `wam/tarifs-grille`. Les montants sont calculés depuis
 * la formule réellement appliquée au panier (cf. inc/tarifs.php) : la page ne
 * peut donc pas annoncer un prix que le panier ne facturerait pas.
 *
 * @package wamv1
 */

$paliers = wamv1_tarifs_paliers_cours( (int) ( $args['max'] ?? 5 ) );
$remise  = wamv1_tarifs_remise_multi_cours();

if ( ! $paliers ) {
    // Aucun prix exploitable : on n'affiche pas un tableau vide.
    return;
}
?>

<div class="tarifs-block tarifs-block--grille">

    <?php /* tabindex + role : une zone scrollable doit rester atteignable au clavier */ ?>
    <div class="tarifs-table-wrap" tabindex="0" role="region"
         aria-label="Grille tarifaire des cours hebdomadaires">
        <table class="tarifs-table">
            <caption class="tarifs-table__caption text-sm color-subtext">
                Tarifs à la saison, par personne. Chaque cours supplémentaire est
                remisé de <?php echo wp_kses_post( wamv1_tarifs_montant( $remise ) ); ?>.
            </caption>
            <thead>
                <tr>
                    <th scope="col">Nombre de cours par semaine</th>
                    <th scope="col">Total pour la saison</th>
                    <th scope="col">Soit par cours</th>
                    <th scope="col">Économie</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $paliers as $palier ) : ?>
                <tr>
                    <th scope="row">
                        <?php echo esc_html( $palier['nb'] ); ?> cours
                    </th>
                    <td class="tarifs-table__price">
                        <?php echo wp_kses_post( wamv1_tarifs_montant( $palier['total'] ) ); ?>
                    </td>
                    <td class="color-subtext">
                        <?php echo wp_kses_post( wamv1_tarifs_montant( $palier['moyen'] ) ); ?>
                    </td>
                    <td class="color-subtext">
                        <?php
                        echo $palier['economie'] > 0
                            ? '−' . wp_kses_post( wamv1_tarifs_montant( $palier['economie'] ) )
                            : '—';
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p class="tarifs-block__note text-xs color-subtext">
        Au-delà de <?php echo esc_html( count( $paliers ) ); ?> cours, la remise continue
        de s’appliquer sur chaque cours supplémentaire. Le montant exact s’affiche
        dans le panier avant tout paiement.
    </p>

</div>
