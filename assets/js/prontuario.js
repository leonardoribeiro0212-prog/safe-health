
(function () {
    const toggle = document.getElementById('pepMenuToggle');

    if (!toggle) {
        return;
    }

    toggle.addEventListener('click', function () {
        document.body.classList.toggle('pep-menu-open');
    });

    document.addEventListener('click', function (evento) {
        if (window.innerWidth > 820) {
            return;
        }

        const sidebar = document.querySelector('.pep-sidebar');

        if (!sidebar) {
            return;
        }

        const clicouNoMenu = sidebar.contains(evento.target);
        const clicouNoBotao = toggle.contains(evento.target);

        if (!clicouNoMenu && !clicouNoBotao) {
            document.body.classList.remove('pep-menu-open');
        }
    });
})();


/*
 * Barra clínica padronizada do Prontuário
 * Novo | Salvar | Fechar e assinar
 *
 * Cada módulo implementado pode capturar os eventos abaixo:
 * prontuario:novo
 * prontuario:salvar
 * prontuario:assinar
 */
document.addEventListener('DOMContentLoaded', function () {
    const body = document.body;

    function emitirAcao(nomeAcao) {
        const evento = new CustomEvent(
            'prontuario:' + nomeAcao,
            {
                cancelable: true,
                detail: {
                    aba: body.dataset.prontuarioAba || '',
                    atendimentoId:
                        Number(body.dataset.atendimentoId || 0)
                }
            }
        );

        document.dispatchEvent(evento);

        if (!evento.defaultPrevented) {
            const nomes = {
                novo: 'Novo',
                salvar: 'Salvar',
                assinar: 'Fechar e assinar'
            };

            window.alert(
                'A ação "' + (nomes[nomeAcao] || nomeAcao) +
                '" será vinculada quando este módulo clínico for implementado.'
            );
        }
    }

    document.querySelector('[data-clinical-new]')
        ?.addEventListener('click', function () {
            emitirAcao('novo');
        });

    document.querySelector('[data-clinical-save]')
        ?.addEventListener('click', function () {
            emitirAcao('salvar');
        });

    document.querySelector('[data-clinical-sign]')
        ?.addEventListener('click', function () {
            emitirAcao('assinar');
        });
});
