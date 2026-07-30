/**
 * Blocs Gutenberg de la page Tarifs.
 *
 * Blocs *dynamiques* : aucun HTML n'est enregistré dans le contenu de la page
 * (`save` retourne null). Le rendu est produit côté PHP par les template-parts
 * `template-parts/tarifs-*.php`, qui lisent les tarifs à leur source
 * (WooCommerce, options du plugin, champs ACF des stages).
 *
 * Conséquence voulue : les montants restent justes même après un `ddev pull`,
 * et une évolution de tarif ne demande aucune réédition de la page.
 *
 * Écrit en ES5 avec createElement, sans JSX ni étape de build — même approche
 * que blocks/reviews/index.js.
 */
(function () {
	var registerBlockType = wp.blocks.registerBlockType;
	var createElement = wp.element.createElement;
	var ServerSideRender = wp.serverSideRender;
	var InspectorControls = (wp.blockEditor || wp.editor).InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var RangeControl = wp.components.RangeControl;
	var ToggleControl = wp.components.ToggleControl;
	var Placeholder = wp.components.Placeholder;

	/**
	 * Aperçu serveur, encadré d'un libellé pour rester identifiable dans l'éditeur
	 * (les blocs dynamiques n'ont pas de contenu propre à sélectionner).
	 */
	function preview(name, attributes, label) {
		return createElement(
			'div',
			{ className: 'wam-tarifs-block-preview' },
			createElement(
				'p',
				{ className: 'wam-tarifs-block-preview__label' },
				label
			),
			createElement(ServerSideRender, {
				block: name,
				attributes: attributes,
				httpMethod: 'POST',
				LoadingResponsePlaceholder: function () {
					return createElement(Placeholder, { label: 'Chargement des tarifs…' });
				}
			})
		);
	}

	/* ------------------------------------------------------------------
	   1. Grille dégressive des cours hebdomadaires
	   ------------------------------------------------------------------ */
	registerBlockType('wam/tarifs-grille', {
		title: 'Tarifs — grille des cours',
		description:
			'Tableau dégressif des cours hebdomadaires. Les montants sont calculés automatiquement depuis le prix WooCommerce et la remise multi-cours.',
		icon: 'editor-table',
		category: 'design',
		keywords: ['tarif', 'prix', 'cours', 'grille'],
		supports: { anchor: true, html: false },
		attributes: {
			max: { type: 'number', default: 5 }
		},
		edit: function (props) {
			return createElement(
				'div',
				null,
				createElement(
					InspectorControls,
					null,
					createElement(
						PanelBody,
						{ title: 'Réglages de la grille' },
						createElement(RangeControl, {
							label: 'Nombre de paliers affichés',
							help: 'La remise continue de s’appliquer au-delà du dernier palier.',
							value: props.attributes.max,
							onChange: function (val) {
								props.setAttributes({ max: val });
							},
							min: 1,
							max: 8
						})
					)
				),
				preview('wam/tarifs-grille', props.attributes, 'Grille des cours hebdomadaires (mise à jour automatique)')
			);
		},
		save: function () {
			return null;
		}
	});

	/* ------------------------------------------------------------------
	   2. Cartes des prestations ponctuelles
	   ------------------------------------------------------------------ */
	var CARDS = [
		{ key: 'stages', label: 'Stages & workshops' },
		{ key: 'particulier', label: 'Cours particulier' },
		{ key: 'evjf', label: 'EVJF, EVG & anniversaires' },
		{ key: 'location', label: 'Location du studio' },
		{ key: 'team', label: 'Team building' }
	];

	registerBlockType('wam/tarifs-prestations', {
		title: 'Tarifs — cartes prestations',
		description:
			'Cartes tarifaires des prestations ponctuelles : stages, cours particulier, EVJF, location, team building.',
		icon: 'grid-view',
		category: 'design',
		keywords: ['tarif', 'prix', 'prestation', 'carte'],
		supports: { anchor: true, html: false },
		attributes: {
			stages: { type: 'boolean', default: true },
			particulier: { type: 'boolean', default: true },
			evjf: { type: 'boolean', default: true },
			location: { type: 'boolean', default: true },
			team: { type: 'boolean', default: true }
		},
		edit: function (props) {
			var toggles = CARDS.map(function (card) {
				return createElement(ToggleControl, {
					key: card.key,
					label: card.label,
					checked: props.attributes[card.key],
					onChange: function (val) {
						var patch = {};
						patch[card.key] = val;
						props.setAttributes(patch);
					}
				});
			});

			return createElement(
				'div',
				null,
				createElement(
					InspectorControls,
					null,
					createElement(
						PanelBody,
						{ title: 'Cartes affichées' },
						toggles
					)
				),
				preview('wam/tarifs-prestations', props.attributes, 'Cartes des prestations (mise à jour automatique)')
			);
		},
		save: function () {
			return null;
		}
	});

	/* ------------------------------------------------------------------
	   3. Formules de mariage
	   ------------------------------------------------------------------ */
	registerBlockType('wam/tarifs-formules', {
		title: 'Tarifs — formules mariage',
		description:
			'Tableau des formules de chorégraphie de mariage, avec le prix par séance calculé automatiquement.',
		icon: 'heart',
		category: 'design',
		keywords: ['tarif', 'mariage', 'formule', 'bal'],
		supports: { anchor: true, html: false },
		edit: function (props) {
			return preview('wam/tarifs-formules', props.attributes, 'Formules de mariage (mise à jour automatique)');
		},
		save: function () {
			return null;
		}
	});

	/* ------------------------------------------------------------------
	   4. FAQ tarifaire
	   ------------------------------------------------------------------ */
	registerBlockType('wam/tarifs-faq', {
		title: 'Tarifs — FAQ',
		description:
			'Questions fréquentes sur les tarifs, en accordéons. Génère aussi le balisage FAQPage pour les moteurs de recherche.',
		icon: 'editor-help',
		category: 'design',
		keywords: ['faq', 'tarif', 'question', 'paiement'],
		supports: { anchor: true, html: false },
		edit: function (props) {
			return preview('wam/tarifs-faq', props.attributes, 'FAQ tarifs + balisage FAQPage (mise à jour automatique)');
		},
		save: function () {
			return null;
		}
	});
})();
