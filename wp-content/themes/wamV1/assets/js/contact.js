document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('wam-contact-form');
    if (!form) return;

    const responseDiv = document.getElementById('wam-contact-response');
    const submitBtn = document.getElementById('wam-contact-submit');
    const submitText = submitBtn.querySelector('.btn__text');
    const submitLoader = submitBtn.querySelector('.btn__loader');

    form.addEventListener('submit', (e) => {
        e.preventDefault();

        // 1. Nettoyage des erreurs précédentes
        form.querySelectorAll('.wam-field-error').forEach(el => el.remove());
        form.querySelectorAll('[aria-invalid="true"]').forEach(el => el.removeAttribute('aria-invalid'));

        // 2. Validation Front-end
        let hasError = false;
        const requiredFields = ['first_name', 'last_name', 'email', 'subject', 'message'];
        
        requiredFields.forEach(name => {
            const field = form.elements[name];
            if (!field) return;

            let errorMsg = '';
            if (!field.value.trim()) {
                errorMsg = 'Ce champ est obligatoire.';
            } else if (name === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(field.value)) {
                errorMsg = 'L\'adresse e-mail n\'est pas valide.';
            }

            if (errorMsg) {
                if (!hasError) field.focus(); // Focus sur le premier champ en erreur
                hasError = true;
                field.setAttribute('aria-invalid', 'true');
                const errorSpan = document.createElement('span');
                errorSpan.className = 'wam-field-error';
                errorSpan.textContent = errorMsg;
                // on ajoute l'erreur juste après le champ
                field.parentElement.appendChild(errorSpan);
            }
        });

        if (hasError) {
            return; // on stoppe l'envoi si erreur
        }

        // 3. Activer l'état de chargement
        submitBtn.disabled = true;
        submitBtn.classList.add('is-loading');
        
        responseDiv.classList.remove('is-visible');
        responseDiv.className = 'wam-form-response'; // reset
        responseDiv.innerHTML = '';

        const formData = new FormData(form);
        const ajaxUrl = (typeof wamParams !== 'undefined' && wamParams.ajaxurl) ? wamParams.ajaxurl : form.action;

        fetch(ajaxUrl, {
            method: 'POST',
            body: formData,
        })
        .then(res => {
            if (!res.ok) {
                throw new Error('Erreur HTTP réseau : ' + res.status);
            }
            return res.text();
        })
        .then(text => {
            try {
                return JSON.parse(text);
            } catch(e) {
                console.error("Réponse du serveur non-JSON :", text);
                throw new Error("Le serveur a répondu mais dans un format invalide (voir console).");
            }
        })
        .then(res => {
            // Désactiver l'état de chargement
            submitBtn.disabled = false;
            submitBtn.classList.remove('is-loading');

            if (res.success) {
                form.reset();
                responseDiv.classList.add('is-success');
                responseDiv.innerHTML = `<p>${res.data.message}</p>`;
                responseDiv.classList.add('is-visible');
            } else {
                responseDiv.classList.add('is-error');
                const errMsg = res.data?.message || 'Une erreur est survenue.';
                responseDiv.innerHTML = `<p>${errMsg}</p>`;
                responseDiv.classList.add('is-visible');
            }
        })
        .catch(err => {
            submitBtn.disabled = false;
            submitBtn.classList.remove('is-loading');
            responseDiv.classList.add('is-error');
            responseDiv.innerHTML = `<p>${err.message || 'Erreur réseau inattendue.'}</p>`;
            responseDiv.classList.add('is-visible');
            console.error('Erreur Ajax Formulaire:', err);
        });
    });
});
