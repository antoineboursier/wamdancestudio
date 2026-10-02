<?php
/**
 * Mon compte → « Mes commandes »
 *
 * Une carte par commande : en-tête interne (numéro, date, statut, total,
 * facture), puis chaque article avec sa photo, son intitulé et — si
 * l'inscription est encore modifiable — ses champs de correction affichés
 * directement, sans repli. Logique : inc/account-inscriptions.php.
 *
 * @package wamv1
 */

defined('ABSPATH') || exit;

$user_id   = get_current_user_id();
$commandes = wamv1_inscriptions_commandes($user_id);
?>

<div class="wam-commandes">
    <?php if (!$commandes) : ?>
        <?php wc_print_notice(esc_html__('Vous n\'avez pas encore passé de commande.', 'woocommerce'), 'notice'); ?>
    <?php endif; ?>

    <?php foreach ($commandes as $order) :
        $modifiable = wamv1_inscriptions_commande_modifiable($order, $user_id);
        $facture    = wamv1_inscriptions_facture($order);
        $famille    = wamv1_inscriptions_statut_famille($order->get_status());
        $items      = array_filter($order->get_items(), function ($item) {
            return $item instanceof WC_Order_Item_Product;
        });
        // Le prix par ligne ne s'affiche que s'il y a plusieurs articles :
        // sinon il répète le total de la commande, déjà en en-tête.
        $afficher_prix_ligne = count($items) > 1;
        ?>
        <article class="wam-commande<?php echo $famille === 'inactif' ? ' wam-commande--inactive' : ''; ?>">
            <header class="wam-commande__header">
                <div class="wam-commande__identite">
                    <span class="text-sm color-subtext">
                        Commande #<?php echo esc_html($order->get_order_number()); ?> · <?php echo esc_html(wc_format_datetime($order->get_date_created())); ?>
                    </span>
                    <span class="wam-commande__status wam-commande__status--<?php echo esc_attr($famille); ?>">
                        <?php echo esc_html(wc_get_order_status_name($order->get_status())); ?>
                    </span>
                </div>
                <div class="wam-commande__summary">
                    <span class="wam-commande__total"><?php echo wp_kses_post($order->get_formatted_order_total()); ?></span>
                    <?php if ($facture) : ?>
                        <a href="<?php echo esc_url($facture['url']); ?>" class="wam-commande__facture" download>
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z" />
                                <path d="M14 2v6h6" />
                            </svg>
                            <?php echo esc_html($facture['label']); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </header>

            <div class="wam-commande__items">
                <?php foreach ($items as $item_id => $item) :
                    $libelles     = wamv1_inscriptions_libelles($item);
                    $participants = wamv1_inscriptions_participants($item);
                    if (!wamv1_inscriptions_fiche_utile($item, $participants)) {
                        $participants = [];
                    }
                    $essai        = wamv1_inscriptions_est_essai($item);
                    $editable     = $modifiable && !$essai && $participants;
                    $saisie       = wamv1_inscriptions_saisie_en_erreur((int) $item_id);
                    $noms         = [];
                    foreach ($participants as $participant) {
                        $nom = wamv1_inscriptions_participant_nom($participant);
                        if ($nom !== '') {
                            $noms[] = ['titre' => $participant['titre'], 'nom' => $nom];
                        }
                    }
                    ?>
                    <div class="wam-commande-item" id="inscription-<?php echo esc_attr($item_id); ?>">
                        <?php if ($libelles['vignette_id']) :
                            // Cadre fixe, jamais ajusté à la hauteur du bloc : portrait
                            // 120×200 pour la photo d'un cours/stage (`wam-prof-thumb`,
                            // 400×600 recadrée), carré 120×120 pour l'illustration d'un
                            // produit générique, qu'un cadre portrait déformerait.
                            $portrait = $libelles['vignette_format'] === 'portrait';
                            $taille   = $portrait ? 'wam-prof-thumb' : 'woocommerce_thumbnail';
                            ?>
                            <div class="wam-commande-item__media wam-commande-item__media--<?php echo esc_attr($libelles['vignette_format']); ?>">
                                <?php echo wp_get_attachment_image($libelles['vignette_id'], $taille, false, ['alt' => $libelles['titre']]); ?>
                            </div>
                        <?php endif; ?>

                        <div class="wam-commande-item__body">
                            <div class="wam-commande-item__info">
                                <?php if ($libelles['nature'] !== $libelles['titre']) : ?>
                                    <span class="text-xs color-green"><?php echo esc_html($libelles['nature']); ?></span>
                                <?php endif; ?>
                                <h3 class="wam-commande-item__title"><?php echo esc_html($libelles['titre']); ?></h3>
                                <?php if ($libelles['precision']) : ?>
                                    <span class="text-sm color-subtext wam-commande-item__precision"><?php echo esc_html($libelles['precision']); ?></span>
                                <?php endif; ?>
                                <?php if ($afficher_prix_ligne) : ?>
                                    <span class="wam-commande-item__price">
                                        <?php echo wp_kses_post(wc_price($order->get_line_total($item, true, true))); ?>
                                        <?php if ($item->get_quantity() > 1) : ?>
                                            <span class="text-xs color-subtext fw-normal">× <?php echo esc_html($item->get_quantity()); ?></span>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php if ($editable) : ?>
                            <div class="wam-fiche">
                                <?php
                                /*
                                 * Bouton révélé par le JS uniquement : sans JS les champs
                                 * restent éditables et « Enregistrer » visible, donc le
                                 * formulaire fonctionne sans cette bascule.
                                 * Placé avant le formulaire dans le DOM (et à droite en
                                 * CSS) pour rester atteignable au clavier sans traverser
                                 * d'abord tous les champs.
                                 */
                                ?>
                                <button type="button" class="wam-fiche__toggle" aria-expanded="false" aria-controls="wam-fiche-form-<?php echo esc_attr($item_id); ?>" aria-label="Modifier les informations" hidden>
                                    <svg class="wam-fiche__icon wam-fiche__icon--edit" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z" />
                                    </svg>
                                    <svg class="wam-fiche__icon wam-fiche__icon--cancel" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M18 6 6 18M6 6l12 12" />
                                    </svg>
                                    <?php // Libellé affiché en mobile seulement, où la fiche est repliée. ?>
                                    <span class="wam-fiche__toggle-label">Éditer mes informations</span>
                                </button>

                                <form id="wam-fiche-form-<?php echo esc_attr($item_id); ?>" class="wam-inscription__form" method="post" action="<?php echo esc_url(wc_get_account_endpoint_url(WAMV1_INSCRIPTIONS_ENDPOINT) . '#inscription-' . $item_id); ?>">
                                    <?php foreach ($participants as $participant) : ?>
                                        <fieldset class="wam-inscription__participant">
                                            <?php if (count($participants) > 1) : ?>
                                                <legend class="text-sm fw-bold color-text"><?php echo esc_html($participant['titre']); ?></legend>
                                            <?php endif; ?>
                                            <div class="wam-inscription__fields">
                                                <?php foreach ($participant['champs'] as $key => $champ) :
                                                    $id     = 'wam-insc-' . $item_id . '-' . md5($key);
                                                    $valeur = ($saisie !== null && array_key_exists($key, $saisie)) ? $saisie[$key] : $champ['value'];
                                                    // data-initial : valeur en base, comparée par
                                                    // assets/js/account-inscriptions.js pour afficher « Enregistrer ».
                                                    $attrs = ['data-initial' => $champ['value']];
                                                    if ($champ['type'] === 'tel') {
                                                        $attrs += ['inputmode' => 'tel', 'autocomplete' => 'off'];
                                                    }
                                                    $args = [
                                                        'id'       => $id,
                                                        'type'     => $champ['type'],
                                                        'label'    => $champ['label'],
                                                        'required' => $champ['requis'],
                                                        'class'    => ['wam-inscription__field'],
                                                    ];
                                                    if (!$champ['modifiable']) {
                                                        $attrs                = ['disabled' => 'disabled'];
                                                        $args['description'] = 'Non modifiable en ligne : contactez-nous pour la corriger.';
                                                    }
                                                    $args['custom_attributes'] = $attrs;
                                                    woocommerce_form_field(wamv1_inscriptions_nom_champ($key), $args, $valeur);
                                                endforeach; ?>
                                            </div>
                                        </fieldset>
                                    <?php endforeach; ?>

                                    <div class="wam-inscription__actions">
                                        <?php wp_nonce_field('wamv1_inscription_' . $item_id, 'wamv1_inscription_nonce'); ?>
                                        <input type="hidden" name="wamv1_inscription_item" value="<?php echo esc_attr($item_id); ?>">
                                        <button type="submit" class="btn-primary">Enregistrer</button>
                                    </div>
                                </form>
                            </div>
                            <?php elseif ($noms) : ?>
                                <div class="wam-commande-item__participants">
                                    <?php foreach ($noms as $n) : ?>
                                        <p class="text-sm fw-bold color-text wam-commande-item__participant">
                                            <?php echo esc_html($n['titre']); ?> : <?php echo esc_html($n['nom']); ?>
                                        </p>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </article>
    <?php endforeach; ?>
</div>
