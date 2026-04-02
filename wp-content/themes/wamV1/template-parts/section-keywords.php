<?php
/**
 * Template Part : Section Keywords animée — Variante "Cursive Live"
 * Boucle infinie sur les mots (les mots se répètent en cycle sans fin).
 * Polices exactes Figma : .kw-mallia = Mallia 46px / .kw-cholo = Cholo Rhita 68px / .kw-outfit = Outfit 59px
 *
 * @package wamv1
 */

$icon_dir = get_template_directory_uri() . '/assets/images/';

$keywords = array(
    array('text' => '{Bienveillance}', 'class' => 'is-style-title-sign-lg',  'id' => 'kw-0'),
    array('text' => '{ Collectif }',   'class' => 'is-style-title-cool-lg',   'id' => 'kw-1'),
    array('text' => '{ confiance }',   'class' => 'is-style-title-norm-lg',  'id' => 'kw-2'),
);
?>
<section class="section-keywords"
         aria-label="<?php esc_attr_e('Nos valeurs', 'wamv1'); ?>">

    <?php /* Particules flottantes (JS piloté) */ ?>
    <div class="keywords-particles"
         id="keywords-particles"
         aria-hidden="true"></div>

    <?php /* Intro — subtext 16px */ ?>
    <p class="section-keywords__intro">
        <?php esc_html_e('Rejoins le studio pour un moment de :', 'wamv1'); ?>
    </p>

    <?php /* Scène — hauteur fixe pour éviter les sauts */ ?>
    <div class="keywords-stage"
         aria-live="polite"
         aria-atomic="true">
        <?php foreach ($keywords as $kw): ?>
            <span class="keyword-word <?php echo esc_attr($kw['class']); ?>"
                  id="<?php echo esc_attr($kw['id']); ?>"></span>
        <?php endforeach; ?>
    </div>

    <?php /* Bouton pause */ ?>
    <div class="section-keywords__pause">
        <button id="pause-keywords" class="btn-pause" aria-pressed="false" type="button">
            <span class="btn-icon btn-icon--xs"
                  style="--icon-url: url('<?php echo esc_url($icon_dir . 'pause.svg'); ?>');"
                  aria-hidden="true"
                  id="pause-keywords-icon">
            </span>
            <span><?php esc_html_e("Mettre en pause l'animation", 'wamv1'); ?></span>
        </button>
    </div>

    <?php /* Données JSON pour le JS — boucle infinie */ ?>
    <script>
        window.wamKeywords  = [
            <?php foreach ($keywords as $i => $kw): ?>
            { text: '<?php echo esc_js($kw['text']); ?>', id: '<?php echo esc_js($kw['id']); ?>' }<?php echo $i < count($keywords) - 1 ? ',' : ''; ?>
        <?php endforeach; ?>
        ];
        window.wamIconsDir = '<?php echo esc_js($icon_dir); ?>';
    </script>
</section>
