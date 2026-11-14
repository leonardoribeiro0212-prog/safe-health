document.addEventListener('DOMContentLoaded', function () {
    const page =
        document.querySelector('.alta-page');

    if (!page) {
        return;
    }

    const atendimentoId =
        Number(page.dataset.atendimentoId || 0);

    const altaId =
        Number(page.dataset.altaId || 0);

    const apiUrl =
        '/safe-health/modules/prontuario/api/alta-medica.php';

    const feedback =
        document.getElementById('altaFeedback');

    async function api(payload) {
        const response =
            await fetch(
                apiUrl,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type':
                            'application/json',
                        'Accept':
                            'application/json'
                    },
                    body:
                        JSON.stringify(payload)
                }
            );

        let result;

        try {
            result =
                await response.json();
        } catch (error) {
            throw new Error(
                'A resposta do servidor não pôde ser processada.'
            );
        }

        if (
            !response.ok
            || !result.sucesso
        ) {
            throw new Error(
                result.mensagem
                    || 'Não foi possível concluir a operação.'
            );
        }

        return result;
    }

    function diagnosticos() {
        return Array.from(
            document.querySelectorAll(
                '#altaCidSelecionados .cid-selected-item'
            )
        ).map(function (item) {
            return {
                cid10_id:
                    Number(
                        item.dataset.cidId
                        || 0
                    ),
                tipo:
                    item.dataset.tipo
                    || 'SECUNDARIO'
            };
        });
    }

    function dadosFormulario() {
        return {
            diagnosticos:
                diagnosticos(),

            resumo_clinico:
                document.getElementById(
                    'altaResumoClinico'
                )?.value.trim() || '',

            condicao_alta:
                document.getElementById(
                    'altaCondicao'
                )?.value || '',

            destino_paciente:
                document.getElementById(
                    'altaDestino'
                )?.value || '',

            orientacoes:
                document.getElementById(
                    'altaOrientacoes'
                )?.value.trim() || '',

            retorno_recomendado:
                document.getElementById(
                    'altaRetorno'
                )?.value.trim() || '',

            observacoes:
                document.getElementById(
                    'altaObservacoes'
                )?.value.trim() || ''
        };
    }

    function mostrarFeedback(
        mensagem,
        tipo = 'success'
    ) {
        if (!feedback) {
            return;
        }

        feedback.textContent =
            mensagem;

        feedback.className =
            'allergy-form-feedback '
            + tipo;

        feedback.hidden =
            false;
    }

    function url(alta = 0) {
        return (
            '/safe-health/modules/prontuario/index.php'
            + '?aba=alta'
            + '&atendimento_id='
            + encodeURIComponent(
                atendimentoId
            )
            + (
                alta
                    ? '&alta_id='
                        + encodeURIComponent(alta)
                    : ''
            )
        );
    }

    async function novaAlta() {
        const result =
            await api({
                acao: 'novo',
                atendimento_id:
                    atendimentoId
            });

        const novoId =
            Number(
                result.dados?.alta_id
                || 0
            );

        if (!novoId) {
            throw new Error(
                'O servidor não retornou a nova Alta Médica.'
            );
        }

        window.location.href =
            url(novoId);
    }

    document.querySelectorAll(
        '#btnNovaAlta'
    ).forEach(function (button) {
        button.addEventListener(
            'click',
            async function () {
                button.disabled = true;

                try {
                    await novaAlta();
                } catch (error) {
                    window.alert(
                        error.message
                    );

                    button.disabled =
                        false;
                }
            }
        );
    });

    document.getElementById(
        'btnSalvarAlta'
    )?.addEventListener(
        'click',
        async function () {
            this.disabled = true;

            try {
                const result =
                    await api({
                        acao: 'salvar',
                        atendimento_id:
                            atendimentoId,
                        alta_id:
                            altaId,
                        ...dadosFormulario()
                    });

                mostrarFeedback(
                    result.mensagem,
                    'success'
                );
            } catch (error) {
                mostrarFeedback(
                    error.message,
                    'error'
                );
            } finally {
                this.disabled =
                    false;
            }
        }
    );

    document.getElementById(
        'btnExcluirAlta'
    )?.addEventListener(
        'click',
        async function () {
            if (
                !window.confirm(
                    'Excluir esta Alta Médica em rascunho?'
                )
            ) {
                return;
            }

            this.disabled = true;

            try {
                const result =
                    await api({
                        acao: 'excluir',
                        atendimento_id:
                            atendimentoId,
                        alta_id:
                            altaId
                    });

                window.alert(
                    result.mensagem
                );

                window.location.href =
                    url();
            } catch (error) {
                window.alert(
                    error.message
                );

                this.disabled =
                    false;
            }
        }
    );

    document.getElementById(
        'btnFinalizarAtendimento'
    )?.addEventListener(
        'click',
        async function () {
            const confirmar =
                window.confirm(
                    'Finalizar este atendimento?\n\n'
                    + 'A Alta Médica será finalizada e o atendimento será encerrado.'
                );

            if (!confirmar) {
                return;
            }

            this.disabled = true;

            try {
                const result =
                    await api({
                        acao: 'finalizar',
                        atendimento_id:
                            atendimentoId,
                        alta_id:
                            altaId,
                        ...dadosFormulario()
                    });

                window.alert(
                    result.mensagem
                );

                window.location.href =
                    '/safe-health/modules/prontuario/atendimentos.php?aba=finalizados';
            } catch (error) {
                window.alert(
                    error.message
                );

                this.disabled =
                    false;
            }
        }
    );

});
