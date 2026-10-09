/**
 * Infobulle de la courbe « Au fil du temps ».
 *
 * Chaque créneau porte une zone de survol (plus large que la marque) avec ses
 * chiffres en attributs : on affiche un repère vertical et une bulle. Sans
 * script, la vue tableau sous la courbe donne les mêmes chiffres.
 */
(function () {
	'use strict';

	document.querySelectorAll('.wam-nl-chart__cadre').forEach(function (cadre) {
		var svg = cadre.querySelector('svg');
		var bulle = cadre.querySelector('.wam-nl-chart__bulle');
		var repere = cadre.querySelector('.wam-nl-chart__repere');
		if (!svg || !bulle || !repere) {
			return;
		}

		var vue = svg.viewBox.baseVal;

		function montrer(zone) {
			var x = parseFloat(zone.getAttribute('data-x'));
			repere.setAttribute('x1', x);
			repere.setAttribute('x2', x);
			repere.classList.add('is-visible');

			bulle.innerHTML =
				'<strong></strong>' +
				'<span><i style="background:#2a78d6"></i>Ouvertures : <b class="o"></b></span><br>' +
				'<span><i style="background:#eb6834"></i>Clics : <b class="c"></b></span>';
			bulle.querySelector('strong').textContent = zone.getAttribute('data-label');
			bulle.querySelector('.o').textContent = zone.getAttribute('data-open');
			bulle.querySelector('.c').textContent = zone.getAttribute('data-click');
			bulle.hidden = false;

			// Position en pixels écran, la bulle bascule à gauche près du bord droit.
			var echelle = svg.getBoundingClientRect().width / vue.width;
			var gauche = x * echelle + 12;
			if (gauche + bulle.offsetWidth > cadre.clientWidth) {
				gauche = x * echelle - bulle.offsetWidth - 12;
			}
			bulle.style.left = Math.max(0, gauche) + 'px';
		}

		function cacher() {
			bulle.hidden = true;
			repere.classList.remove('is-visible');
		}

		svg.querySelectorAll('.wam-nl-chart__zone').forEach(function (zone) {
			zone.addEventListener('mouseenter', function () {
				montrer(zone);
			});
		});
		svg.addEventListener('mouseleave', cacher);
	});
})();
