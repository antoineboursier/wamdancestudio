<?php
/**
 * Template part : FAQ tarifaire en accordéons natifs.
 *
 * Rendu du bloc Gutenberg `wam/tarifs-faq`. Les paires question / réponse
 * viennent de wamv1_tarifs_faq(), qui alimente aussi le nœud FAQPage du JSON-LD
 * (cf. le filtre `wamv1_schema_faq_items` dans inc/tarifs.php) : l'affiché et le
 * déclaré ne peuvent donc pas diverger.
 *
 * @package wamv1
 */

$faq = wamv1_tarifs_faq();

if ( ! $faq ) {
    return;
}
?>

<div class="tarifs-block tarifs-block--faq">
    <div class="tarifs-faq">
        <?php foreach ( $faq as $item ) : ?>
        <details class="tarifs-faq__item">
            <summary class="tarifs-faq__question">
                <?php echo esc_html( $item['q'] ); ?>
            </summary>
            <div class="tarifs-faq__answer text-sm color-subtext">
                <p><?php echo esc_html( $item['r'] ); ?></p>
            </div>
        </details>
        <?php endforeach; ?>
    </div>
</div>
