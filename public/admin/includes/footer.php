<?php
// admin/includes/footer.php
?>
    <footer class="admin-footer mt-auto">
        <p class="mb-0">
            &copy; <?= date('Y') ?> <strong class="notranslate">ShopCorrect</strong>. All Rights Reserved.
            <span class="mx-2">|</span>
            <a href="#" class="text-decoration-none text-muted">Privacy</a> &middot;
            <a href="#" class="text-decoration-none text-muted">Terms</a>
        </p>
    </footer>
</main>

<!-- ✅ Bootstrap JS — loaded ONCE here only -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- ✅ Sidebar toggle — loaded ONCE here only -->
<script>
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar       = document.getElementById('sidebar');
    const overlay       = document.getElementById('sidebarOverlay');

    function openSidebar() {
        if (sidebar && overlay) {
            sidebar.classList.add('show');
            overlay.classList.add('show');
        }
    }
    function closeSidebar() {
        if (sidebar && overlay) {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        }
    }

    if (sidebarToggle) sidebarToggle.addEventListener('click', openSidebar);
    if (overlay)       overlay.addEventListener('click', closeSidebar);

    // Auto-close sidebar when a nav link is tapped on mobile
    document.querySelectorAll('.sidebar .nav-link').forEach(function(link) {
        link.addEventListener('click', function() {
            if (window.innerWidth < 992) closeSidebar();
        });
    });
</script>

</body>
</html>