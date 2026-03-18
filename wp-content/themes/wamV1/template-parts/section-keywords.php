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
    array('text' => '{Bienveillance}', 'class' => 'kw-mallia',  'id' => 'kw-0'),
    array('text' => '{ Collectif }',   'class' => 'kw-cholo',   'id' => 'kw-1'),
    array('text' => '{ confiance }',   'class' => 'kw-outfit',  'id' => 'kw-2'),
);
?>
<section class="section-keywords relative flex flex-col items-center justify-center gap-6 py-14 text-center min-h-[400px] overflow-hidden w-full"
         aria-label="<?php esc_attr_e('Nos valeurs', 'wamv1'); ?>">

    <?php /* Particules flottantes (JS piloté) */ ?>
    <div class="keywords-particles absolute inset-0 pointer-events-none overflow-hidden z-0"
         id="keywords-particles"
         aria-hidden="true"></div>

    <?php /* Intro — .text-wam-sm .text-wam-subtext */ ?>
    <p class="relative z-10 text-wam-sm text-wam-subtext font-outfit max-w-[495px] m-0">
        <?php esc_html_e('Rejoins le studio pour un moment de :', 'wamv1'); ?>
    </p>

    <?php /* Scène — hauteur fixe pour éviter les sauts */ ?>
    <div class="keywords-stage relative z-10 min-h-[110px] flex items-center justify-center w-full"
         aria-live="polite"
         aria-atomic="true">
        <?php foreach ($keywords as $kw): ?>
            <span class="keyword-word <?php echo esc_attr($kw['class']); ?>"
                  id="<?php echo esc_attr($kw['id']); ?>"></span>
        <?php endforeach; ?>
    </div>

    <?php /* Bouton pause */ ?>
    <div class="relative z-10">
        <button id="pause-keywords" class="btn-pause" aria-pressed="false" type="button">
            <span class="btn-icon w-2.5 h-2.5"
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