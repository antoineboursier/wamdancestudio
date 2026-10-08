/**
 * Bloc « Inscription newsletter » (wam-nl/form).
 *
 * Bloc dynamique : `save` retourne null, le HTML est produit par PHP à
 * l'affichage. L'aperçu dans l'éditeur passe par ServerSideRender, donc ce que
 * voit l'éditrice correspond au rendu public.
 *
 * ES5 avec createElement, sans JSX ni étape de build — même approche que
 * blocks/tarifs/index.js du thème.
 */
(function () {
	'use strict';

	var registerBlockType = wp.blocks.registerBlockType;
	var el = wp.element.createElement;
	var ServerSideRender = wp.serverSideRender;
	var InspectorControls = (wp.blockEditor || wp.editor).InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var TextareaControl = wp.components.TextareaControl;
	var Disabled = wp.components.Disabled;
	var __ = wp.i18n.__;

	registerBlockType('wam-nl/form', {
		edit: function (props) {
			var a = props.attributes;

			return el(
				'div',
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __('Formulaire', 'wam-newsletter'), initialOpen: true },
						el(TextControl, {
							label: __('Titre (facultatif)', 'wam-newsletter'),
							value: a.titre || '',
							onChange: function (v) {
								props.setAttributes({ titre: v });
							}
						}),
						el(TextareaControl, {
							label: __('Texte d’introduction (facultatif)', 'wam-newsletter'),
							value: a.texte || '',
							rows: 3,
							onChange: function (v) {
								props.setAttributes({ texte: v });
							}
						}),
						el(
							'p',
							{ className: 'components-base-control__help' },
							__('La liste d’inscription et le texte de consentement se règlent dans Newsletter → Réglages → Formulaire.', 'wam-newsletter')
						)
					)
				),
				// Disabled : dans l'éditeur, les champs du formulaire ne doivent pas
				// être saisissables, sinon un clic dedans vole le focus au bloc et
				// rend la sélection impossible.
				el(
					Disabled,
					null,
					el(ServerSideRender, {
						block: 'wam-nl/form',
						attributes: {
							titre: a.titre || '',
							texte: a.texte || ''
						}
					})
				)
			);
		},
		save: function () {
			return null;
		}
	});
})();
