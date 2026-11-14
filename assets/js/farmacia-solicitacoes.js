document.addEventListener('DOMContentLoaded', function () {
    const numeroAtendimento =
        document.getElementById('numeroAtendimento');

    const botaoPesquisar =
        document.getElementById('botaoPesquisar');

    const listaAbertos =
        document.getElementById('listaAtendimentosAbertos');

    const dadosPacienteCard =
        document.getElementById('dadosPacienteCard');

    const form =
        document.getElementById('formSolicitacao');

    const historicoCard =
        document.getElementById('historicoCard');

    const historico =
        document.getElementById('historicoSolicitacoes');

    const mensagem =
        document.getElementById('mensagemSolicitacao');

    const atendimentoId =
        document.getElementById('atendimentoId');

    const pacienteId =
        document.getElementById('pacienteId');

    const pacienteNome =
        document.getElementById('pacienteNome');

    const pacienteCpf =
        document.getElementById('pacienteCpf');

    const atendimentoNumero =
        document.getElementById('atendimentoNumero');

    const atendimentoConvenio =
        document.getElementById('atendimentoConvenio');

    const tipoSolicitacao =
        document.getElementById('tipoSolicitacao');

    const campoMedicamento =
        document.getElementById('campoMedicamento');

    const campoMaterial =
        document.getElementById('campoMaterial');

    const medicamentoId =
        document.getElementById('medicamentoId');

    const materialId =
        document.getElementById('materialId');

    function escapar(valor) {
        return String(valor ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function formatarCpf(valor) {
        const n =
            String(valor ?? '')
                .replace(/\D/g, '')
                .slice(0, 11);

        return n
            .replace(/^(\d{3})(\d)/, '$1.$2')
            .replace(
                /^(\d{3})\.(\d{3})(\d)/,
                '$1.$2.$3'
            )
            .replace(
                /\.(\d{3})(\d)/,
                '.$1-$2'
            );
    }

    function formatarData(valor) {
        if (!valor) return '-';

        const data =
            new Date(
                String(valor).replace(' ', 'T')
            );

        return Number.isNaN(data.getTime())
            ? valor
            : data.toLocaleString('pt-BR');
    }

    function statusLegivel(valor) {
        return String(valor ?? '')
            .replaceAll('_', ' ')
            .toLowerCase()
            .replace(
                /\b\w/g,
                letra => letra.toUpperCase()
            );
    }

    function mostrarMensagem(
        texto,
        tipo = 'success'
    ) {
        mensagem.hidden = false;
        mensagem.className =
            `pharmacy-message ${tipo}`;
        mensagem.textContent = texto;
    }

    function ocultarMensagem() {
        mensagem.hidden = true;
        mensagem.textContent = '';
        mensagem.className =
            'pharmacy-message';
    }

    async function lerJson(resposta) {
        const texto =
            await resposta.text();

        try {
            return JSON.parse(texto);
        } catch {
            console.error(
                'Resposta inválida:',
                texto
            );

            throw new Error(
                'O servidor retornou uma resposta inválida.'
            );
        }
    }

    function atualizarTipo() {
        const medicamento =
            tipoSolicitacao.value ===
            'MEDICAMENTO';

        campoMedicamento.hidden =
            !medicamento;

        campoMaterial.hidden =
            medicamento;

        medicamentoId.required =
            medicamento;

        materialId.required =
            !medicamento;

        if (medicamento) {
            materialId.value = '';
        } else {
            medicamentoId.value = '';
        }
    }

    function renderizarAtendimentos(lista) {
        if (
            !Array.isArray(lista) ||
            lista.length === 0
        ) {
            listaAbertos.innerHTML = `
                <div class="empty-state">
                    Nenhum atendimento em aberto.
                </div>
            `;

            return;
        }

        listaAbertos.innerHTML = `
            <div class="open-table">
                <div class="open-row open-header">
                    <span>Atendimento</span>
                    <span>Paciente</span>
                    <span>Tipo</span>
                    <span>Status</span>
                    <span>Entrada</span>
                    <span>Ação</span>
                </div>

                ${lista.map(item => `
                    <div class="open-row">
                        <span>
                            <strong>
                                ${escapar(item.numero_atendimento)}
                            </strong>
                        </span>

                        <span>
                            ${escapar(item.paciente_nome)}
                        </span>

                        <span>
                            ${escapar(item.tipo_atendimento || '-')}
                        </span>

                        <span>
                            ${escapar(statusLegivel(item.status))}
                        </span>

                        <span>
                            ${escapar(formatarData(item.data_entrada))}
                        </span>

                        <span>
                            <button
                                type="button"
                                class="select-attendance-button"
                                data-atendimento="${escapar(item.numero_atendimento)}"
                            >
                                <i class="fa-solid fa-cart-plus"></i>
                                Solicitar
                            </button>
                        </span>
                    </div>
                `).join('')}
            </div>
        `;
    }

    async function carregarAtendimentos() {
        try {
            const resposta = await fetch(
                '/safe-health/modules/farmacia/api/solicitacoes.php?acao=listar_abertos',
                {
                    headers: {
                        Accept: 'application/json'
                    },
                    cache: 'no-store'
                }
            );

            const resultado =
                await lerJson(resposta);

            if (
                !resposta.ok ||
                !resultado.sucesso
            ) {
                throw new Error(
                    resultado.mensagem ||
                    'Erro ao carregar atendimentos.'
                );
            }

            renderizarAtendimentos(
                resultado.dados.atendimentos ?? []
            );

        } catch (erro) {
            listaAbertos.innerHTML = `
                <div class="empty-state">
                    ${escapar(
                        erro instanceof Error
                            ? erro.message
                            : 'Erro ao carregar atendimentos.'
                    )}
                </div>
            `;
        }
    }

    async function carregarItens() {
        const resposta = await fetch(
            '/safe-health/modules/farmacia/api/solicitacoes.php?acao=itens',
            {
                headers: {
                    Accept: 'application/json'
                },
                cache: 'no-store'
            }
        );

        const resultado =
            await lerJson(resposta);

        if (
            !resposta.ok ||
            !resultado.sucesso
        ) {
            throw new Error(
                resultado.mensagem ||
                'Não foi possível carregar os itens.'
            );
        }

        medicamentoId.innerHTML = `
            <option value="">Selecione</option>
            ${(resultado.dados.medicamentos ?? [])
                .map(item => `
                    <option value="${Number(item.id)}">
                        ${escapar(item.nome)}
                        ${item.concentracao ? ' — ' + escapar(item.concentracao) : ''}
                    </option>
                `).join('')}
        `;

        materialId.innerHTML = `
            <option value="">Selecione</option>
            ${(resultado.dados.materiais ?? [])
                .map(item => `
                    <option value="${Number(item.id)}">
                        ${escapar(item.nome)}
                        ${item.unidade_medida ? ' — ' + escapar(item.unidade_medida) : ''}
                    </option>
                `).join('')}
        `;
    }

    function renderizarHistorico(lista) {
        if (
            !Array.isArray(lista) ||
            lista.length === 0
        ) {
            historico.innerHTML = `
                <div class="empty-state">
                    Nenhuma solicitação registrada neste atendimento.
                </div>
            `;

            return;
        }

        historico.innerHTML =
            lista.map(item => `
                <article class="request-item">

                    <div class="request-top">
                        <div>
                            <strong>
                                ${escapar(item.item_nome || '-')}
                            </strong>

                            <span>
                                ${escapar(
                                    statusLegivel(
                                        item.tipo_solicitacao
                                    )
                                )}
                                · Quantidade:
                                ${escapar(item.quantidade)}
                            </span>
                        </div>

                        <div class="request-badges">
                            <span class="priority-badge ${item.prioridade === 'URGENTE' ? 'urgent' : ''}">
                                ${escapar(statusLegivel(item.prioridade))}
                            </span>

                            <span class="status-badge">
                                ${escapar(statusLegivel(item.status))}
                            </span>
                        </div>
                    </div>

                    <div class="request-meta">
                        <span>
                            <strong>Solicitado em:</strong>
                            ${escapar(formatarData(item.criado_em))}
                        </span>

                        <span>
                            <strong>Solicitante:</strong>
                            ${escapar(
                                item.usuario_nome ||
                                'Não identificado'
                            )}
                        </span>
                    </div>

                    ${item.observacoes
                        ? `<div class="request-note">${escapar(item.observacoes)}</div>`
                        : ''}

                </article>
            `).join('');
    }

    async function carregarHistorico() {
        const resposta = await fetch(
            `/safe-health/modules/farmacia/api/solicitacoes.php?acao=historico&atendimento_id=${encodeURIComponent(atendimentoId.value)}`,
            {
                headers: {
                    Accept: 'application/json'
                },
                cache: 'no-store'
            }
        );

        const resultado =
            await lerJson(resposta);

        if (
            !resposta.ok ||
            !resultado.sucesso
        ) {
            throw new Error(
                resultado.mensagem ||
                'Não foi possível carregar o histórico.'
            );
        }

        renderizarHistorico(
            resultado.dados.solicitacoes ?? []
        );

        historicoCard.hidden = false;
    }

    async function pesquisarAtendimento(
        numeroForcado = null
    ) {
        const numero =
            String(
                numeroForcado ??
                numeroAtendimento.value
            ).trim();

        if (!numero) {
            alert(
                'Informe o número do atendimento.'
            );

            return;
        }

        try {
            const resposta = await fetch(
                `/safe-health/modules/farmacia/api/solicitacoes.php?acao=buscar_atendimento&numero=${encodeURIComponent(numero)}`,
                {
                    headers: {
                        Accept: 'application/json'
                    },
                    cache: 'no-store'
                }
            );

            const resultado =
                await lerJson(resposta);

            if (
                !resposta.ok ||
                !resultado.sucesso
            ) {
                throw new Error(
                    resultado.mensagem ||
                    'Atendimento não encontrado.'
                );
            }

            const a =
                resultado.dados.atendimento;

            numeroAtendimento.value =
                a.numero_atendimento || numero;

            atendimentoId.value =
                String(a.atendimento_id);

            pacienteId.value =
                String(a.paciente_id);

            pacienteNome.textContent =
                a.paciente_nome || '-';

            pacienteCpf.textContent =
                a.paciente_cpf
                    ? formatarCpf(a.paciente_cpf)
                    : 'Não informado';

            atendimentoNumero.textContent =
                a.numero_atendimento || '-';

            atendimentoConvenio.textContent =
                a.convenio || 'Não informado';

            dadosPacienteCard.hidden = false;
            form.hidden = false;

            ocultarMensagem();

            await carregarHistorico();

            dadosPacienteCard.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });

        } catch (erro) {
            alert(
                erro instanceof Error
                    ? erro.message
                    : 'Erro ao localizar atendimento.'
            );
        }
    }

    form.addEventListener(
        'submit',
        async function (evento) {
            evento.preventDefault();

            if (!form.reportValidity()) {
                return;
            }

            const dados =
                Object.fromEntries(
                    new FormData(form).entries()
                );

            try {
                const resposta = await fetch(
                    '/safe-health/modules/farmacia/api/solicitacoes.php',
                    {
                        method: 'POST',

                        headers: {
                            'Content-Type':
                                'application/json',

                            Accept:
                                'application/json'
                        },

                        body:
                            JSON.stringify(dados)
                    }
                );

                const resultado =
                    await lerJson(resposta);

                if (
                    !resposta.ok ||
                    !resultado.sucesso
                ) {
                    throw new Error(
                        resultado.mensagem ||
                        'Não foi possível salvar.'
                    );
                }

                const atendimentoAtual =
                    atendimentoId.value;

                const pacienteAtual =
                    pacienteId.value;

                form.reset();

                atendimentoId.value =
                    atendimentoAtual;

                pacienteId.value =
                    pacienteAtual;

                atualizarTipo();

                mostrarMensagem(
                    resultado.mensagem
                );

                await carregarHistorico();

            } catch (erro) {
                mostrarMensagem(
                    erro instanceof Error
                        ? erro.message
                        : 'Erro ao salvar solicitação.',
                    'error'
                );
            }
        }
    );

    tipoSolicitacao.addEventListener(
        'change',
        atualizarTipo
    );

    botaoPesquisar.addEventListener(
        'click',
        () => pesquisarAtendimento()
    );

    numeroAtendimento.addEventListener(
        'keydown',
        function (evento) {
            if (evento.key === 'Enter') {
                evento.preventDefault();
                pesquisarAtendimento();
            }
        }
    );

    listaAbertos.addEventListener(
        'click',
        function (evento) {
            const botao =
                evento.target.closest(
                    '.select-attendance-button'
                );

            if (!botao) {
                return;
            }

            pesquisarAtendimento(
                botao.dataset.atendimento ||
                ''
            );
        }
    );

    atualizarTipo();

    carregarItens().catch(
        erro => {
            alert(
                erro instanceof Error
                    ? erro.message
                    : 'Erro ao carregar itens.'
            );
        }
    );

    carregarAtendimentos();

    setInterval(
        carregarAtendimentos,
        30000
    );
});
