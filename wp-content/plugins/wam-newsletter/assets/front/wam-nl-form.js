/**
 * Soumission AJAX du formulaire d'inscription.
 *
 * Délégation sur le document plutôt qu'une écoute par formulaire : un encart
 * newsletter peut être injecté après coup (bloc, widget, contenu mis en cache
 * puis rafraîchi), et il doit fonctionner sans ré-attache.
 *
 * Sans JavaScript, le formulaire reste soumis normalement vers admin-ajax.php :
 * l'inscription aboutit, seule la réponse n'est plus affichée dans la page.
 *
 * ES5 volontaire, sans étape de build (convention du projet).
 */
(function () {
	'use strict';

	if (typeof window.wamNlForm === 'undefined') {
		return;
	}

	function reponse(form) {
		return form.querySelector('.wam-form-response');
	}

	function afficher(form, type, message) {
		var zone = reponse(form);
		if (!zone) {
			return;
		}
		zone.className = 'wam-form-response is-visible is-' + type;
		zone.textContent = message;
	}

	function bouton(form) {
		return form.querySelector('button[type="submit"]');
	}

	document.addEventListener('submit', function (evenement) {
		var form = evenement.target;
		if (!form || !form.classList || !form.classList.contains('wam-nl-form')) {
			return;
		}

		evenement.preventDefault();

		// Validation native d'abord : messages du navigateur, localisés et
		// accessibles, plutôt qu'un aller-retour serveur pour un champ vide.
		if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
			if (typeof form.reportValidity === 'function') {
				form.reportValidity();
			}
			return;
		}

		var envoi = bouton(form);
		if (envoi) {
			if (envoi.disabled) {
				return;
			}
			envoi.disabled = true;
			envoi.classList.add('is-loading');
		}

		var zone = reponse(form);
		if (zone) {
			zone.className = 'wam-form-response';
			zone.textContent = '';
		}

		var donnees = new FormData(form);

		fetch(window.wamNlForm.ajaxUrl, {
			method: 'POST',
			body: donnees,
			credentials: 'same-origin'
		})
			.then(function (r) {
				return r.json().catch(function () {
					return { success: false, data: {} };
				});
			})
			.then(function (charge) {
				var message = (charge && charge.data && charge.data.message) || '';

				if (charge && charge.success) {
					afficher(form, 'success', message || 'Merci, votre inscription est bien enregistrée.');
					form.reset();
					return;
				}

				afficher(form, 'error', message || 'L’inscription n’a pas abouti. Merci de réessayer.');
			})
			.catch(function () {
				afficher(form, 'error', 'Connexion interrompue. Merci de réessayer.');
			})
			.then(function () {
				if (envoi) {
					envoi.disabled = false;
					envoi.classList.remove('is-loading');
				}
			});
	});
})();
