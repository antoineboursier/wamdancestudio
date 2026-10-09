/**
 * Blocs de l'éditeur de newsletter (wam-nl/*).
 *
 * Tous dynamiques : `save` rend null, le HTML part de PHP. L'aperçu passe par
 * ServerSideRender, donc le canevas montre LE HTML QUI SERA ENVOYÉ, pas une
 * approximation. C'est ce qui évite la mauvaise surprise au moment du test.
 *
 * Exception assumée : le bouton est dessiné côté JavaScript pour que son libellé
 * soit modifiable directement dans le canevas (RichText). Un aller-retour
 * serveur à chaque lettre tapée serait inutilisable.
 *
 * ES5 avec createElement, sans JSX ni étape de build : convention du projet
 * (cf. blocks/tarifs/index.js du thème). Rien à compiler pour déployer.
 */
(function () {
	'use strict';

	var registerBlockType = wp.blocks.registerBlockType;
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;

	var blockEditor = wp.blockEditor || wp.editor;
	var InspectorControls = blockEditor.InspectorControls;
	var BlockControls = blockEditor.BlockControls;
	var useBlockProps = blockEditor.useBlockProps;
	var RichText = blockEditor.RichText;
	var MediaUpload = blockEditor.MediaUpload;
	var MediaUploadCheck = blockEditor.MediaUploadCheck;

	var C = wp.components;
	var PanelBody = C.PanelBody;
	var TextControl = C.TextControl;
	var TextareaControl = C.TextareaControl;
	var SelectControl = C.SelectControl;
	var ToggleControl = C.ToggleControl;
	var RangeControl = C.RangeControl;
	var Button = C.Button;
	var Notice = C.Notice;
	var Spinner = C.Spinner;
	var ButtonGroup = C.ButtonGroup;
	var ToolbarGroup = C.ToolbarGroup;
	var ToolbarButton = C.ToolbarButton;

	var ServerSideRender = wp.serverSideRender || C.ServerSideRender;
	var apiFetch = wp.apiFetch;
	var __ = wp.i18n.__;

	var reglages = window.wamNlEditor || {};
	var couleurs = reglages.colors || {};
	var ns = reglages.restNamespace || 'wam-nl/v1';

	/** Palette proposée pour les boutons et le séparateur. */
	function palette() {
		return [
			{ label: __('Jaune WAM', 'wam-newsletter'), value: couleurs.accent || '#FBD150' },
			{ label: __('Turquoise WAM', 'wam-newsletter'), value: couleurs.separator || '#00D6B2' },
			{ label: __('Crème', 'wam-newsletter'), value: couleurs.text || '#F9F4EB' }
		];
	}

	/**
	 * Construit une suite de vrais blocs Gutenberg à partir des contenus
	 * résolus par /posts-resolve.
	 *
	 * Reproduit la mise en page du rendu dynamique (séparateur, titre centré,
	 * colonnes image/texte, alternance gauche/droite) mais avec des blocs
	 * natifs indépendants : chaque titre, chaque image, chaque paragraphe
	 * devient modifiable, déplaçable, supprimable, et on peut intercaler
	 * n'importe quel autre bloc entre deux contenus.
	 *
	 * Sans image, les blocs de texte sont posés à plat (pas de colonnes vides) —
	 * c'est aussi ce que fait le rendu dynamique.
	 */
	function construireBlocsDepuisItems(items, a, texteBoutonDefaut) {
		var createBlock = wp.blocks.createBlock;
		var blocs = [];

		items.forEach(function (item, index) {
			blocs.push(createBlock('wam-nl/separator', {}));

			var titreContenu = item.link
				? '<a href="' + item.link + '">' + item.title + '</a>'
				: item.title;
			blocs.push(
				createBlock('core/heading', {
					level: 2,
					textAlign: 'center',
					content: titreContenu
				})
			);

			// Sous-titre en vert WAM (« Turquoise »), un cran sous le titre ; date et
			// horaire sur leur propre ligne : comme le rendu dynamique.
			if (item.subtitle) {
				blocs.push(
					createBlock('core/paragraph', {
						align: 'center',
						textColor: 'separator',
						fontSize: 'large',
						content: '<strong>' + item.subtitle + '</strong>'
					})
				);
			}
			var quand = a.showDate ? item.when || item.date : '';
			if (quand) {
				blocs.push(
					createBlock('core/paragraph', {
						align: 'center',
						content: '<strong>' + quand + '</strong>'
					})
				);
			}

			var blocsTexte = [];
			if (a.showExcerpt && item.excerpt) {
				blocsTexte.push(createBlock('core/paragraph', { content: item.excerpt }));
			}
			if (item.price) {
				blocsTexte.push(
					createBlock('core/paragraph', {
						content: wp.i18n.sprintf(__('Dès %s €', 'wam-newsletter'), item.price)
					})
				);
			}
			if (a.showButton) {
				blocsTexte.push(
					createBlock('wam-nl/button', {
						text: a.buttonText || texteBoutonDefaut,
						url: item.link,
						variant: a.buttonVariant || 'plein',
						color: a.buttonColor || ''
					})
				);
			}

			if (a.showImage && item.image) {
				var blocImage = createBlock('core/image', {
					id: item.image.id,
					url: item.image.url,
					alt: item.image.alt,
					linkDestination: 'custom',
					href: item.link
				});

				// L'ordre des DEUX blocs colonne détermine gauche/droite : une fois
				// détaché, on peut aussi les inverser à la main (glisser-déposer
				// dans la vue Liste), sans repasser par ce bouton.
				var colonneImage = createBlock('core/column', {}, [blocImage]);
				var colonneTexte = createBlock('core/column', {}, blocsTexte);
				var inverse = a.alternate && index % 2 === 1;

				blocs.push(
					createBlock('core/columns', {}, inverse ? [colonneTexte, colonneImage] : [colonneImage, colonneTexte])
				);
			} else {
				blocs = blocs.concat(blocsTexte);
			}
		});

		return blocs;
	}

	/**
	 * Aperçu serveur encadré d'un libellé.
	 *
	 * Les blocs dynamiques n'ont pas de contenu propre à cliquer : sans ce
	 * bandeau, on ne sait pas quel bloc on vient de sélectionner.
	 */
	function apercu(nom, attributs, libelle, aide) {
		return el(
			'div',
			{ className: 'wam-nl-bloc' },
			el(
				'div',
				{ className: 'wam-nl-bloc__etiquette' },
				libelle,
				aide ? el('span', { className: 'wam-nl-bloc__aide' }, aide) : null
			),
			el(
				'div',
				{ className: 'wam-nl-bloc__rendu' },
				el(ServerSideRender, {
					block: nom,
					attributes: attributs,
					EmptyResponsePlaceholder: function () {
						return el('p', { className: 'wam-nl-bloc__vide' }, __('Rien à afficher pour ce réglage.', 'wam-newsletter'));
					},
					LoadingResponsePlaceholder: function () {
						return el('div', { className: 'wam-nl-bloc__chargement' }, el(Spinner, null));
					}
				})
			)
		);
	}

	/**
	 * Aperçu serveur SANS bandeau d'étiquette.
	 *
	 * Utilisé pour l'entête et le pied de page : le bandeau faisait doublon
	 * avec le bouton de verrou (qui dit déjà « cette zone est verrouillée »),
	 * et encombrait l'écran pour deux blocs qu'on ne modifie presque jamais.
	 */
	function apercuSansEtiquette(nom, attributs) {
		return el(
			'div',
			{ className: 'wam-nl-bloc wam-nl-bloc--sans-etiquette' },
			el(
				'div',
				{ className: 'wam-nl-bloc__rendu' },
				el(ServerSideRender, {
					block: nom,
					attributes: attributs,
					EmptyResponsePlaceholder: function () {
						return el('p', { className: 'wam-nl-bloc__vide' }, __('Rien à afficher pour ce réglage.', 'wam-newsletter'));
					},
					LoadingResponsePlaceholder: function () {
						return el('div', { className: 'wam-nl-bloc__chargement' }, el(Spinner, null));
					}
				})
			)
		);
	}

	/**
	 * Bouton de verrou, visible directement dans la barre d'outils du bloc.
	 *
	 * Remplace le bandeau de texte « verrouillé en haut/bas » : l'icône seule
	 * (cadenas fermé ou ouvert) dit déjà l'état, et un clic permet d'agir sans
	 * passer par le menu « ... ». Déverrouiller demande une confirmation —
	 * c'est ce qui retire la protection contre un déplacement ou une
	 * suppression accidentelle ; reverrouiller n'en a pas besoin, c'est sans
	 * risque.
	 */
	function BoutonVerrou(props) {
		var verrouille = !!(props.lock && props.lock.move && props.lock.remove);

		return el(
			ToolbarGroup,
			null,
			el(ToolbarButton, {
				icon: verrouille ? 'lock' : 'unlock',
				label: verrouille
					? __('Zone verrouillée - cliquer pour déverrouiller', 'wam-newsletter')
					: __('Zone déverrouillée - cliquer pour verrouiller', 'wam-newsletter'),
				onClick: function () {
					if (verrouille) {
						if (
							!window.confirm(
								__(
									'Déverrouiller cette zone permet de la déplacer ou de la supprimer par erreur. Continuer ?',
									'wam-newsletter'
								)
							)
						) {
							return;
						}
						props.onToggle({});
					} else {
						props.onToggle({ move: true, remove: true });
					}
				}
			})
		);
	}

	// ------------------------------------------------------------------
	// Entête
	// ------------------------------------------------------------------

	registerBlockType('wam-nl/header', {
		edit: function (props) {
			var a = props.attributes;
			var blockProps = useBlockProps ? useBlockProps() : {};

			return el(
				'div',
				blockProps,
				BlockControls
					? el(BlockControls, null, el(BoutonVerrou, {
							lock: a.lock,
							onToggle: function (valeur) {
								props.setAttributes({ lock: valeur });
							}
					  }))
					: null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __('Bannière', 'wam-newsletter'), initialOpen: true },
						el(
							'p',
							{ className: 'components-base-control__help' },
							__('Sans image choisie, la bannière habituelle de WAM est utilisée.', 'wam-newsletter')
						),
						MediaUploadCheck
							? el(
									MediaUploadCheck,
									null,
									el(MediaUpload, {
										allowedTypes: ['image'],
										value: a.attachmentId,
										onSelect: function (media) {
											props.setAttributes({ attachmentId: media.id, alt: media.alt || a.alt });
										},
										render: function (obj) {
											return el(
												Fragment,
												null,
												el(
													Button,
													{ variant: 'secondary', onClick: obj.open },
													a.attachmentId
														? __('Changer l’image', 'wam-newsletter')
														: __('Choisir une image', 'wam-newsletter')
												),
												a.attachmentId
													? el(
															Button,
															{
																variant: 'link',
																isDestructive: true,
																onClick: function () {
																	props.setAttributes({ attachmentId: 0 });
																}
															},
															__('Revenir à la bannière par défaut', 'wam-newsletter')
													  )
													: null
											);
										}
									})
							  )
							: null,
						el(TextControl, {
							label: __('Texte alternatif', 'wam-newsletter'),
							help: __('Lu par les lecteurs d’écran et affiché si l’image ne charge pas.', 'wam-newsletter'),
							value: a.alt || '',
							onChange: function (v) {
								props.setAttributes({ alt: v });
							}
						}),
						el(TextControl, {
							label: __('Lien de la bannière', 'wam-newsletter'),
							type: 'url',
							help: __('Vide = page d’accueil du site.', 'wam-newsletter'),
							value: a.url || '',
							onChange: function (v) {
								props.setAttributes({ url: v });
							}
						})
					)
				),
				apercuSansEtiquette('wam-nl/header', a)
			);
		},
		save: function () {
			return null;
		}
	});

	// ------------------------------------------------------------------
	// Pied de page
	// ------------------------------------------------------------------

	registerBlockType('wam-nl/footer', {
		edit: function (props) {
			var a = props.attributes;
			var blockProps = useBlockProps ? useBlockProps() : {};

			return el(
				'div',
				blockProps,
				BlockControls
					? el(BlockControls, null, el(BoutonVerrou, {
							lock: a.lock,
							onToggle: function (valeur) {
								props.setAttributes({ lock: valeur });
							}
					  }))
					: null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __('Pied de page', 'wam-newsletter'), initialOpen: true },
						el(
							Notice,
							{ status: 'info', isDismissible: false },
							__('Le lien de désinscription est obligatoire : ce bloc ne peut pas être supprimé.', 'wam-newsletter')
						),
						el(TextareaControl, {
							label: __('Phrase supplémentaire (facultatif)', 'wam-newsletter'),
							value: a.extra || '',
							rows: 2,
							onChange: function (v) {
								props.setAttributes({ extra: v });
							}
						})
					)
				),
				apercuSansEtiquette('wam-nl/footer', a)
			);
		},
		save: function () {
			return null;
		}
	});

	// ------------------------------------------------------------------
	// Bouton
	// ------------------------------------------------------------------

	registerBlockType('wam-nl/button', {
		edit: function (props) {
			var a = props.attributes;
			var blockProps = useBlockProps ? useBlockProps() : {};
			var couleur = a.color || couleurs.accent || '#FBD150';
			var plein = 'contour' !== a.variant;

			var style = {
				display: 'inline-block',
				fontFamily: 'Arial, Helvetica, sans-serif',
				fontSize: '16px',
				fontWeight: 'bold',
				lineHeight: '39px',
				padding: '0 24px',
				borderRadius: '4px',
				border: '2px solid ' + couleur,
				backgroundColor: plein ? couleur : 'transparent',
				color: plein ? couleurs.background || '#131620' : couleur,
				textDecoration: 'none',
				cursor: 'text'
			};

			return el(
				'div',
				blockProps,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __('Bouton', 'wam-newsletter'), initialOpen: true },
						el(TextControl, {
							label: __('Lien', 'wam-newsletter'),
							type: 'url',
							placeholder: 'https://',
							value: a.url || '',
							onChange: function (v) {
								props.setAttributes({ url: v });
							}
						}),
						el(SelectControl, {
							label: __('Style', 'wam-newsletter'),
							value: a.variant || 'plein',
							options: [
								{ label: __('Plein', 'wam-newsletter'), value: 'plein' },
								{ label: __('Contour', 'wam-newsletter'), value: 'contour' }
							],
							onChange: function (v) {
								props.setAttributes({ variant: v });
							}
						}),
						el(SelectControl, {
							label: __('Couleur', 'wam-newsletter'),
							value: couleur,
							options: palette(),
							onChange: function (v) {
								props.setAttributes({ color: v });
							}
						}),
						el(SelectControl, {
							label: __('Alignement', 'wam-newsletter'),
							value: a.align || 'left',
							options: [
								{ label: __('À gauche', 'wam-newsletter'), value: 'left' },
								{ label: __('Centré', 'wam-newsletter'), value: 'center' },
								{ label: __('À droite', 'wam-newsletter'), value: 'right' }
							],
							onChange: function (v) {
								props.setAttributes({ align: v });
							}
						})
					)
				),
				el(
					'div',
					{ style: { textAlign: a.align || 'left' } },
					el(RichText, {
						tagName: 'span',
						style: style,
						value: a.text || '',
						allowedFormats: [],
						placeholder: __('Texte du bouton', 'wam-newsletter'),
						onChange: function (v) {
							props.setAttributes({ text: v });
						}
					})
				),
				!a.url
					? el(
							'p',
							{ className: 'wam-nl-bloc__alerte' },
							__('Ce bouton n’a pas encore de lien.', 'wam-newsletter')
					  )
					: null
			);
		},
		save: function () {
			return null;
		}
	});

	// ------------------------------------------------------------------
	// Séparateur
	// ------------------------------------------------------------------

	registerBlockType('wam-nl/separator', {
		edit: function (props) {
			var a = props.attributes;
			var blockProps = useBlockProps ? useBlockProps() : {};
			var couleur = a.color || couleurs.separator || '#00D6B2';

			return el(
				'div',
				blockProps,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __('Séparateur', 'wam-newsletter'), initialOpen: true },
						el(SelectControl, {
							label: __('Couleur', 'wam-newsletter'),
							value: couleur,
							options: palette(),
							onChange: function (v) {
								props.setAttributes({ color: v });
							}
						})
					)
				),
				el('div', {
					style: {
						borderTop: '2px dotted ' + couleur,
						margin: '13px 0'
					}
				})
			);
		},
		save: function () {
			return null;
		}
	});

	// ------------------------------------------------------------------
	// Espacement
	// ------------------------------------------------------------------

	registerBlockType('wam-nl/spacer', {
		edit: function (props) {
			var a = props.attributes;
			var blockProps = useBlockProps ? useBlockProps() : {};
			var hauteur = a.height || 24;

			return el(
				'div',
				blockProps,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __('Espacement', 'wam-newsletter'), initialOpen: true },
						el(RangeControl, {
							label: __('Hauteur (pixels)', 'wam-newsletter'),
							value: hauteur,
							min: 4,
							max: 120,
							step: 4,
							onChange: function (v) {
								props.setAttributes({ height: v || 24 });
							}
						})
					)
				),
				el(
					'div',
					{
						className: 'wam-nl-espacement',
						style: { height: hauteur + 'px' }
					},
					el('span', null, hauteur + ' px')
				)
			);
		},
		save: function () {
			return null;
		}
	});

	// ------------------------------------------------------------------
	// Contenus WAM
	// ------------------------------------------------------------------

	/** Options de type de contenu, libellés en français courant. */
	var TYPES = [
		{ label: __('Stages', 'wam-newsletter'), value: 'stages' },
		{ label: __('Cours', 'wam-newsletter'), value: 'cours' },
		{ label: __('Articles du blog', 'wam-newsletter'), value: 'post' },
		{ label: __('Pages', 'wam-newsletter'), value: 'page' }
	];

	/** Taxonomie de filtre proposée selon le type de contenu. */
	function taxonomiePour(type) {
		if ('cours' === type) {
			return 'cat_cours';
		}
		if ('post' === type) {
			return 'category';
		}
		return '';
	}

	function SelecteurManuel(props) {
		var a = props.attributes;
		var setAttributes = props.setAttributes;
		var recherche = useState('');
		var terme = recherche[0];
		var setTerme = recherche[1];
		var resultats = useState([]);
		var liste = resultats[0];
		var setListe = resultats[1];
		var choisis = useState([]);
		var titres = choisis[0];
		var setTitres = choisis[1];

		useEffect(
			function () {
				apiFetch({
					path: ns + '/content-search?postType=' + encodeURIComponent(a.postType) + '&search=' + encodeURIComponent(terme)
				})
					.then(setListe)
					.catch(function () {
						setListe([]);
					});
			},
			[terme, a.postType]
		);

		// Les titres des contenus déjà choisis, pour les afficher même s'ils ne
		// sont pas dans les résultats de recherche courants.
		useEffect(
			function () {
				var ids = a.postIds || [];
				if (!ids.length) {
					setTitres([]);
					return;
				}
				apiFetch({ path: ns + '/content-search?postType=' + encodeURIComponent(a.postType) + '&search=' })
					.then(function (tous) {
						var connus = {};
						(tous || []).forEach(function (p) {
							connus[p.id] = libelleContenu(p, false);
						});
						(liste || []).forEach(function (p) {
							connus[p.id] = libelleContenu(p, false);
						});
						setTitres(
							ids.map(function (id) {
								return { id: id, title: connus[id] || '#' + id };
							})
						);
					})
					.catch(function () {
						setTitres(
							ids.map(function (id) {
								return { id: id, title: '#' + id };
							})
						);
					});
			},
			[(a.postIds || []).join(','), a.postType]
		);

		function basculer(id) {
			var ids = (a.postIds || []).slice();
			var i = ids.indexOf(id);
			if (i >= 0) {
				ids.splice(i, 1);
			} else {
				ids.push(id);
			}
			setAttributes({ postIds: ids });
		}

		function deplacer(index, delta) {
			var ids = (a.postIds || []).slice();
			var cible = index + delta;
			if (cible < 0 || cible >= ids.length) {
				return;
			}
			var tmp = ids[index];
			ids[index] = ids[cible];
			ids[cible] = tmp;
			setAttributes({ postIds: ids });
		}

		return el(
			Fragment,
			null,
			titres.length
				? el(
						'div',
						{ className: 'wam-nl-choisis' },
						el('p', { className: 'wam-nl-choisis__titre' }, __('Dans cet ordre :', 'wam-newsletter')),
						titres.map(function (p, index) {
							return el(
								'div',
								{ key: p.id, className: 'wam-nl-choisis__item' },
								el('span', null, index + 1 + '. ' + p.title),
								el(
									'span',
									{ className: 'wam-nl-choisis__actions' },
									el(
										Button,
										{
											size: 'small',
											icon: 'arrow-up-alt2',
											label: __('Monter', 'wam-newsletter'),
											disabled: 0 === index,
											onClick: function () {
												deplacer(index, -1);
											}
										}
									),
									el(
										Button,
										{
											size: 'small',
											icon: 'arrow-down-alt2',
											label: __('Descendre', 'wam-newsletter'),
											disabled: index === titres.length - 1,
											onClick: function () {
												deplacer(index, 1);
											}
										}
									),
									el(
										Button,
										{
											size: 'small',
											icon: 'no-alt',
											isDestructive: true,
											label: __('Retirer', 'wam-newsletter'),
											onClick: function () {
												basculer(p.id);
											}
										}
									)
								)
							);
						})
				  )
				: el('p', { className: 'components-base-control__help' }, __('Aucun contenu choisi pour le moment.', 'wam-newsletter')),
			el(TextControl, {
				label: __('Rechercher un contenu', 'wam-newsletter'),
				value: terme,
				onChange: setTerme
			}),
			el(
				'div',
				{ className: 'wam-nl-resultats' },
				(liste || []).map(function (p) {
					var actif = (a.postIds || []).indexOf(p.id) >= 0;
					return el(
						Button,
						{
							key: p.id,
							variant: actif ? 'primary' : 'secondary',
							size: 'small',
							onClick: function () {
								basculer(p.id);
							}
						},
						libelleContenu(p, true)
					);
				})
			)
		);
	}

	/** « Titre · sous-titre », avec la date en plus pour la liste de recherche. */
	function libelleContenu(p, avecDate) {
		var texte = p.title;
		if (p.subtitle) {
			texte += ' · ' + p.subtitle;
		}
		if (avecDate && p.date) {
			texte += ' - ' + p.date;
		}
		return texte;
	}

	function TermesFiltre(props) {
		var a = props.attributes;
		var setAttributes = props.setAttributes;
		var taxonomie = taxonomiePour(a.postType);
		var etat = useState([]);
		var termes = etat[0];
		var setTermes = etat[1];

		useEffect(
			function () {
				if (!taxonomie) {
					setTermes([]);
					return;
				}
				apiFetch({ path: ns + '/terms?taxonomy=' + encodeURIComponent(taxonomie) })
					.then(setTermes)
					.catch(function () {
						setTermes([]);
					});
			},
			[taxonomie]
		);

		if (!taxonomie || !termes.length) {
			return null;
		}

		var options = [{ label: __('Toutes les catégories', 'wam-newsletter'), value: 0 }].concat(
			termes.map(function (t) {
				return { label: t.name, value: t.id };
			})
		);

		return el(SelectControl, {
			label: __('Filtrer par catégorie', 'wam-newsletter'),
			value: a.term || 0,
			options: options,
			onChange: function (v) {
				setAttributes({ term: parseInt(v, 10) || 0, taxonomy: parseInt(v, 10) ? taxonomie : '' });
			}
		});
	}

	registerBlockType('wam-nl/posts', {
		edit: function (props) {
			var a = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps ? useBlockProps() : {};
			var manuel = 'manual' === a.mode;

			var etatConversion = useState(false);
			var enConversion = etatConversion[0];
			var setEnConversion = etatConversion[1];

			/**
			 * Détache ce bloc : les contenus actuels deviennent de vrais blocs
			 * Gutenberg, modifiables un par un, mais qui arrêtent de se mettre à
			 * jour automatiquement. Irréversible pour ce bloc précis (on peut
			 * toujours annuler avec Ctrl+Z juste après), d'où la confirmation.
			 */
			function convertirEnBlocs() {
				if (
					!window.confirm(
						__(
							'Cette section deviendra des blocs normaux, modifiables un par un (texte, image, ordre). Elle ne se mettra plus à jour automatiquement par la suite. Continuer ?',
							'wam-newsletter'
						)
					)
				) {
					return;
				}

				setEnConversion(true);

				apiFetch({
					path: ns + '/posts-resolve',
					method: 'POST',
					data: {
						postType: a.postType,
						mode: a.mode,
						count: a.count,
						order: a.order,
						taxonomy: a.taxonomy,
						term: a.term,
						postIds: a.postIds
					}
				})
					.then(function (reponse) {
						var items = reponse.items || [];
						if (!items.length) {
							window.alert(__('Aucun contenu à convertir pour le moment.', 'wam-newsletter'));
							setEnConversion(false);
							return;
						}
						var nouveauxBlocs = construireBlocsDepuisItems(items, a, reponse.defaultButtonText || '');
						wp.data.dispatch('core/block-editor').replaceBlocks(props.clientId, nouveauxBlocs);
					})
					.catch(function () {
						window.alert(__('La conversion a échoué. Réessayez.', 'wam-newsletter'));
						setEnConversion(false);
					});
			}

			return el(
				'div',
				blockProps,
				BlockControls
					? el(
							BlockControls,
							null,
							el(
								ToolbarGroup,
								null,
								el(ToolbarButton, {
									icon: 'edit',
									label: __('Convertir en blocs modifiables', 'wam-newsletter'),
									isBusy: enConversion,
									onClick: convertirEnBlocs
								})
							)
					  )
					: null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __('Quels contenus ?', 'wam-newsletter'), initialOpen: true },
						el(
							'p',
							{ className: 'components-base-control__help' },
							__(
								'Besoin de modifier le texte, l’image ou l’ordre d’un contenu précis ? Utilisez « Convertir en blocs modifiables » dans la barre d’outils ci-dessus (icône ⛓️‍💥).',
								'wam-newsletter'
							)
						),
						el(SelectControl, {
							label: __('Type de contenu', 'wam-newsletter'),
							value: a.postType,
							options: TYPES,
							onChange: function (v) {
								setAttributes({
									postType: v,
									term: 0,
									taxonomy: '',
									postIds: [],
									// Les cours n'ont pas de date : « à venir » n'y
									// voudrait rien dire, on repasse en récents.
									order: 'cours' === v || 'page' === v ? 'recent' : a.order
								});
							}
						}),
						el(
							ButtonGroup,
							{ className: 'wam-nl-mode' },
							el(
								Button,
								{
									variant: manuel ? 'secondary' : 'primary',
									onClick: function () {
										setAttributes({ mode: 'auto' });
									}
								},
								__('Automatique', 'wam-newsletter')
							),
							el(
								Button,
								{
									variant: manuel ? 'primary' : 'secondary',
									onClick: function () {
										setAttributes({ mode: 'manual' });
									}
								},
								__('Je choisis', 'wam-newsletter')
							)
						),
						el(
							'p',
							{ className: 'components-base-control__help' },
							manuel
								? __('Vous choisissez les contenus et leur ordre.', 'wam-newsletter')
								: __('Les contenus sont repris automatiquement à chaque envoi.', 'wam-newsletter')
						),
						manuel
							? el(SelecteurManuel, { attributes: a, setAttributes: setAttributes })
							: el(
									Fragment,
									null,
									el(RangeControl, {
										label: __('Combien de contenus', 'wam-newsletter'),
										value: a.count,
										min: 1,
										max: 12,
										onChange: function (v) {
											setAttributes({ count: v || 1 });
										}
									}),
									el(SelectControl, {
										label: __('Lesquels', 'wam-newsletter'),
										value: a.order,
										options: [
											{ label: __('Les plus récents', 'wam-newsletter'), value: 'recent' },
											{ label: __('Les prochains (par date)', 'wam-newsletter'), value: 'upcoming' }
										],
										help:
											'stages' === a.postType
												? __('« Les prochains » exclut les stages déjà passés.', 'wam-newsletter')
												: __('« Les prochains » n’a d’effet que sur les contenus qui ont une date.', 'wam-newsletter'),
										onChange: function (v) {
											setAttributes({ order: v });
										}
									}),
									el(TermesFiltre, { attributes: a, setAttributes: setAttributes })
							  )
					),
					el(
						PanelBody,
						{ title: __('Ce qu’on affiche', 'wam-newsletter'), initialOpen: false },
						el(ToggleControl, {
							label: __('L’image', 'wam-newsletter'),
							checked: !!a.showImage,
							onChange: function (v) {
								setAttributes({ showImage: v });
							}
						}),
						el(ToggleControl, {
							label: __('Le résumé', 'wam-newsletter'),
							checked: !!a.showExcerpt,
							onChange: function (v) {
								setAttributes({ showExcerpt: v });
							}
						}),
						el(ToggleControl, {
							label: __('La date', 'wam-newsletter'),
							checked: !!a.showDate,
							onChange: function (v) {
								setAttributes({ showDate: v });
							}
						}),
						el(ToggleControl, {
							label: __('Le bouton', 'wam-newsletter'),
							checked: !!a.showButton,
							onChange: function (v) {
								setAttributes({ showButton: v });
							}
						}),
						el(ToggleControl, {
							label: __('Alterner image à gauche / à droite', 'wam-newsletter'),
							checked: !!a.alternate,
							help: __('Sur téléphone, l’image reste toujours au-dessus du texte.', 'wam-newsletter'),
							onChange: function (v) {
								setAttributes({ alternate: v });
							}
						})
					),
					a.showButton
						? el(
								PanelBody,
								{ title: __('Bouton de chaque contenu', 'wam-newsletter'), initialOpen: false },
								el(TextControl, {
									label: __('Texte du bouton', 'wam-newsletter'),
									placeholder: __('Automatique selon le type', 'wam-newsletter'),
									value: a.buttonText || '',
									onChange: function (v) {
										setAttributes({ buttonText: v });
									}
								}),
								el(SelectControl, {
									label: __('Style', 'wam-newsletter'),
									value: a.buttonVariant || 'plein',
									options: [
										{ label: __('Plein', 'wam-newsletter'), value: 'plein' },
										{ label: __('Contour', 'wam-newsletter'), value: 'contour' }
									],
									onChange: function (v) {
										setAttributes({ buttonVariant: v });
									}
								}),
								el(SelectControl, {
									label: __('Couleur', 'wam-newsletter'),
									value: a.buttonColor || couleurs.accent || '#FBD150',
									options: palette(),
									onChange: function (v) {
										setAttributes({ buttonColor: v });
									}
								})
						  )
						: null
				),
				// Pas de bandeau d'étiquette : il n'apportait rien et gênait la lecture.
				apercuSansEtiquette('wam-nl/posts', a)
			);
		},
		save: function () {
			return null;
		}
	});
})();
