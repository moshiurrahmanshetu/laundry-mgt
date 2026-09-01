<?php
/**
 * Common Footer Component
 * Project: Laundry Management System (laundry-mgt)
 */
?>
</main> <!-- end .content-body -->

    <!-- App Footer -->
    <footer class="app-footer text-center text-md-start d-flex flex-column flex-md-row justify-content-between align-items-center">
        <div>
            &copy; <?= date('Y') ?> <strong><?= e(APP_NAME) ?></strong>. All rights reserved.
        </div>
        <div class="mt-2 mt-md-0 d-flex align-items-center gap-2">
            <span class="text-muted small">Phase 01: Foundation &amp; Authentication</span>
            <span class="badge bg-secondary-subtle text-secondary border">v<?= e(APP_VERSION) ?></span>
        </div>
    </footer>

</div> <!-- end #main-wrapper -->

<!-- Mobile Offcanvas Backdrop Overlay -->
<div id="mobile-overlay"></div>

</div> <!-- end #app-layout -->

<!-- Bootstrap 5 Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<!-- Application Core JavaScript -->
<script src="<?= asset_url('js/app.js') ?>?v=<?= e(APP_VERSION) ?>"></script>

<?= $extraScripts ?? '' ?>
</body>
</html>
