'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const configuracao = window.SafeHealthConta;

    if (!configuracao) {
        console.error('Configuração da conta não encontrada.');
        return;
    }

    const modal = document.getElementById('modalNovoItem');
    const formulario = document.getElementById('formNovoItem');
    const alerta = document.getElementById('alertaConta');

    const quantidade = document.getElementById('quantidadeItem');
    const valorUnitario = document.getElementById('valorUnitarioItem');
    const previewTotal = document.getElementById('previewValorTotal');

    function formatarMoeda(valor) {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        }).format(Number(valor || 0));
    }

    function mostrarMensagem(mensagem, tipo = 'sucesso') {
        alerta.textContent = mensagem;
        alerta.className = `alerta-conta alerta-${tipo}`;
        alerta.hidden = false;

        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });

        window.setTimeout(() => {
            alerta.hidden = true;
        }, 5000);
    }

    function abrirModal() {
        if (!modal) {
            return;
        }

        modal.hidden = false;
        document.body.classList.add('modal-aberto');

        document
            .getElementById('tipoItem')
            ?.focus();
    }

    function fecharModal() {
        if (!modal) {
            return;
        }

        modal.hidden = true;
        document.body.classList.remove('modal-aberto');
    }

    async function chamarApi(dados) {
        const resposta = await fetch(
            configuracao.apiUrl,
            {
                method: 'POST',

                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json'
                },

                body: JSON.stringify({
                    conta_id: configuracao.contaId,
                    ...dados
                })
            }
        );

        const texto = await resposta.text();

        let resultado;

        try {
            resultado = JSON.parse(texto);
        } catch (erro) {
            console.error('Resposta recebida:', texto);

            throw new Error(
                'A API retornou uma resposta inválida.'
            );
        }

        if (!resposta.ok || !resultado.sucesso) {
            throw new Error(
                resultado.mensagem ||
                'Não foi possível concluir a operação.'
            );
        }

        return resultado;
    }

    function bloquearBotao(botao, bloqueado, textoOriginal = '') {
        if (!botao) {
            return;
        }

        if (bloqueado) {
            botao.dataset.textoOriginal =
                botao.innerHTML;

            botao.disabled = true;

            botao.innerHTML = `
                <i class="fa-solid fa-spinner fa-spin"></i>
                Processando...
            `;
        } else {
            botao.disabled = false;

            botao.innerHTML =
                textoOriginal ||
                botao.dataset.textoOriginal ||
                'Salvar';
        }
    }

    function atualizarPreview() {
        const quantidadeValor =
            Number(quantidade?.value || 0);

        const valorUnitarioValor =
            Number(valorUnitario?.value || 0);

        previewTotal.textContent = formatarMoeda(
            quantidadeValor * valorUnitarioValor
        );
    }

    document
        .getElementById('abrirModalItem')
        ?.addEventListener('click', abrirModal);

    document
        .getElementById('abrirModalItemVazio')
        ?.addEventListener('click', abrirModal);

    document
        .getElementById('fecharModalItem')
        ?.addEventListener('click', fecharModal);

    document
        .getElementById('cancelarModalItem')
        ?.addEventListener('click', fecharModal);

    modal
        ?.querySelector('.modal-conta-fundo')
        ?.addEventListener('click', fecharModal);

    quantidade
        ?.addEventListener('input', atualizarPreview);

    valorUnitario
        ?.addEventListener('input', atualizarPreview);

    document.addEventListener('keydown', (evento) => {
        if (
            evento.key === 'Escape' &&
            modal &&
            !modal.hidden
        ) {
            fecharModal();
        }
    });

    formulario?.addEventListener(
        'submit',
        async (evento) => {
            evento.preventDefault();

            const botao =
                document.getElementById('salvarItem');

            bloquearBotao(botao, true);

            try {
                const formData =
                    new FormData(formulario);

                const dados =
                    Object.fromEntries(
                        formData.entries()
                    );

                const resultado = await chamarApi({
                    acao: 'adicionar_item',
                    ...dados
                });

                mostrarMensagem(
                    resultado.mensagem,
                    'sucesso'
                );

                fecharModal();

                window.setTimeout(() => {
                    window.location.reload();
                }, 700);
            } catch (erro) {
                mostrarMensagem(
                    erro.message,
                    'erro'
                );

                bloquearBotao(botao, false);
            }
        }
    );

    document
        .querySelectorAll('.seletor-status-item')
        .forEach((seletor) => {
            const statusAnterior = seletor.value;

            seletor.addEventListener(
                'change',
                async () => {
                    seletor.disabled = true;

                    try {
                        const resultado =
                            await chamarApi({
                                acao: 'alterar_status_item',
                                item_conta_id:
                                    Number(
                                        seletor.dataset.itemId
                                    ),
                                status_item:
                                    seletor.value
                            });

                        mostrarMensagem(
                            resultado.mensagem,
                            'sucesso'
                        );

                        window.setTimeout(() => {
                            window.location.reload();
                        }, 500);
                    } catch (erro) {
                        seletor.value = statusAnterior;
                        seletor.disabled = false;

                        mostrarMensagem(
                            erro.message,
                            'erro'
                        );
                    }
                }
            );
        });

    const botaoAuditoria =
        document.getElementById(
            'encaminharAuditoria'
        );

    botaoAuditoria?.addEventListener(
        'click',
        async () => {
            const confirmou = window.confirm(
                'Deseja encaminhar esta conta para auditoria?'
            );

            if (!confirmou) {
                return;
            }

            bloquearBotao(
                botaoAuditoria,
                true
            );

            try {
                const resultado = await chamarApi({
                    acao: 'encaminhar_auditoria'
                });

                mostrarMensagem(
                    resultado.mensagem,
                    'sucesso'
                );

                window.setTimeout(() => {
                    window.location.reload();
                }, 600);
            } catch (erro) {
                mostrarMensagem(
                    erro.message,
                    'erro'
                );

                bloquearBotao(
                    botaoAuditoria,
                    false
                );
            }
        }
    );

    const botaoFechar =
        document.getElementById('fecharConta');

    botaoFechar?.addEventListener(
        'click',
        async () => {
            const confirmou = window.confirm(
                'Confirma o fechamento da conta hospitalar? ' +
                'Após o fechamento, novos lançamentos serão bloqueados.'
            );

            if (!confirmou) {
                return;
            }

            bloquearBotao(
                botaoFechar,
                true
            );

            try {
                const resultado = await chamarApi({
                    acao: 'fechar_conta'
                });

                mostrarMensagem(
                    resultado.mensagem,
                    'sucesso'
                );

                window.setTimeout(() => {
                    window.location.reload();
                }, 600);
            } catch (erro) {
                mostrarMensagem(
                    erro.message,
                    'erro'
                );

                bloquearBotao(
                    botaoFechar,
                    false
                );
            }
        }
    );
});