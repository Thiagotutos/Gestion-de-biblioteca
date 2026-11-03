        </main>
    </div> <!-- Cierre de main-container -->

    <script>
    (function() {
        var toggleBtn = document.getElementById('sidebarToggle');
        var closeBtn = document.getElementById('sidebarCloseBtn');
        var backdrop = document.getElementById('sidebarBackdrop');

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
    })();
    </script>
</body>
</html>
