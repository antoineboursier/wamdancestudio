<?php
/**
 * Template part : tableau des formules de chorégraphie de mariage.
 *
 * Rendu du bloc Gutenberg `wam/tarifs-formules`. Les prix viennent des produits
 * WooCommerce ; une formule sans prix exploitable est simplement omise, plutôt
 * que d'afficher un « 0 € » trompeur.
 *
 * @package wamv1
 */

$formules = wamv1_tarifs_formules( [
    [ 'id' => 785, 'label' => 'Formule découverte', 'detail' => '1 séance d’1 heure',   'note' => 'Faire connaissance, poser le projet' ],
    [ 'id' => 681, 'label' => 'Formule 5 h',        'detail' => '5 séances d’1 heure',  'note' => 'Une ouverture de bal simple et élégante' ],
    [ 'id' => 964, 'label' => 'Formule 8 h',        'detail' => '8 séances d’1 heure',  'note' => 'Chorégraphie sur mesure, tarif dégressif' ],
    [ 'id' => 682, 'label' => 'Formule 10 h',       'detail' => '10 séances d’1 heure', 'note' => 'La plus demandée — meilleur prix par séance' ],
] );

if ( ! $formules ) {
    return;
}
?>

<div class="tarifs-block tarifs-block--formules">

    <div class="tarifs-table-wrap" tabindex="0" role="region"
         aria-label="Formules de chorégraphie de mariage">
        <table class="tarifs-table">
            <caption class="tarifs-table__caption text-sm color-subtext">
                Formules de préparation d’ouverture de bal, au studio.
            </caption>
            <thead>
                <tr>
                    <th scope="col">Formule</th>
                    <th scope="col">Contenu</th>
                    <th scope="col">Tarif</th>
                    <th scope="col">Soit par séance</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $formules as $formule ) : ?>
                <?php
                // Nombre de séances déduit du libellé, pour le prix unitaire.
                preg_match( '/(\d+)/', $formule['detail'], $m );
                $seances = isset( $m[1] ) ? max( 1, (int) $m[1] ) : 1;
                ?>
                <tr>
                    <th scope="row"><?php echo esc_html( $formule['label'] ); ?></th>
                    <td class="color-subtext">
                        <?php echo esc_html( $formule['detail'] ); ?>
                        <?php if ( $formule['note'] ) : ?>
                            <span class="tarifs-table__note text-xs">
                                <?php echo esc_html( $formule['note'] ); ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="tarifs-table__price">
                        <?php echo wp_kses_post( wamv1_tarifs_montant( $formule['prix'] ) ); ?>
                    </td>
                    <td class="color-subtext">
                        <?php echo wp_kses_post( wamv1_tarifs_montant( $formule['prix'] / $seances ) ); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p class="tarifs-block__note text-xs color-subtext">
        Déplacement à domicile possible sur toutes les formules, avec supplément
        kilométrique indiqué au devis.
    </p>

</div>
