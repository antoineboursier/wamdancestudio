/**
 * Panneau d'envoi de l'éditeur de newsletter.
 *
 * Pensé pour des personnes qui débutent sous WordPress. Trois partis pris :
 *
 *  1. UNE checklist « prêt à envoyer », toujours visible, qui dit en français ce
 *     qui va et ce qui manque. Plutôt qu'un bouton qui refuse avec un message
 *     d'erreur, on montre l'état en permanence. Les règles viennent du serveur
 *     (route /checklist) : l'envoi applique exactement les mêmes, il n'y a pas
 *     deux vérités.
 *  2. LE nombre de destinataires affiché en direct à côté des listes. « 1 843
 *     personnes recevront cet e-mail » est l'information qui rassure (ou alerte)
 *     avant de cliquer.
 *  3. UN seul bouton d'action, explicite — « Envoyer maintenant » ou
 *     « Programmer l'envoi » — et non le « Publier » de WordPress, qui ne veut
 *     rien dire pour une newsletter.
 *
 * ES5 avec createElement, sans étape de build (convention du projet).
 */
(function () {
	'use strict';

	var reglages = window.wamNlEditor || {};
	if (!reglages.postType) {
		return;
	}

	var plugins = wp.plugins;
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;
	var apiFetch = wp.apiFetch;
	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;

	// Les composants d'extension ont migré de @wordpress/edit-post vers
	// @wordpress/editor : on prend ce qui existe, pour ne pas dépendre d'une
	// version précise de WordPress.
	var editorPkg = wp.editor || {};
	var editPostPkg = wp.editPost || {};
	var PluginSidebar = editorPkg.PluginSidebar || editPostPkg.PluginSidebar;
	var PluginSidebarMoreMenuItem = editorPkg.PluginSidebarMoreMenuItem || editPostPkg.PluginSidebarMoreMenuItem;
	var PluginDocumentSettingPanel = editorPkg.PluginDocumentSettingPanel || editPostPkg.PluginDocumentSettingPanel;

	var C = wp.components;
	var PanelBody = C.PanelBody;
	var TextControl = C.TextControl;
	var TextareaControl = C.TextareaControl;
	var CheckboxControl = C.CheckboxControl;
	var Button = C.Button;
	var Notice = C.Notice;
	var Spinner = C.Spinner;
	var Modal = C.Modal;
	var ButtonGroup = C.ButtonGroup;
	var ToggleControl = C.ToggleControl;

	var ns = reglages.restNamespace;
	var METAS = reglages.metaKeys;
	var SIDEBAR = 'wam-nl-envoi';

	// ------------------------------------------------------------------
	// Petits utilitaires
	// ------------------------------------------------------------------

	/** Lecture/écriture des métas de la newsletter courante. */
	function useNewsletter() {
		var donnees = useSelect(function (select) {
			var editeur = select('core/editor');
			return {
				postId: editeur.getCurrentPostId(),
				postType: editeur.getCurrentPostType(),
				meta: editeur.getEditedPostAttribute('meta') || {},
				content: editeur.getEditedPostContent(),
				saving: editeur.isSavingPost(),
				dirty: editeur.isEditedPostDirty()
			};
		}, []);

		var editPost = useDispatch('core/editor').editPost;

		function setMeta(cle, valeur) {
			var patch = {};
			patch[cle] = valeur;
			editPost({ meta: patch });
		}

		return {
			postId: donnees.postId,
			postType: donnees.postType,
			meta: donnees.meta,
			content: donnees.content,
			saving: donnees.saving,
			dirty: donnees.dirty,
			setMeta: setMeta
		};
	}

	function estNewsletter(postType) {
		return postType === reglages.postType;
	}

	// ------------------------------------------------------------------
	// Panneau « Objet et aperçu », juste sous le titre
	// ------------------------------------------------------------------

	function PanneauObjet() {
		var n = useNewsletter();
		if (!estNewsletter(n.postType) || !PluginDocumentSettingPanel) {
			return null;
		}

		var objet = n.meta[METAS.subject] || '';
		var apercu = n.meta[METAS.preheader] || '';

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'wam-nl-objet',
				title: __('Objet de l’e-mail', 'wam-newsletter'),
				className: 'wam-nl-panneau-objet'
			},
			el(TextControl, {
				label: __('Objet', 'wam-newsletter'),
				help: __('La ligne que les gens voient dans leur boîte de réception.', 'wam-newsletter'),
				value: objet,
				onChange: function (v) {
					n.setMeta(METAS.subject, v);
				}
			}),
			el(TextareaControl, {
				label: __('Texte d’aperçu', 'wam-newsletter'),
				help: __('Facultatif. S’affiche après l’objet, en gris, dans la boîte de réception.', 'wam-newsletter'),
				rows: 2,
				value: apercu,
				onChange: function (v) {
					n.setMeta(METAS.preheader, v);
				}
			}),
			el(
				'p',
				{ className: 'wam-nl-astuce' },
				__('Astuce : écrivez {prenom} dans l’objet ou le texte pour insérer le prénom de la personne.', 'wam-newsletter')
			)
		);
	}

	// ------------------------------------------------------------------
	// Aperçu desktop / mobile
	// ------------------------------------------------------------------

	function ModaleApercu(props) {
		var etat = useState('desktop');
		var appareil = etat[0];
		var setAppareil = etat[1];

		var largeur = 'mobile' === appareil ? reglages.widthMobile || 375 : reglages.widthDesktop || 660;

		return el(
			Modal,
			{
				title: __('Aperçu de la newsletter', 'wam-newsletter'),
				onRequestClose: props.onClose,
				className: 'wam-nl-modale-apercu',
				isFullScreen: true
			},
			el(
				'div',
				{ className: 'wam-nl-apercu__barre' },
				el(
					ButtonGroup,
					null,
					el(
						Button,
						{
							variant: 'desktop' === appareil ? 'primary' : 'secondary',
							onClick: function () {
								setAppareil('desktop');
							}
						},
						__('Ordinateur', 'wam-newsletter')
					),
					el(
						Button,
						{
							variant: 'mobile' === appareil ? 'primary' : 'secondary',
							onClick: function () {
								setAppareil('mobile');
							}
						},
						__('Téléphone', 'wam-newsletter')
					)
				),
				el('span', { className: 'wam-nl-apercu__largeur' }, largeur + ' px')
			),
			props.loading
				? el('div', { className: 'wam-nl-apercu__chargement' }, el(Spinner, null))
				: el(
						'div',
						{ className: 'wam-nl-apercu__cadre' },
						el('iframe', {
							title: __('Aperçu', 'wam-newsletter'),
							srcDoc: props.html,
							style: { width: largeur + 'px' },
							className: 'wam-nl-apercu__iframe'
						})
				  )
		);
	}

	// ------------------------------------------------------------------
	// Checklist
	// ------------------------------------------------------------------

	function Checklist(props) {
		var items = props.items || [];
		if (!items.length) {
			return el('div', { className: 'wam-nl-checklist__chargement' }, el(Spinner, null));
		}

		return el(
			'ul',
			{ className: 'wam-nl-checklist' },
			items.map(function (item) {
				var classe = 'wam-nl-checklist__item';
				if (item.ok) {
					classe += ' is-ok';
				} else if (item.optional) {
					classe += ' is-conseil';
				} else {
					classe += ' is-manquant';
				}

				return el(
					'li',
					{ key: item.key, className: classe },
					el(
						'span',
						{ className: 'wam-nl-checklist__marque', 'aria-hidden': 'true' },
						item.ok ? '✓' : item.optional ? '·' : '!'
					),
					el(
						'span',
						{ className: 'wam-nl-checklist__texte' },
						el('strong', null, item.label),
						el('span', { className: 'wam-nl-checklist__aide' }, item.hint)
					)
				);
			})
		);
	}

	// ------------------------------------------------------------------
	// Progression d'un envoi
	// ------------------------------------------------------------------

	function Progression(props) {
		var p = props.progress || {};
		var total = p.total || 0;
		var envoyes = p.sent || 0;
		var pourcent = total > 0 ? Math.round((envoyes / total) * 100) : 0;

		return el(
			'div',
			{ className: 'wam-nl-progression' },
			el(
				'p',
				{ className: 'wam-nl-progression__chiffres' },
				sprintf(
					/* translators: 1: envoyés, 2: total */
					__('%1$s envoyé(s) sur %2$s', 'wam-newsletter'),
					envoyes.toLocaleString('fr-FR'),
					total.toLocaleString('fr-FR')
				)
			),
			el(
				'div',
				{ className: 'wam-nl-progression__barre' },
				el('div', { className: 'wam-nl-progression__jauge', style: { width: pourcent + '%' } })
			),
			p.failed
				? el(
						'p',
						{ className: 'wam-nl-progression__echecs' },
						sprintf(
							/* translators: %s nombre d'échecs */
							_n_fallback(p.failed, '%s échec', '%s échecs'),
							p.failed.toLocaleString('fr-FR')
						)
				  )
				: null
		);
	}

	/** Pluriel simple : wp.i18n._n demande un domaine chargé côté JS. */
	function _n_fallback(nombre, singulier, pluriel) {
		return nombre > 1 ? pluriel : singulier;
	}

	// ------------------------------------------------------------------
	// Panneau latéral d'envoi
	// ------------------------------------------------------------------

	function PanneauEnvoi() {
		var n = useNewsletter();

		var listesEtat = useState([]);
		var listes = listesEtat[0];
		var setListes = listesEtat[1];

		var checkEtat = useState(null);
		var check = checkEtat[0];
		var setCheck = checkEtat[1];

		var messageEtat = useState(null);
		var message = messageEtat[0];
		var setMessage = messageEtat[1];

		var occupeEtat = useState('');
		var occupe = occupeEtat[0];
		var setOccupe = occupeEtat[1];

		var apercuEtat = useState(null);
		var apercu = apercuEtat[0];
		var setApercu = apercuEtat[1];

		var testEtat = useState(reglages.testRecipients || '');
		var destinatairesTest = testEtat[0];
		var setDestinatairesTest = testEtat[1];

		var planifEtat = useState(false);
		var planifier = planifEtat[0];
		var setPlanifier = planifEtat[1];

		var dateEtat = useState('');
		var dateEnvoi = dateEtat[0];
		var setDateEnvoi = dateEtat[1];

		var selection = (n.meta[METAS.listIds] || []).map(Number);

		// Listes, une fois.
		useEffect(function () {
			apiFetch({ path: ns + '/lists' })
				.then(setListes)
				.catch(function () {
					setListes([]);
				});
		}, []);

		/** Recalcule la checklist à partir de l'état COURANT de l'éditeur. */
		function rafraichir() {
			if (!n.postId) {
				return;
			}
			apiFetch({
				path: ns + '/checklist',
				method: 'POST',
				data: {
					postId: n.postId,
					subject: n.meta[METAS.subject] || '',
					preheader: n.meta[METAS.preheader] || '',
					listIds: selection,
					content: n.content || ''
				}
			})
				.then(setCheck)
				.catch(function () {
					setCheck(null);
				});
		}

		// La checklist suit l'objet, les listes et le contenu, sans attendre un
		// enregistrement : c'est ce qui la rend utile pendant la rédaction.
		useEffect(
			function () {
				var minuteur = window.setTimeout(rafraichir, 400);
				return function () {
					window.clearTimeout(minuteur);
				};
			},
			[n.postId, n.meta[METAS.subject], n.meta[METAS.preheader], selection.join(','), n.content]
		);

		// Pendant un envoi, on suit la progression.
		var statut = check && check.status ? check.status : 'draft';
		useEffect(
			function () {
				if ('sending' !== statut || !n.postId) {
					return undefined;
				}
				var minuteur = window.setInterval(function () {
					apiFetch({ path: ns + '/progress?postId=' + n.postId })
						.then(function (p) {
							setCheck(function (ancien) {
								if (!ancien) {
									return ancien;
								}
								var copie = Object.assign({}, ancien);
								copie.progress = p;
								copie.status = p.status;
								return copie;
							});
						})
						.catch(function () {});
				}, 10000);
				return function () {
					window.clearInterval(minuteur);
				};
			},
			[statut, n.postId]
		);

		function basculerListe(id, coche) {
			var ids = selection.slice();
			var i = ids.indexOf(id);
			if (coche && i < 0) {
				ids.push(id);
			}
			if (!coche && i >= 0) {
				ids.splice(i, 1);
			}
			n.setMeta(METAS.listIds, ids);
		}

		function ouvrirApercu() {
			setApercu({ loading: true, html: '' });
			apiFetch({
				path: ns + '/preview',
				method: 'POST',
				data: {
					postId: n.postId,
					content: n.content || '',
					subject: n.meta[METAS.subject] || '',
					preheader: n.meta[METAS.preheader] || ''
				}
			})
				.then(function (r) {
					setApercu({ loading: false, html: r.html || '' });
				})
				.catch(function () {
					setApercu(null);
					setMessage({ type: 'error', texte: __('L’aperçu n’a pas pu être généré.', 'wam-newsletter') });
				});
		}

		/**
		 * Les actions d'envoi portent sur ce qui est ENREGISTRÉ : on sauvegarde
		 * donc d'abord. Sans ça, on enverrait la version précédente tout en
		 * voyant la nouvelle à l'écran.
		 */
		function enregistrerPuis(action) {
			setOccupe(action.nom);
			setMessage(null);

			var suite = function () {
				apiFetch({ path: ns + action.chemin, method: 'POST', data: action.donnees() })
					.then(function (r) {
						setMessage({ type: r.ok === false ? 'error' : 'success', texte: r.message || '' });
						rafraichir();
					})
					.catch(function (e) {
						setMessage({
							type: 'error',
							texte: (e && e.message) || __('L’opération a échoué.', 'wam-newsletter')
						});
					})
					.then(function () {
						setOccupe('');
					});
			};

			if (n.dirty) {
				wp.data
					.dispatch('core/editor')
					.savePost()
					.then(suite)
					.catch(function () {
						setOccupe('');
						setMessage({ type: 'error', texte: __('L’enregistrement a échoué.', 'wam-newsletter') });
					});
				return;
			}
			suite();
		}

		function envoyerTest() {
			enregistrerPuis({
				nom: 'test',
				chemin: '/send-test',
				donnees: function () {
					return { postId: n.postId, recipients: destinatairesTest };
				}
			});
		}

		function envoyer() {
			var quand = planifier && dateEnvoi ? dateEnvoi.replace('T', ' ') + ':00' : '';
			var libelle = planifier
				? __('Programmer cet envoi ?', 'wam-newsletter')
				: sprintf(
						/* translators: %s nombre de destinataires */
						__('Envoyer maintenant à %s personne(s) ? Cette action est irréversible.', 'wam-newsletter'),
						(check && check.recipients ? check.recipients : 0).toLocaleString('fr-FR')
				  );

			if (!window.confirm(libelle)) {
				return;
			}

			enregistrerPuis({
				nom: 'envoi',
				chemin: '/send',
				donnees: function () {
					return { postId: n.postId, scheduleAt: quand };
				}
			});
		}

		function reprendre() {
			enregistrerPuis({
				nom: 'reprise',
				chemin: '/resume',
				donnees: function () {
					return { postId: n.postId };
				}
			});
		}

		function arreter() {
			if (!window.confirm(__('Arrêter cet envoi ? Les messages déjà partis ne peuvent pas être rappelés.', 'wam-newsletter'))) {
				return;
			}
			enregistrerPuis({
				nom: 'arret',
				chemin: '/cancel',
				donnees: function () {
					return { postId: n.postId };
				}
			});
		}

		if (!estNewsletter(n.postType) || !PluginSidebar) {
			return null;
		}

		var pret = check && check.ready;
		var progression = check && check.progress ? check.progress : {};
		var enCours = 'sending' === statut;
		var enPause = 'paused' === statut;
		var envoyee = 'sent' === statut;
		var programmee = 'scheduled' === statut;

		return el(
			Fragment,
			null,
			PluginSidebarMoreMenuItem
				? el(
						PluginSidebarMoreMenuItem,
						{ target: SIDEBAR, icon: 'email-alt' },
						__('Envoi de la newsletter', 'wam-newsletter')
				  )
				: null,
			el(
				PluginSidebar,
				{
					name: SIDEBAR,
					title: __('Envoi', 'wam-newsletter'),
					icon: 'email-alt',
					className: 'wam-nl-sidebar'
				},

				message
					? el(
							Notice,
							{
								status: message.type,
								isDismissible: true,
								onRemove: function () {
									setMessage(null);
								}
							},
							message.texte
					  )
					: null,

				envoyee
					? el(
							PanelBody,
							{ title: __('Newsletter envoyée', 'wam-newsletter'), initialOpen: true },
							el(Progression, { progress: progression }),
							el(
								'p',
								{ className: 'wam-nl-astuce' },
								__('Pour en refaire une semblable, utilisez « Dupliquer » depuis la liste des newsletters.', 'wam-newsletter')
							)
					  )
					: null,

				enCours || programmee
					? el(
							PanelBody,
							{ title: programmee ? __('Envoi programmé', 'wam-newsletter') : __('Envoi en cours', 'wam-newsletter'), initialOpen: true },
							el(Progression, { progress: progression }),
							el(
								Button,
								{
									variant: 'secondary',
									isDestructive: true,
									isBusy: 'arret' === occupe,
									onClick: arreter
								},
								__('Arrêter l’envoi', 'wam-newsletter')
							)
					  )
					: null,

				enPause
					? el(
							PanelBody,
							{ title: __('Envoi en pause', 'wam-newsletter'), initialOpen: true },
							el(
								Notice,
								{ status: 'warning', isDismissible: false },
								progression.pause || __('Envoi interrompu.', 'wam-newsletter')
							),
							el(Progression, { progress: progression }),
							el(
								Button,
								{
									variant: 'primary',
									isBusy: 'reprise' === occupe,
									onClick: reprendre
								},
								__('Reprendre l’envoi', 'wam-newsletter')
							)
					  )
					: null,

				!enCours && !envoyee && !programmee && !enPause
					? el(
							Fragment,
							null,
							el(
								PanelBody,
								{ title: __('Prêt à envoyer ?', 'wam-newsletter'), initialOpen: true },
								el(Checklist, { items: check ? check.items : [] })
							),

							el(
								PanelBody,
								{ title: __('Qui va recevoir cet e-mail', 'wam-newsletter'), initialOpen: true },
								listes.length
									? listes.map(function (liste) {
											return el(CheckboxControl, {
												key: liste.id,
												label: liste.name + ' (' + liste.count.toLocaleString('fr-FR') + ')',
												checked: selection.indexOf(liste.id) >= 0,
												onChange: function (coche) {
													basculerListe(liste.id, coche);
												}
											});
									  })
									: el(
											'p',
											{ className: 'components-base-control__help' },
											el(
												'a',
												{ href: reglages.listsScreenUrl },
												__('Aucune liste pour l’instant : en créer une', 'wam-newsletter')
											)
									  ),
								check
									? el(
											'p',
											{ className: 'wam-nl-destinataires' },
											sprintf(
												/* translators: %s nombre de personnes */
												__('%s personne(s) recevront cet e-mail.', 'wam-newsletter'),
												(check.recipients || 0).toLocaleString('fr-FR')
											)
									  )
									: null
							),

							el(
								PanelBody,
								{ title: __('Vérifier avant d’envoyer', 'wam-newsletter'), initialOpen: true },
								el(
									Button,
									{ variant: 'secondary', onClick: ouvrirApercu, style: { marginBottom: '8px' } },
									__('Voir l’aperçu', 'wam-newsletter')
								),
								el(TextControl, {
									label: __('Envoyer un test à', 'wam-newsletter'),
									help: __('Plusieurs adresses séparées par des virgules.', 'wam-newsletter'),
									value: destinatairesTest,
									onChange: setDestinatairesTest
								}),
								el(
									Button,
									{
										variant: 'secondary',
										isBusy: 'test' === occupe,
										disabled: !(check && check.items && check.items[0] && check.items[0].ok),
										onClick: envoyerTest
									},
									__('Envoyer un test', 'wam-newsletter')
								)
							),

							el(
								PanelBody,
								{ title: __('Envoyer', 'wam-newsletter'), initialOpen: true },
								el(ToggleControl, {
									label: __('Programmer plus tard', 'wam-newsletter'),
									checked: planifier,
									onChange: setPlanifier
								}),
								planifier
									? el(TextControl, {
											label: __('Date et heure', 'wam-newsletter'),
											type: 'datetime-local',
											value: dateEnvoi,
											onChange: setDateEnvoi
									  })
									: null,
								el(
									Button,
									{
										variant: 'primary',
										className: 'wam-nl-bouton-envoi',
										isBusy: 'envoi' === occupe,
										disabled: !pret || (planifier && !dateEnvoi),
										onClick: envoyer
									},
									planifier ? __('Programmer l’envoi', 'wam-newsletter') : __('Envoyer maintenant', 'wam-newsletter')
								),
								!pret
									? el(
											'p',
											{ className: 'wam-nl-astuce' },
											__('Il reste un point à régler dans la liste ci-dessus.', 'wam-newsletter')
									  )
									: null
							)
					  )
					: null
			),

			apercu
				? el(ModaleApercu, {
						html: apercu.html,
						loading: apercu.loading,
						onClose: function () {
							setApercu(null);
						}
				  })
				: null
		);
	}

	// ------------------------------------------------------------------
	// Mise en route
	// ------------------------------------------------------------------

	plugins.registerPlugin('wam-nl-editor', {
		render: function () {
			return el(Fragment, null, el(PanneauObjet, null), el(PanneauEnvoi, null));
		}
	});

	/**
	 * Plein écran et panneau d'envoi ouverts à la première visite.
	 *
	 * Une seule fois, mémorisé dans le navigateur : si la personne préfère
	 * ensuite revenir à l'affichage normal ou fermer le panneau, son choix est
	 * respecté — on ne le lui réimpose pas à chaque ouverture.
	 */
	wp.domReady(function () {
		var editeur = wp.data.select('core/editor');
		if (!editeur || !estNewsletter(editeur.getCurrentPostType())) {
			return;
		}

		try {
			if (!window.localStorage.getItem('wamNlPleinEcran')) {
				window.localStorage.setItem('wamNlPleinEcran', '1');
				var editPostSelect = wp.data.select('core/edit-post');
				if (editPostSelect && !editPostSelect.isFeatureActive('fullscreenMode')) {
					wp.data.dispatch('core/edit-post').toggleFeature('fullscreenMode');
				}
			}
		} catch (e) {
			// localStorage indisponible (navigation privée stricte) : sans
			// incidence, on laisse l'affichage par défaut.
		}

		try {
			var cle = 'wamNlPanneau-' + editeur.getCurrentPostId();
			if (!window.sessionStorage.getItem(cle)) {
				window.sessionStorage.setItem(cle, '1');
				var dispatcher = wp.data.dispatch('core/edit-post');
				if (dispatcher && dispatcher.openGeneralSidebar) {
					dispatcher.openGeneralSidebar('wam-nl-editor/' + SIDEBAR);
				}
			}
		} catch (e) {
			// idem
		}
	});
})();
