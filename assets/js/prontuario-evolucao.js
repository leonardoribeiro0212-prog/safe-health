document.addEventListener('DOMContentLoaded', function () {
    const page = document.querySelector('.evo-page');

    if (!page) {
        return;
    }

    const atendimentoId =
        Number(page.dataset.atendimentoId || 0);

    const evolucaoId =
        Number(page.dataset.evolucaoId || 0);

    const feedback =
        document.getElementById('evoFeedback');

    const apiUrl =
        '/safe-health/modules/prontuario/api/evolucao.php';

    async function api(payload) {
        const response = await fetch(
            apiUrl,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            }
        );

        let result;

        try {
            result = await response.json();
        } catch (error) {
            throw new Error(
                'A resposta do servidor não pôde ser processada.'
            );
        }

        if (!response.ok || !result.sucesso) {
            throw new Error(
                result.mensagem
                    || 'Não foi possível concluir a operação.'
            );
        }

        return result;
    }

    function dadosFormulario() {
        return {
            subjetivo:
                document.getElementById(
                    'evoSubjetivo'
                )?.value.trim() || '',

            objetivo:
                document.getElementById(
                    'evoObjetivo'
                )?.value.trim() || '',

            avaliacao:
                document.getElementById(
                    'evoAvaliacao'
                )?.value.trim() || '',

            plano:
                document.getElementById(
                    'evoPlano'
                )?.value.trim() || '',

            observacoes:
                document.getElementById(
                    'evoObservacoes'
                )?.value.trim() || '',

            diagnosticos:
                Array.from(
                    document.querySelectorAll(
                        '#cidSelecionados .cid-selected-item'
                    )
                ).map(function (item) {
                    return {
                        cid10_id:
                            Number(item.dataset.cidId || 0),
                        tipo:
                            item.dataset.tipo || 'SECUNDARIO'
                    };
                })
        };
    }

    function mostrarFeedback(
        mensagem,
        tipo = 'success'
    ) {
        if (!feedback) {
            return;
        }

        feedback.textContent = mensagem;
        feedback.className =
            'allergy-form-feedback ' + tipo;
        feedback.hidden = false;
    }

    async function novaEvolucao() {
        const result = await api({
            acao: 'novo',
            atendimento_id: atendimentoId
        });

        const novoId =
            Number(result.dados?.evolucao_id || 0);

        if (!novoId) {
            throw new Error(
                'O servidor não retornou o novo documento.'
            );
        }

        window.location.href =
            '/safe-health/modules/prontuario/index.php'
            + '?aba=evolucao'
            + '&atendimento_id='
            + encodeURIComponent(atendimentoId)
            + '&evolucao_id='
            + encodeURIComponent(novoId);
    }

    async function salvar() {
        if (!evolucaoId) {
            throw new Error(
                'Nenhuma Evolução Médica está aberta.'
            );
        }

        const result = await api({
            acao: 'salvar',
            atendimento_id: atendimentoId,
            evolucao_id: evolucaoId,
            ...dadosFormulario()
        });

        mostrarFeedback(
            result.mensagem,
            'success'
        );

        return result;
    }

    document.querySelectorAll(
        '#btnNovaEvolucao'
    ).forEach(function (button) {
        button.addEventListener(
            'click',
            async function () {
                button.disabled = true;

                try {
                    await novaEvolucao();
                } catch (error) {
                    window.alert(error.message);
                    button.disabled = false;
                }
            }
        );
    });

    document.getElementById(
        'btnSalvarEvolucao'
    )?.addEventListener(
        'click',
        async function () {
            this.disabled = true;

            try {
                await salvar();
            } catch (error) {
                mostrarFeedback(
                    error.message,
                    'error'
                );
            } finally {
                this.disabled = false;
            }
        }
    );

    document.getElementById(
        'btnExcluirEvolucao'
    )?.addEventListener(
        'click',
        async function () {
            if (!evolucaoId) {
                return;
            }

            const confirmar =
                window.confirm(
                    'Excluir esta Evolução Médica em rascunho?\n\n'
                    + 'Documentos já assinados nunca podem ser excluídos.'
                );

            if (!confirmar) {
                return;
            }

            this.disabled = true;

            try {
                const result = await api({
                    acao: 'excluir',
                    atendimento_id: atendimentoId,
                    evolucao_id: evolucaoId
                });

                window.alert(
                    result.mensagem
                );

                window.location.href =
                    '/safe-health/modules/prontuario/index.php'
                    + '?aba=evolucao'
                    + '&atendimento_id='
                    + encodeURIComponent(
                        atendimentoId
                    );

            } catch (error) {
                window.alert(error.message);
                this.disabled = false;
            }
        }
    );

    document.getElementById(
        'btnAssinarEvolucao'
    )?.addEventListener(
        'click',
        async function () {
            if (!evolucaoId) {
                return;
            }

            const confirmar =
                window.confirm(
                    'Fechar e assinar esta Evolução Médica?\n\n'
                    + 'Após a assinatura, o documento não poderá ser editado diretamente.'
                );

            if (!confirmar) {
                return;
            }

            this.disabled = true;

            try {
                const result = await api({
                    acao: 'finalizar',
                    atendimento_id: atendimentoId,
                    evolucao_id: evolucaoId,
                    ...dadosFormulario()
                });

                window.alert(
                    result.mensagem
                );

                window.location.href =
                    '/safe-health/modules/prontuario/index.php'
                    + '?aba=evolucao'
                    + '&atendimento_id='
                    + encodeURIComponent(
                        atendimentoId
                    );

            } catch (error) {
                window.alert(error.message);
                this.disabled = false;
            }
        }
    );

    const cidBusca =
        document.getElementById('cidBusca');
    const cidResultados =
        document.getElementById('cidResultados');
    const cidTipo =
        document.getElementById('cidTipo');
    const btnAdicionarCid =
        document.getElementById('btnAdicionarCid');
    const cidSelecionados =
        document.getElementById('cidSelecionados');

    let cidEscolhido = null;
    let cidTimer = null;

    function escapeHtmlCid(valor) {
        const div = document.createElement('div');
        div.textContent = String(valor ?? '');
        return div.innerHTML;
    }

    function atualizarRemocaoCid() {
        document.querySelectorAll(
            '#cidSelecionados .cid-remove-button'
        ).forEach(function (button) {
            button.onclick = function () {
                button.closest(
                    '.cid-selected-item'
                )?.remove();
            };
        });
    }

    function cidJaSelecionado(id) {
        return !!document.querySelector(
            '#cidSelecionados .cid-selected-item'
            + '[data-cid-id="' + Number(id) + '"]'
        );
    }

    function possuiPrincipal() {
        return !!document.querySelector(
            '#cidSelecionados .cid-selected-item'
            + '[data-tipo="PRINCIPAL"]'
        );
    }

    function renderCidSelecionado(cid, tipo) {
        if (!cidSelecionados || cidJaSelecionado(cid.id)) {
            return;
        }

        if (tipo === 'PRINCIPAL' && possuiPrincipal()) {
            tipo = 'SECUNDARIO';
        }

        const item = document.createElement('div');
        item.className = 'cid-selected-item';
        item.dataset.cidId = String(cid.id);
        item.dataset.tipo = tipo;

        item.innerHTML =
            '<div>'
            + '<strong>' + escapeHtmlCid(cid.codigo) + '</strong>'
            + '<span>' + escapeHtmlCid(cid.descricao) + '</span>'
            + '</div>'
            + '<div class="cid-selected-actions">'
            + '<span class="cid-type-badge '
            + tipo.toLowerCase()
            + '">'
            + (tipo === 'PRINCIPAL' ? 'Principal' : 'Secundário')
            + '</span>'
            + '<button type="button" class="cid-remove-button"'
            + ' title="Remover CID">'
            + '<i class="fa-solid fa-xmark"></i>'
            + '</button>'
            + '</div>';

        cidSelecionados.appendChild(item);
        atualizarRemocaoCid();
    }

    cidBusca?.addEventListener('input', function () {
        clearTimeout(cidTimer);
        cidEscolhido = null;

        if (btnAdicionarCid) {
            btnAdicionarCid.disabled = true;
        }

        const termo = cidBusca.value.trim();

        if (termo.length < 2) {
            if (cidResultados) {
                cidResultados.hidden = true;
                cidResultados.innerHTML = '';
            }
            return;
        }

        cidTimer = setTimeout(async function () {
            try {
                const response = await fetch(
                    '/safe-health/modules/prontuario/api/cid10.php?q='
                    + encodeURIComponent(termo),
                    {
                        headers: {
                            'Accept': 'application/json'
                        }
                    }
                );

                const result = await response.json();

                if (!response.ok || !result.sucesso) {
                    throw new Error(
                        result.mensagem || 'Falha na pesquisa.'
                    );
                }

                if (!cidResultados) {
                    return;
                }

                cidResultados.innerHTML = '';

                if (!result.dados.length) {
                    cidResultados.innerHTML =
                        '<div class="cid-empty">'
                        + 'Nenhum CID encontrado.'
                        + '</div>';
                    cidResultados.hidden = false;
                    return;
                }

                result.dados.forEach(function (cid) {
                    const option =
                        document.createElement('button');

                    option.type = 'button';
                    option.className = 'cid-result-item';
                    option.innerHTML =
                        '<strong>'
                        + escapeHtmlCid(cid.codigo)
                        + '</strong>'
                        + '<span>'
                        + escapeHtmlCid(cid.descricao)
                        + '</span>';

                    option.addEventListener(
                        'click',
                        function () {
                            cidEscolhido = cid;
                            cidBusca.value =
                                cid.codigo
                                + ' — '
                                + cid.descricao;
                            cidResultados.hidden = true;

                            if (btnAdicionarCid) {
                                btnAdicionarCid.disabled = false;
                            }
                        }
                    );

                    cidResultados.appendChild(option);
                });

                cidResultados.hidden = false;
            } catch (error) {
                if (cidResultados) {
                    cidResultados.innerHTML =
                        '<div class="cid-empty">'
                        + escapeHtmlCid(error.message)
                        + '</div>';
                    cidResultados.hidden = false;
                }
            }
        }, 250);
    });

    btnAdicionarCid?.addEventListener(
        'click',
        function () {
            if (!cidEscolhido) {
                return;
            }

            renderCidSelecionado(
                cidEscolhido,
                cidTipo?.value || 'SECUNDARIO'
            );

            cidEscolhido = null;
            cidBusca.value = '';
            btnAdicionarCid.disabled = true;

            if (cidResultados) {
                cidResultados.hidden = true;
                cidResultados.innerHTML = '';
            }
        }
    );

    document.addEventListener('click', function (event) {
        if (
            cidResultados
            && !event.target.closest('.cid-autocomplete')
        ) {
            cidResultados.hidden = true;
        }
    });

    atualizarRemocaoCid();

});
