/* =====================================================
   Congonet 24 + — Interactions
   ===================================================== */
(function () {
    'use strict';

    var root = document.documentElement;

    function $(sel, ctx) { return (ctx || document).querySelector(sel); }
    function $$(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }

    function stocker(cle, valeur) {
        try { localStorage.setItem(cle, valeur); } catch (e) {}
    }
    function lire(cle) {
        try { return localStorage.getItem(cle); } catch (e) { return null; }
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    var toastEl = $('.toast'), toastTimer;
    function toast(message) {
        if (!toastEl) return;
        toastEl.textContent = message;
        toastEl.classList.add('visible');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { toastEl.classList.remove('visible'); }, 2200);
    }

    /* ---------- Thème clair / sombre ---------- */
    var themeBtn = $('.theme-toggle');
    if (themeBtn) {
        themeBtn.addEventListener('click', function () {
            var actuel = root.getAttribute('data-theme') ||
                (window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
            var nouveau = actuel === 'light' ? 'dark' : 'light';
            root.setAttribute('data-theme', nouveau);
            stocker('theme', nouveau);
        });
    }

    /* ---------- Recherche + suggestions ---------- */
    var input = $('#search-input');
    var liste = $('#search-suggestions');
    var searchForm = $('.search');

    $$('.search-open').forEach(function (b) {
        b.addEventListener('click', function () {
            document.body.classList.add('search-active');
            input.focus();
        });
    });
    $$('.search-close').forEach(function (b) {
        b.addEventListener('click', function () {
            document.body.classList.remove('search-active');
            fermerSuggestions();
        });
    });

    function fermerSuggestions() {
        if (liste) { liste.hidden = true; liste.innerHTML = ''; }
        if (input) input.setAttribute('aria-expanded', 'false');
    }

    if (input && liste && window.fetch) {
        var timer, controleur, indexActif = -1;

        input.addEventListener('input', function () {
            clearTimeout(timer);
            var q = input.value.trim();
            if (q.length < 2) { fermerSuggestions(); return; }
            timer = setTimeout(function () { chercher(q); }, 220);
        });

        function chercher(q) {
            if (controleur && controleur.abort) controleur.abort();
            controleur = window.AbortController ? new AbortController() : null;
            fetch('api.php?action=suggestions&q=' + encodeURIComponent(q), controleur ? { signal: controleur.signal } : {})
                .then(function (r) { return r.json(); })
                .then(function (data) { afficher(data.resultats || [], q); })
                .catch(function () {});
        }

        function afficher(resultats, q) {
            indexActif = -1;
            var html = resultats.map(function (v) {
                return '<li role="option"><a href="' + escapeHtml(v.url) + '">' +
                    '<img src="' + escapeHtml(v.miniature) + '" alt="" loading="lazy">' +
                    '<div><div class="s-title">' + escapeHtml(v.titre) + '</div>' +
                    '<div class="s-meta">' + escapeHtml(v.vues) + (v.duree ? ' · ' + escapeHtml(v.duree) : '') + '</div></div>' +
                    '</a></li>';
            }).join('');
            html += '<li><a class="s-all" href="index.php?q=' + encodeURIComponent(q) + '">' +
                (resultats.length ? 'Voir tous les résultats pour « ' + escapeHtml(q) + ' »' : 'Rechercher « ' + escapeHtml(q) + ' »') +
                '</a></li>';
            liste.innerHTML = html;
            liste.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        }

        input.addEventListener('keydown', function (e) {
            var liens = $$('a', liste);
            if (liste.hidden || !liens.length) return;
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                indexActif = (indexActif + (e.key === 'ArrowDown' ? 1 : -1) + liens.length) % liens.length;
                liens.forEach(function (a, i) { a.classList.toggle('active', i === indexActif); });
                liens[indexActif].scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'Enter' && indexActif >= 0) {
                e.preventDefault();
                window.location.href = liens[indexActif].href;
            } else if (e.key === 'Escape') {
                fermerSuggestions();
            }
        });

        document.addEventListener('click', function (e) {
            if (searchForm && !searchForm.contains(e.target)) fermerSuggestions();
        });
    }

    // Raccourci clavier « / » pour rechercher
    document.addEventListener('keydown', function (e) {
        if (e.key === '/' && input && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName)) {
            e.preventDefault();
            document.body.classList.add('search-active');
            input.focus();
            input.select();
        }
    });

    /* ---------- Lecteur léger (vidéo à la une) ---------- */
    $$('.lite-player').forEach(function (el) {
        function lancer() {
            if (el.querySelector('iframe')) return;
            var iframe = document.createElement('iframe');
            iframe.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(el.dataset.id) +
                '?autoplay=1&rel=0&modestbranding=1&playsinline=1';
            iframe.title = 'Lecteur vidéo';
            iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
            iframe.allowFullscreen = true;
            el.appendChild(iframe);
            var btn = el.querySelector('.lite-play');
            if (btn) btn.remove();
        }
        el.addEventListener('click', lancer);
    });

    /* ---------- Rangée défilante ---------- */
    $$('.row-section').forEach(function (section) {
        var row = $('.video-row', section);
        if (!row) return;
        function defiler(sens) { row.scrollBy({ left: sens * row.clientWidth * 0.9, behavior: 'smooth' }); }
        var prev = $('.row-prev', section), next = $('.row-next', section);
        if (prev) prev.addEventListener('click', function () { defiler(-1); });
        if (next) next.addEventListener('click', function () { defiler(1); });
    });

    /* ---------- « Afficher plus » sans recharger la page ---------- */
    $$('.load-more').forEach(function (btn) {
        var grille = $(btn.dataset.grid);
        if (!grille || !window.fetch || !window.DOMParser) return;

        var observateur = null;

        function charger(e) {
            if (e) e.preventDefault();
            if (btn.classList.contains('loading') || !btn.getAttribute('href')) return;
            btn.classList.add('loading');

            var squelettes = [];
            for (var i = 0; i < 4; i++) {
                var s = document.createElement('article');
                s.className = 'video-card skeleton';
                s.innerHTML = '<div class="thumb"></div><div class="video-info"><div class="video-title"></div><div class="video-meta"></div></div>';
                grille.appendChild(s);
                squelettes.push(s);
            }

            fetch(btn.href, { headers: { 'X-Requested-With': 'fetch' } })
                .then(function (r) { return r.text(); })
                .then(function (html) {
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    squelettes.forEach(function (s) { s.remove(); });
                    $$('#' + grille.id + ' > .video-card', doc).forEach(function (carte) {
                        grille.appendChild(document.importNode(carte, true));
                    });
                    var suivant = $('.load-more', doc);
                    if (suivant) {
                        btn.href = suivant.getAttribute('href');
                        btn.classList.remove('loading');
                    } else {
                        btn.parentNode.remove();
                        if (observateur) observateur.disconnect();
                    }
                })
                .catch(function () {
                    squelettes.forEach(function (s) { s.remove(); });
                    btn.classList.remove('loading');
                    toast('Impossible de charger plus de vidéos.');
                });
        }

        btn.addEventListener('click', charger);

        // Chargement automatique au défilement
        if ('IntersectionObserver' in window) {
            observateur = new IntersectionObserver(function (entrees) {
                if (entrees[0].isIntersecting) charger();
            }, { rootMargin: '400px' });
            observateur.observe(btn);
        }
    });

    /* ---------- Bouton retour en haut ---------- */
    var haut = $('.back-to-top');
    if (haut) {
        window.addEventListener('scroll', function () {
            haut.classList.toggle('visible', window.scrollY > 800);
        }, { passive: true });
        haut.addEventListener('click', function () { window.scrollTo({ top: 0 }); });
    }

    /* =====================================================
       Page de lecture
       ===================================================== */
    var playerEl = $('#player');
    if (!playerEl) return;

    var ytPlayer = null;
    var prochaine = playerEl.dataset.next;
    var overlay = $('.upnext-overlay');
    var autoBtn = $('.autoplay-toggle');
    var autoplay = lire('autoplay') !== '0';
    var compteTimer = null;

    function majAutoplay() {
        if (autoBtn) autoBtn.setAttribute('aria-pressed', autoplay ? 'true' : 'false');
    }
    majAutoplay();

    if (autoBtn) {
        autoBtn.addEventListener('click', function () {
            autoplay = !autoplay;
            stocker('autoplay', autoplay ? '1' : '0');
            majAutoplay();
            toast(autoplay ? 'Lecture automatique activée' : 'Lecture automatique désactivée');
            if (!autoplay) annulerCompte();
        });
    }

    function annulerCompte() {
        clearInterval(compteTimer);
        if (overlay) overlay.hidden = true;
    }

    function lancerCompte() {
        if (!prochaine || !overlay || !autoplay) return;
        var reste = 8;
        var compteur = $('.upnext-count', overlay);
        compteur.textContent = reste;
        overlay.hidden = false;
        clearInterval(compteTimer);
        compteTimer = setInterval(function () {
            reste--;
            compteur.textContent = reste;
            if (reste <= 0) {
                clearInterval(compteTimer);
                window.location.href = prochaine;
            }
        }, 1000);
    }

    var annuler = $('.upnext-cancel');
    if (annuler) annuler.addEventListener('click', annulerCompte);

    // API YouTube : détecte la fin de la vidéo et permet les horodatages
    var apiPrecedente = window.onYouTubeIframeAPIReady;
    window.onYouTubeIframeAPIReady = function () {
        if (typeof apiPrecedente === 'function') apiPrecedente();
        ytPlayer = new YT.Player('player-frame', {
            events: {
                onStateChange: function (e) {
                    if (e.data === YT.PlayerState.ENDED) lancerCompte();
                    else if (e.data === YT.PlayerState.PLAYING) annulerCompte();
                }
            }
        });
    };
    var scriptApi = document.createElement('script');
    scriptApi.src = 'https://www.youtube.com/iframe_api';
    scriptApi.async = true;
    document.head.appendChild(scriptApi);

    // Horodatages dans la description
    $$('.timestamp').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            var t = parseInt(a.dataset.t, 10) || 0;
            if (ytPlayer && ytPlayer.seekTo) {
                ytPlayer.seekTo(t, true);
                ytPlayer.playVideo();
            }
            playerEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    });

    // Description repliable
    var desc = $('.description');
    var toggle = $('.description-toggle');
    if (desc && toggle) {
        var texte = $('.description-text', desc);
        if (texte && texte.scrollHeight <= texte.clientHeight + 4) {
            toggle.remove();
            desc.classList.remove('collapsed');
        } else {
            toggle.addEventListener('click', function () {
                var replie = desc.classList.toggle('collapsed');
                toggle.textContent = replie ? 'Afficher plus' : 'Afficher moins';
            });
        }
    }

    // Partage
    var shareBtn = $('.share-btn');
    var sharePanel = $('.share-panel');
    if (shareBtn && sharePanel) {
        shareBtn.addEventListener('click', function () {
            var donnees = { title: shareBtn.dataset.title, url: shareBtn.dataset.url };
            if (navigator.share && window.matchMedia('(pointer: coarse)').matches) {
                navigator.share(donnees).catch(function () {});
                return;
            }
            sharePanel.hidden = !sharePanel.hidden;
        });
    }

    var copyBtn = $('.copy-link');
    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            var champ = copyBtn.previousElementSibling;
            function ok() { toast('Lien copié !'); }
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(champ.value).then(ok, function () {
                    champ.select(); document.execCommand('copy'); ok();
                });
            } else {
                champ.select(); document.execCommand('copy'); ok();
            }
        });
    }
})();
