<?php
// vendor/includes/footer.php
?>

        <footer class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 px-4 py-3 mt-auto border-top bg-white" style="font-size:0.83rem;">
            <div>
                &copy; <?= date('Y') ?> <strong class="notranslate">ShopCorrect</strong>. All rights reserved.
            </div>
            <div class="d-flex gap-3">
                <a href="#" class="text-decoration-none text-muted">Privacy Policy</a>
                <a href="#" class="text-decoration-none text-muted">Terms of Service</a>
            </div>
        </footer>

    </div></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    const sidebar  = document.getElementById('sidebar-wrapper');
    const overlay  = document.getElementById('sidebarOverlay');
    const menuBtn  = document.getElementById('menu-toggle');

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

    if (menuBtn)  menuBtn.addEventListener('click', openSidebar);
    if (overlay)  overlay.addEventListener('click', closeSidebar);

    // Auto-close sidebar when a nav link is tapped on mobile
    document.querySelectorAll('#sidebar-wrapper .nav-link').forEach(function(link) {
        link.addEventListener('click', function() {
            if (window.innerWidth < 992) closeSidebar();
        });
    });
</script>

<!--Start of Tawk.to Script-->
<script type="text/javascript">
var Tawk_API=Tawk_API||{}, Tawk_LoadStart=new Date();
(function(){
var s1=document.createElement("script"),s0=document.getElementsByTagName("script")[0];
s1.async=true;
s1.src='https://embed.tawk.to/69c25c194d7e6c1c3df7baa6/1jkfjfh6r';
s1.charset='UTF-8';
s1.setAttribute('crossorigin','*');
s0.parentNode.insertBefore(s1,s0);
})();
</script>
<!--End of Tawk.to Script-->

</body>
</html>