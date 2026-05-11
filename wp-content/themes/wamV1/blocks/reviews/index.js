const { registerBlockType } = wp.blocks;
const { createElement, Fragment } = wp.element;
const { RichText, InnerBlocks, InspectorControls } = wp.blockEditor || wp.editor;
const { PanelBody, RangeControl } = wp.components;

// 1. Sous-bloc : Carte d'avis individuelle
registerBlockType('wam/review-item', {
    title: 'Avis Individuel',
    parent: ['wam/reviews'],
    icon: 'format-quote',
    attributes: {
        author: { type: 'string', source: 'html', selector: '.review-card__author' },
        content: { type: 'string', source: 'html', selector: '.review-card__text' },
        rating: { type: 'number', default: 5 }
    },
    edit: function(props) {
        const { attributes, setAttributes } = props;
        return createElement('li', { className: 'review-item-edit', role: 'listitem' },
            createElement(InspectorControls, {},
                createElement(PanelBody, { title: 'Réglages de l\'avis' },
                    createElement(RangeControl, {
                        label: 'Note (étoiles)',
                        value: attributes.rating,
                        onChange: (val) => setAttributes({ rating: val }),
                        min: 1,
                        max: 5
                    })
                )
            ),
            createElement('article', { className: 'review-card' },
                createElement('div', { className: 'review-card__stars', 'aria-hidden': 'true' }, '★'.repeat(attributes.rating) + '☆'.repeat(5 - attributes.rating)),
                createElement(RichText, {
                    tagName: 'blockquote',
                    className: 'review-card__text',
                    value: attributes.content,
                    onChange: (val) => setAttributes({ content: val }),
                    placeholder: 'Le contenu de l\'avis...'
                }),
                createElement(RichText, {
                    tagName: 'cite',
                    className: 'review-card__author text-xs',
                    value: attributes.author,
                    onChange: (val) => setAttributes({ author: val }),
                    placeholder: 'Nom de l\'auteur...'
                })
            )
        );
    },
    save: function(props) {
        const { attributes } = props;
        return createElement('li', { role: 'listitem' },
            createElement('article', { className: 'review-card' },
                createElement('div', { className: 'review-card__stars', 'aria-hidden': 'true' }, '★'.repeat(attributes.rating) + '☆'.repeat(5 - attributes.rating)),
                createElement('span', { className: 'screen-reader-text' }, `Note : ${attributes.rating} sur 5`),
                createElement(RichText.Content, {
                    tagName: 'blockquote',
                    className: 'review-card__text',
                    value: attributes.content
                }),
                createElement(RichText.Content, {
                    tagName: 'cite',
                    className: 'review-card__author text-xs',
                    value: attributes.author
                })
            )
        );
    }
});

// 2. Bloc Conteneur : Grille d'avis avec InnerBlocks
const TEMPLATE = [
    ['wam/review-item'],
    ['wam/review-item'],
    ['wam/review-item'],
    ['wam/review-item'],
    ['wam/review-item'],
    ['wam/review-item']
];

registerBlockType('wam/reviews', {
    title: 'Avis WAM',
    icon: 'star-filled',
    category: 'design',
    supports: {
        anchor: true
    },
    edit: function(props) {
        // En admin, on affiche juste la grille sans le badge Google ni le lien (selon la demande)
        return createElement('div', { className: 'section-reviews' },
            createElement('div', { className: 'section-reviews__header' },
                createElement('h2', { className: 'title-sign-md color-pink' }, 'C\'est vous qui le dites...')
            ),
            createElement('div', { className: 'section-reviews__slider-container' },
                createElement('div', { className: 'section-reviews__grid' },
                    createElement(InnerBlocks, {
                        allowedBlocks: ['wam/review-item'],
                        template: TEMPLATE,
                        templateLock: false // Permet d'ajouter ou supprimer des avis si besoin
                    })
                )
            )
        );
    },
    save: function() {
        // Le contenu généré par les InnerBlocks sera passé au render_callback PHP ($content)
        return createElement(InnerBlocks.Content, {});
    }
});
