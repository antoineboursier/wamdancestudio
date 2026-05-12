const { registerBlockType } = wp.blocks;
const { createElement, Fragment } = wp.element;
const { RichText, InnerBlocks, InspectorControls } = wp.blockEditor || wp.editor;
const { PanelBody, RangeControl } = wp.components;

/**
 * 1. Sous-bloc : Carte d'avis individuelle
 * Inclut désormais les micro-données Schema.org pour le SEO
 */
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
                    placeholder: 'Le contenu de l\'avis...',
                    multiline: 'p' // Permet de gérer les paragraphes (Entrée)
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
            createElement('article', { 
                className: 'review-card',
                itemScope: true,
                itemType: 'https://schema.org/Review'
            },
                // SEO : On ajoute les données masquées pour Schema.org
                createElement('div', { 
                    itemProp: 'reviewRating', 
                    itemScope: true, 
                    itemType: 'https://schema.org/Rating',
                    style: { display: 'none' }
                },
                    createElement('meta', { itemProp: 'ratingValue', content: attributes.rating }),
                    createElement('meta', { itemProp: 'bestRating', content: '5' })
                ),
                
                createElement('div', { className: 'review-card__stars', 'aria-hidden': 'true' }, '★'.repeat(attributes.rating) + '☆'.repeat(5 - attributes.rating)),
                createElement('span', { className: 'screen-reader-text' }, `Note : ${attributes.rating} sur 5`),
                
                createElement(RichText.Content, {
                    tagName: 'blockquote',
                    className: 'review-card__text',
                    itemProp: 'reviewBody',
                    value: attributes.content
                }),
                
                createElement('cite', { 
                    className: 'review-card__author text-xs',
                    itemProp: 'author',
                    itemScope: true,
                    itemType: 'https://schema.org/Person'
                },
                    createElement('span', { itemProp: 'name' }, 
                        createElement(RichText.Content, { value: attributes.author })
                    )
                )
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
        anchor: true,
        className: false // On désactive la classe automatique pour éviter les doublons
    },
    edit: function(props) {
        return createElement('div', { className: 'section-reviews' },
            createElement('div', { className: 'section-reviews__header' },
                createElement('h2', { className: 'title-sign-md color-pink' }, 'C\'est vous qui le dites...')
            ),
            createElement('div', { className: 'section-reviews__slider-container' },
                createElement('div', { className: 'section-reviews__grid' },
                    createElement(InnerBlocks, {
                        allowedBlocks: ['wam/review-item'],
                        template: TEMPLATE,
                        templateLock: false
                    })
                )
            )
        );
    },
    save: function() {
        return createElement('ul', { 
            className: 'section-reviews__grid',
            role: 'list'
        }, 
            createElement(InnerBlocks.Content, {})
        );
    }
});
