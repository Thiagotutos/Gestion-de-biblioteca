        </main>
    </div> <!-- Cierre de main-container -->

    <!-- Botón Flotante para Volver Arriba -->
    <button type="button" id="btnScrollTop" class="btn-scroll-top" aria-label="Volver arriba" title="Volver arriba">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="19" x2="12" y2="5"></line>
            <polyline points="5 12 12 5 19 12"></polyline>
        </svg>
    </button>

    <script>
    (function() {
        var toggleBtn = document.getElementById('sidebarToggle');
        var closeBtn = document.getElementById('sidebarCloseBtn');
        var backdrop = document.getElementById('sidebarBackdrop');
        var scrollTopBtn = document.getElementById('btnScrollTop');

        function toggleMenu() {
            if (window.innerWidth <= 992) {
                document.body.classList.toggle('sidebar-open');
            } else {
                document.body.classList.toggle('sidebar-collapsed');
                try {
                    localStorage.setItem('sidebar_collapsed', document.body.classList.contains('sidebar-collapsed') ? '1' : '0');
                } catch(e) {}
            }
        }

        function closeMenu() {
            document.body.classList.remove('sidebar-open');
        }

        if (toggleBtn) toggleBtn.addEventListener('click', toggleMenu);
        if (closeBtn) closeBtn.addEventListener('click', closeMenu);
        if (backdrop) backdrop.addEventListener('click', closeMenu);

        // Recordar preferencia de sidebar colapsado en pantallas de escritorio / monitores
        try {
            if (window.innerWidth > 992 && localStorage.getItem('sidebar_collapsed') === '1') {
                document.body.classList.add('sidebar-collapsed');
            }
        } catch(e) {}

        // Botón Volver Arriba (Scroll to top)
        if (scrollTopBtn) {
            function checkScroll() {
                var scrollY = window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop || 0;
                if (scrollY > 220) {
                    scrollTopBtn.classList.add('visible');
                } else {
                    scrollTopBtn.classList.remove('visible');
                }
            }

            window.addEventListener('scroll', checkScroll, { passive: true });
            
            scrollTopBtn.addEventListener('click', function() {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
        }
    })();
    </script>
</body>
</html>
