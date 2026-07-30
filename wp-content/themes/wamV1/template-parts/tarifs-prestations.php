<?php
/**
 * Template part : cartes tarifaires des prestations ponctuelles.
 *
 * Rendu du bloc Gutenberg `wam/tarifs-prestations`. Chaque carte peut être
 * masquée depuis l'éditeur via les cases à cocher du bloc.
 *
 * @package wamv1
 */

$show = [
    'stages'    => (bool) ( $args['stages']    ?? true ),
    'particulier' => (bool) ( $args['particulier'] ?? true ),
    'evjf'      => (bool) ( $args['evjf']      ?? true ),
    'location'  => (bool) ( $args['location']  ?? true ),
    'team'      => (bool) ( $args['team']      ?? true ),
];

$stage_min = wamv1_tarifs_stage_prix_min();
$forfaits  = wamv1_tarifs_forfaits_manuels();
$evjf      = $forfaits['evjf'];
$location  = $forfaits['location'];

$formules_prive = wamv1_tarifs_formules( [
    [
        'id'     => 675,
        'label'  => 'Cours particulier',
        'detail' => '1 séance d’1 heure',
        'note'   => 'Seul·e ou à deux, au même tarif',
    ],
] );
?>

<div class="tarifs-block tarifs-block--prestations">
    <div class="tarifs-cards">

        <?php if ( $show['stages'] ) : ?>
        <article class="tarifs-card">
            <h3 class="tarifs-card__title is-style-title-norm-md">Stages &amp; workshops</h3>
            <p class="tarifs-card__price">
                <?php if ( $stage_min > 0 ) : ?>
                    <span class="tarifs-card__prefix text-sm color-subtext">dès</span>
                    <?php echo wp_kses_post( wamv1_tarifs_montant( $stage_min ) ); ?>
                <?php else : ?>
                    <span class="text-md">Selon la session</span>
                <?php endif; ?>
            </p>
            <p class="tarifs-card__detail text-sm color-subtext">
                Le tarif dépend de l’intervenant·e, du format et de la durée.
                Il est affiché sur la fiche de chaque stage, avec les tarifs réduits
                éventuels.
            </p>
            <a class="tarifs-card__link" href="<?php echo esc_url( home_url( '/stages-workshop-ateliers/' ) ); ?>">
                Voir les stages à venir
            </a>
        </article>
        <?php endif; ?>

        <?php if ( $show['particulier'] ) : ?>
            <?php foreach ( $formules_prive as $formule ) : ?>
            <article class="tarifs-card">
                <h3 class="tarifs-card__title is-style-title-norm-md">
                    <?php echo esc_html( $formule['label'] ); ?>
                </h3>
                <p class="tarifs-card__price">
                    <?php echo wp_kses_post( wamv1_tarifs_montant( $formule['prix'] ) ); ?>
                </p>
                <p class="tarifs-card__detail text-sm color-subtext">
                    <?php echo esc_html( $formule['detail'] ); ?><br>
                    <?php echo esc_html( $formule['note'] ); ?>.
                    Déplacement à domicile possible, avec supplément kilométrique
                    communiqué au devis.
                </p>
                <a class="tarifs-card__link" href="<?php echo esc_url( home_url( '/cours-particulier-prive/' ) ); ?>">
                    Cours particuliers &amp; privés
                </a>
            </article>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ( $show['evjf'] ) : ?>
        <article class="tarifs-card">
            <h3 class="tarifs-card__title is-style-title-norm-md">EVJF, EVG &amp; anniversaires</h3>
            <p class="tarifs-card__price">
                <?php echo wp_kses_post( wamv1_tarifs_montant( $evjf['prix'] ) ); ?>
            </p>
            <p class="tarifs-card__detail text-sm color-subtext">
                <?php echo esc_html( $evjf['duree'] ); ?> de cours pour le groupe,
                jusqu’à <?php echo esc_html( $evjf['participants'] ); ?> personnes,
                sur une chorégraphie choisie dans notre répertoire.
                Option chorégraphie sur mesure&nbsp;:
                +<?php echo wp_kses_post( wamv1_tarifs_montant( $evjf['option_choreo'] ) ); ?>.
                Hors du studio&nbsp;: frais de déplacement en supplément.
            </p>
            <a class="tarifs-card__link" href="<?php echo esc_url( home_url( '/evjf-evg-anniversaire/' ) ); ?>">
                EVJF, EVG &amp; anniversaires
            </a>
        </article>
        <?php endif; ?>

        <?php if ( $show['location'] ) : ?>
        <article class="tarifs-card">
            <h3 class="tarifs-card__title is-style-title-norm-md">Location du studio</h3>
            <p class="tarifs-card__price">
                <?php echo wp_kses_post( wamv1_tarifs_montant( $location['prix_bas'] ) ); ?>
                <span class="tarifs-card__suffix text-sm color-subtext">
                    à <?php echo wp_kses_post( wamv1_tarifs_montant( $location['prix_haut'] ) ); ?> / heure
                </span>
            </p>
            <p class="tarifs-card__detail text-sm color-subtext">
                <?php echo wp_kses_post( wamv1_tarifs_montant( $location['prix_bas'] ) ); ?>
                l’heure en semaine de 8 h à 18 h et pendant les vacances scolaires,
                <?php echo wp_kses_post( wamv1_tarifs_montant( $location['prix_haut'] ) ); ?>
                l’heure en soirée et le week-end.
                Dégressif&nbsp;: −<?php echo esc_html( $location['remise_2h'] ); ?>&nbsp;%
                dès 2 heures consécutives,
                −<?php echo esc_html( $location['remise_4h'] ); ?>&nbsp;% dès 4 heures.
                Parquet de <?php echo esc_html( $location['surface'] ); ?> m², miroirs,
                sonorisation, vestiaires, jusqu’à
                <?php echo esc_html( $location['capacite'] ); ?> personnes.
            </p>
            <a class="tarifs-card__link" href="<?php echo esc_url( home_url( '/location-du-studio/' ) ); ?>">
                Louer le studio
            </a>
        </article>
        <?php endif; ?>

        <?php if ( $show['team'] ) : ?>
        <article class="tarifs-card">
            <h3 class="tarifs-card__title is-style-title-norm-md">Team building en entreprise</h3>
            <p class="tarifs-card__price"><span class="text-md">Sur devis</span></p>
            <p class="tarifs-card__detail text-sm color-subtext">
                Le tarif dépend du nombre de participant·es, de la durée et du lieu —
                au studio ou dans vos locaux. Réponse chiffrée sous quelques jours
                après un premier échange.
            </p>
            <a class="tarifs-card__link" href="<?php echo esc_url( home_url( '/team-building/' ) ); ?>">
                Team building danse
            </a>
        </article>
        <?php endif; ?>

    </div>
</div>
