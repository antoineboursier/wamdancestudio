/**
 * Marquage des embeds verticaux (YouTube Shorts) DANS L'ÉDITEUR.
 *
 * Pendant côté éditeur de `wamv1_mark_portrait_embeds()` (inc/performance.php).
 * Les deux sont nécessaires et ne se recouvrent pas :
 *
 *   - le filtre PHP agit sur `render_block`, donc sur le front ;
 *   - la toile de Gutenberg rend le bloc `core/embed` côté client, sans
 *     jamais passer par PHP — sans ce script, l'éditrice verrait un Short
 *     en pleine largeur alors que le front le bride à 600px.
 *
 * Comme en PHP, le seul signal disponible est le `/shorts/` de l'URL :
 * Gutenberg n'émet aucune classe de ratio pour les vidéos verticales.
 *
 * ES5 avec createElement, sans JSX ni étape de build — même approche que
 * blocks/tarifs/index.js.
 */
(function () {
	if (!window.wp || !wp.hooks || !wp.element || !wp.compose) {
		return;
	}

	var createElement = wp.element.createElement;
	var createHigherOrderComponent = wp.compose.createHigherOrderComponent;

	var PORTRAIT_CLASS = 'wam-embed--portrait';

	/**
	 * @param {Object} props Propriétés du bloc dans l'éditeur.
	 * @return {boolean} true si le bloc est un embed vertical.
	 */
	function isPortraitEmbed(props) {
		return (
			props.name === 'core/embed' &&
			!!props.attributes &&
			typeof props.attributes.url === 'string' &&
			props.attributes.url.indexOf('/shorts/') !== -1
		);
	}

	/**
	 * Applique la classe sur l'enveloppe du bloc dans la toile de l'éditeur.
	 */
	var withPortraitClass = createHigherOrderComponent(function (BlockListBlock) {
		return function (props) {
			if (!isPortraitEmbed(props)) {
				return createElement(BlockListBlock, props);
			}

			var className = props.className ? props.className + ' ' + PORTRAIT_CLASS : PORTRAIT_CLASS;

			return createElement(
				BlockListBlock,
				Object.assign({}, props, { className: className })
			);
		};
	}, 'withPortraitClass');

	wp.hooks.addFilter(
		'editor.BlockListBlock',
		'wamv1/embed-portrait-class',
		withPortraitClass
	);
})();
