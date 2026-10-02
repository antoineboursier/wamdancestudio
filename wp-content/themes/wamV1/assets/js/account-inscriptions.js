/**
 * Mon compte → « Mes commandes »
 *
 * Les informations d'une inscription sont affichées en lecture ; le bouton
 * icône en haut à droite de la fiche bascule en édition (et revient en
 * arrière en annulant la saisie en cours). Le bouton « Enregistrer » ne
 * s'affiche qu'une fois un champ réellement modifié.
 *
 * Chaque champ porte sa valeur en base dans data-initial (et non
 * defaultValue) : après une erreur de validation, la saisie réaffichée
 * diffère de la base, la fiche s'ouvre donc d'emblée en édition.
 *
 * Progressive enhancement : le verrouillage et le bouton de bascule sont
 * posés ici. Sans JS, les champs restent éditables et « Enregistrer »
 * visible — le formulaire fonctionne à l'identique.
 */
(function () {
    document.querySelectorAll('.wam-fiche').forEach(function (fiche) {
        var form = fiche.querySelector('.wam-inscription__form');
        var toggle = fiche.querySelector('.wam-fiche__toggle');
        var actions = form ? form.querySelector('.wam-inscription__actions') : null;
        if (!form || !toggle || !actions) {
            return;
        }

        // Les champs désactivés côté serveur (date de naissance) ne sont jamais
        // déverrouillés : ils restent en lecture quoi qu'il arrive.
        var champs = form.querySelectorAll('input[data-initial]:not([disabled])');
        if (!champs.length) {
            return;
        }

        function modifie() {
            return Array.prototype.some.call(champs, function (champ) {
                return champ.value !== champ.getAttribute('data-initial');
            });
        }

        function syncActions() {
            actions.hidden = !modifie();
        }

        var label = toggle.querySelector('.wam-fiche__toggle-label');

        function basculer(editer) {
            fiche.classList.toggle('is-editing', editer);
            toggle.setAttribute('aria-expanded', String(editer));
            toggle.setAttribute('aria-label', editer ? 'Annuler la modification' : 'Modifier les informations');
            if (label) {
                // Visible en mobile uniquement, où les champs sont repliés.
                label.textContent = editer ? 'Annuler' : 'Éditer mes informations';
            }
            Array.prototype.forEach.call(champs, function (champ) {
                champ.readOnly = !editer;
            });
            syncActions();
        }

        toggle.addEventListener('click', function () {
            var editer = toggle.getAttribute('aria-expanded') !== 'true';
            if (!editer) {
                // Annuler : on restaure les valeurs en base avant de reverrouiller.
                Array.prototype.forEach.call(champs, function (champ) {
                    champ.value = champ.getAttribute('data-initial');
                });
            }
            basculer(editer);
            if (editer) {
                champs[0].focus();
            }
        });

        form.addEventListener('input', syncActions);

        toggle.hidden = false;
        // Marque la fiche comme pilotée par le JS : c'est seulement à cette
        // condition que le CSS replie les champs en mobile, sinon ils y
        // deviendraient inaccessibles quand le script ne s'exécute pas.
        fiche.classList.add('is-enhanced');
        // Une saisie déjà différente de la base = retour d'erreur de validation :
        // la fiche s'ouvre en édition pour que la correction reste accessible.
        basculer(modifie());
    });
})();
