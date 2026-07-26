document.addEventListener('DOMContentLoaded', function () {
    const botaoMenu = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    const appContent = document.querySelector('.app-content');

    if (!botaoMenu || !sidebar || !appContent) {
        console.error('Não foi possível localizar os elementos do menu lateral.');
        return;
    }

    const limiteMobile = 760;

    function estaNoMobile() {
        return window.innerWidth <= limiteMobile;
    }

    function atualizarAcessibilidade() {
        const menuAberto = estaNoMobile()
            ? sidebar.classList.contains('open-mobile')
            : !sidebar.classList.contains('collapsed');

        botaoMenu.setAttribute(
            'aria-expanded',
            String(menuAberto)
        );
    }

    function aplicarEstadoSalvo() {
        if (estaNoMobile()) {
            sidebar.classList.remove('collapsed');
            sidebar.classList.remove('open-mobile');
            appContent.classList.remove('sidebar-collapsed');

            atualizarAcessibilidade();
            return;
        }

        const menuRecolhido =
            localStorage.getItem('safeHealthSidebarCollapsed') === 'true';

        sidebar.classList.toggle(
            'collapsed',
            menuRecolhido
        );

        appContent.classList.toggle(
            'sidebar-collapsed',
            menuRecolhido
        );

        atualizarAcessibilidade();
    }

    botaoMenu.addEventListener('click', function () {
        if (estaNoMobile()) {
            sidebar.classList.toggle('open-mobile');
            atualizarAcessibilidade();
            return;
        }

        const menuRecolhido =
            sidebar.classList.toggle('collapsed');

        appContent.classList.toggle(
            'sidebar-collapsed',
            menuRecolhido
        );

        localStorage.setItem(
            'safeHealthSidebarCollapsed',
            String(menuRecolhido)
        );

        atualizarAcessibilidade();
    });

    window.addEventListener('resize', aplicarEstadoSalvo);

    aplicarEstadoSalvo();
});