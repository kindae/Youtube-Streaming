/* =====================================================
   Studio Congonet 24 + — Interactions de l'administration
   ===================================================== */
(function () {
    'use strict';

    function $(sel, ctx) { return (ctx || document).querySelector(sel); }
    function $$(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }

    /* ---------- Thème ---------- */
    $$('.theme-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var root = document.documentElement;
            var actuel = root.getAttribute('data-theme') ||
                (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            var nouveau = actuel === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-theme', nouveau);
            try { localStorage.setItem('theme', nouveau); } catch (e) {}
        });
    });

    /* ---------- Messages ---------- */
    $$('.toasts .alert').forEach(function (alerte) {
        var fermer = function () { alerte.remove(); };
        var bouton = $('.alert-close', alerte);
        if (bouton) bouton.addEventListener('click', fermer);
        if (alerte.classList.contains('alert-success')) setTimeout(fermer, 6000);
    });

    /* ---------- Confirmation avant les actions sensibles ---------- */
    var modal = $('#confirm-modal');
    document.addEventListener('click', function (e) {
        var bouton = e.target.closest('[data-confirm]');
        if (!bouton || bouton.dataset.confirmed) return;
        e.preventDefault();

        var message = bouton.dataset.confirm;
        var valider = function () {
            bouton.dataset.confirmed = '1';
            var form = bouton.form || bouton.closest('form');
            if (form && form.requestSubmit) form.requestSubmit(bouton);
            else bouton.click();
        };

        if (!modal || !modal.showModal) {
            if (window.confirm(message)) valider();
            return;
        }
        $('.modal-text', modal).textContent = message;
        $('.modal-ok', modal).textContent = bouton.textContent.trim().length < 30 ? bouton.textContent.trim() : 'Confirmer';
        modal.returnValue = '';
        modal.showModal();
        modal.addEventListener('close', function surFermeture() {
            modal.removeEventListener('close', surFermeture);
            if (modal.returnValue === 'ok') valider();
        });
    });

    /* ---------- Indicateur de chargement sur les formulaires ---------- */
    document.addEventListener('submit', function (e) {
        var form = e.target;
        var bouton = e.submitter || $('button[type="submit"], button:not([type])', form);
        if (!bouton || form.method.toLowerCase() !== 'post') return;
        // Le bouton cliqué doit garder sa valeur (name/value) dans l'envoi
        if (bouton.name) {
            var cache = document.createElement('input');
            cache.type = 'hidden';
            cache.name = bouton.name;
            cache.value = bouton.value;
            form.appendChild(cache);
        }
        setTimeout(function () {
            bouton.classList.add('loading');
            bouton.disabled = true;
            if (form.dataset.loading && !bouton.classList.contains('icon-btn')) bouton.lastChild.textContent = ' ' + form.dataset.loading;
        }, 0);
    });

    /* ---------- Filtres : envoi automatique ---------- */
    $$('form[data-autosubmit]').forEach(function (form) {
        $$('select', form).forEach(function (s) {
            s.addEventListener('change', function () { form.submit(); });
        });
        var recherche = $('input[type="search"]', form);
        if (recherche) {
            recherche.addEventListener('search', function () { if (recherche.value === '') form.submit(); });
        }
    });

    /* ---------- Sélection multiple ---------- */
    var bulkForm = $('#bulk-form');
    if (bulkForm) {
        var tout = $('.check-all', bulkForm);
        var cases = $$('input[name="ids[]"]', bulkForm);
        var barre = $('.bulk-bar', bulkForm);
        var compteur = $('.bulk-count', bulkForm);

        var maj = function () {
            var n = cases.filter(function (c) { return c.checked; }).length;
            cases.forEach(function (c) { c.closest('.table-row').classList.toggle('selected', c.checked); });
            compteur.textContent = n;
            barre.hidden = n === 0;
            if (tout) {
                tout.checked = n > 0 && n === cases.length;
                tout.indeterminate = n > 0 && n < cases.length;
            }
        };
        cases.forEach(function (c) { c.addEventListener('change', maj); });
        if (tout) tout.addEventListener('change', function () {
            cases.forEach(function (c) { c.checked = tout.checked; });
            maj();
        });

        // Sélection d'une plage avec Maj + clic
        var dernier = null;
        cases.forEach(function (c, i) {
            c.addEventListener('click', function (e) {
                if (e.shiftKey && dernier !== null) {
                    var debut = Math.min(dernier, i), fin = Math.max(dernier, i);
                    for (var j = debut; j <= fin; j++) cases[j].checked = c.checked;
                    maj();
                }
                dernier = i;
            });
        });
        maj();
    }

    /* ---------- Compteur de caractères ---------- */
    $$('.counter[data-for]').forEach(function (compteur) {
        var champ = document.getElementById(compteur.dataset.for);
        if (!champ) return;
        var maj = function () { compteur.textContent = champ.value.length + ' / ' + (champ.maxLength > 0 ? champ.maxLength : '∞'); };
        champ.addEventListener('input', maj);
        maj();
    });

    /* ---------- Aperçu vidéo ---------- */
    $$('.preview.lite').forEach(function (el) {
        el.addEventListener('click', function () {
            if ($('iframe', el)) return;
            var iframe = document.createElement('iframe');
            iframe.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(el.dataset.id) + '?autoplay=1&rel=0';
            iframe.allow = 'autoplay; encrypted-media; picture-in-picture';
            iframe.allowFullscreen = true;
            el.appendChild(iframe);
            var play = $('.preview-play', el);
            if (play) play.remove();
        });
    });

    /* ---------- Aperçu des liens collés (page Ajouter) ---------- */
    var zoneLiens = $('textarea[name="liens"]');
    var apercu = $('.link-preview');
    if (zoneLiens && apercu) {
        var extraire = function (texte) {
            texte = texte.trim();
            if (/^[A-Za-z0-9_-]{11}$/.test(texte)) return texte;
            var m = texte.match(/(?:youtu\.be\/|[?&]v=|\/(?:shorts|embed|live|v)\/)([A-Za-z0-9_-]{11})/);
            return m ? m[1] : null;
        };
        var majApercu = function () {
            var ids = [];
            zoneLiens.value.split(/[\s,]+/).forEach(function (m) {
                var id = extraire(m);
                if (id && ids.indexOf(id) === -1) ids.push(id);
            });
            apercu.innerHTML = ids.map(function (id) {
                return '<div><img src="https://i.ytimg.com/vi/' + id + '/mqdefault.jpg" alt=""><small>' + id + '</small></div>';
            }).join('');
            apercu.hidden = ids.length === 0;
        };
        zoneLiens.addEventListener('input', majApercu);
        majApercu();
    }

    /* ---------- Copier ---------- */
    $$('.copy-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var champ = btn.previousElementSibling;
            champ.select();
            var fini = function () {
                var texte = btn.textContent;
                btn.textContent = 'Copié ✓';
                setTimeout(function () { btn.textContent = texte; }, 1500);
            };
            if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(champ.value).then(fini);
            else { document.execCommand('copy'); fini(); }
        });
    });
})();
