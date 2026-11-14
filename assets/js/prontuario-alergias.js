document.addEventListener('DOMContentLoaded', function () {
    const pagina = document.querySelector('.allergy-page');

    if (!pagina) {
        return;
    }

    const atendimentoId =
        Number(pagina.dataset.atendimentoId || 0);

    const modal =
        document.getElementById('allergyModal');

    const btnNova =
        document.getElementById('btnNovaAlergia');

    const form =
        document.getElementById('formAlergia');

    const feedback =
        document.getElementById('allergyFormFeedback');

    const btnSalvar =
        document.getElementById('btnSalvarAlergia');

    function abrirModal() {
        if (!modal) {
            return;
        }

        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');

        window.setTimeout(function () {
            document.getElementById('substancia')?.focus();
        }, 80);
    }

    function fecharModal() {
        if (!modal) {
            return;
        }

        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');

        if (feedback) {
            feedback.hidden = true;
            feedback.textContent = '';
            feedback.className =
                'allergy-form-feedback';
        }
    }

    function mostrarFeedback(mensagem, tipo) {
        if (!feedback) {
            return;
        }

        feedback.textContent = mensagem;
        feedback.className =
            'allergy-form-feedback ' +
            (tipo === 'erro' ? 'error' : 'success');
        feedback.hidden = false;
    }

    async function enviar(payload) {
        const resposta = await fetch(
            '/safe-health/modules/prontuario/api/alergias.php',
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            }
        );

        let resultado;

        try {
            resultado = await resposta.json();
        } catch (erro) {
            throw new Error(
                'A resposta do servidor não pôde ser processada.'
            );
        }

        if (!resposta.ok || !resultado.sucesso) {
            throw new Error(
                resultado.mensagem
                    || 'Não foi possível concluir a operação.'
            );
        }

        return resultado;
    }

    btnNova?.addEventListener('click', abrirModal);

    document
        .querySelectorAll('[data-close-allergy-modal]')
        .forEach(function (elemento) {
            elemento.addEventListener(
                'click',
                fecharModal
            );
        });

    document.addEventListener('keydown', function (evento) {
        if (
            evento.key === 'Escape'
            && modal?.classList.contains('open')
        ) {
            fecharModal();
        }
    });

    form?.addEventListener('submit', async function (evento) {
        evento.preventDefault();

        if (!atendimentoId) {
            mostrarFeedback(
                'Atendimento inválido.',
                'erro'
            );
            return;
        }

        const dados = new FormData(form);

        const payload = {
            acao: 'registrar',
            atendimento_id: atendimentoId,
            substancia:
                String(dados.get('substancia') || '').trim(),
            tipo:
                String(dados.get('tipo') || ''),
            gravidade:
                String(dados.get('gravidade') || ''),
            reacao:
                String(dados.get('reacao') || '').trim(),
            observacoes:
                String(dados.get('observacoes') || '').trim()
        };

        btnSalvar.disabled = true;
        btnSalvar.classList.add('loading');

        try {
            const resultado = await enviar(payload);

            mostrarFeedback(
                resultado.mensagem,
                'sucesso'
            );

            window.setTimeout(function () {
                window.location.reload();
            }, 500);
        } catch (erro) {
            mostrarFeedback(
                erro.message,
                'erro'
            );
        } finally {
            btnSalvar.disabled = false;
            btnSalvar.classList.remove('loading');
        }
    });

    document
        .querySelectorAll('.btn-inactivate-allergy')
        .forEach(function (botao) {
            botao.addEventListener(
                'click',
                async function () {
                    const alergiaId =
                        Number(botao.dataset.allergyId || 0);

                    const nome =
                        botao.dataset.allergyName
                        || 'esta alergia';

                    const confirmar = window.confirm(
                        'Inativar o registro de alergia "' +
                        nome +
                        '"?\n\nO histórico será preservado.'
                    );

                    if (!confirmar) {
                        return;
                    }

                    botao.disabled = true;

                    try {
                        await enviar({
                            acao: 'inativar',
                            atendimento_id: atendimentoId,
                            alergia_id: alergiaId
                        });

                        window.location.reload();
                    } catch (erro) {
                        window.alert(erro.message);
                        botao.disabled = false;
                    }
                }
            );
        });

    document.addEventListener('prontuario:novo', function (event) {
        if (event.detail?.aba !== 'alergias') {
            return;
        }

        event.preventDefault();
        abrirModal();
    });

    document.addEventListener('prontuario:salvar', function (event) {
        if (event.detail?.aba !== 'alergias') {
            return;
        }

        event.preventDefault();

        window.alert(
            'Os registros de alergia são salvos individualmente no momento do cadastro.'
        );
    });

    document.addEventListener('prontuario:assinar', function (event) {
        if (event.detail?.aba !== 'alergias') {
            return;
        }

        event.preventDefault();

        window.alert(
            'A tela de Alergias mantém registros longitudinais do paciente e não possui documento pendente para assinatura.'
        );
    });
});
