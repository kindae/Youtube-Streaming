</main>

<footer class="site-footer">
    <div class="container footer-inner">
        <div class="footer-brand">
            <img src="<?= e($site['logo']) ?>" alt="" class="brand-logo">
            <div>
                <strong><?= e($site['nom']) ?></strong>
                <p><?= e($site['slogan']) ?></p>
            </div>
        </div>
        <p class="footer-copy">© <?= date('Y') ?> <?= e($site['nom']) ?> · Vidéos diffusées via YouTube</p>
    </div>
</footer>

<button type="button" class="back-to-top" aria-label="Revenir en haut">
    <svg viewBox="0 0 24 24"><path d="M7.4 15.4 12 10.8l4.6 4.6L18 14l-6-6-6 6z"/></svg>
</button>

<div class="toast" role="status" aria-live="polite"></div>

<script src="assets/js/app.js?v=2" defer></script>
</body>
</html>
