document.addEventListener('DOMContentLoaded', function () {
    const lista =
        document.getElementById('listaPrescricoes');

    const detalhesCard =
        document.getElementById('detalhesCard');

    const campoPesquisa =
        document.getElementById('campoPesquisa');

    const mensagem =
        document.getElementById('mensagemFarmacia');

    let prescricaoSelecionada = null;

    function escapar(valor) {
        return String(valor ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
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

    function renderizarLista(prescricoes) {
        if (
            !Array.isArray(prescricoes) ||
            prescricoes.length === 0
        ) {
            lista.innerHTML = `
                <div class="empty-state">
                    <i class="fa-solid fa-prescription-bottle-medical"></i>
                    <strong>Nenhuma prescrição pendente</strong>
                    <span>
                        Quando uma prescrição médica for liberada para a Farmácia,
                        ela aparecerá automaticamente aqui.
                    </span>
                </div>
            `;

            return;
        }

        lista.innerHTML = `
            <div class="prescription-table">

                <div class="prescription-row prescription-header">
                    <span>Prescrição</span>
                    <span>Atendimento</span>
                    <span>Paciente</span>
                    <span>Itens</span>
                    <span>Status</span>
                    <span>Liberada em</span>
                    <span>Ação</span>
                </div>

                ${prescricoes.map(item => `
                    <div class="prescription-row">
                        <span><strong>#${Number(item.id)}</strong></span>
                        <span>${escapar(item.numero_atendimento)}</span>
                        <span>${escapar(item.paciente_nome)}</span>
                        <span>
                            ${Number(item.itens_dispensados || 0)}
                            /
                            ${Number(item.total_itens || 0)}
                        </span>
                        <span>
                            <span class="status-badge">
                                ${escapar(statusLegivel(item.status))}
                            </span>
                        </span>
                        <span>
                            ${escapar(
                                formatarData(
                                    item.data_liberacao_farmacia ||
                                    item.data_prescricao
                                )
                            )}
                        </span>
                        <span>
                            <button
                                type="button"
                                class="open-button"
                                data-id="${Number(item.id)}"
                            >
                                <i class="fa-solid fa-folder-open"></i>
                                Abrir
                            </button>
                        </span>
                    </div>
                `).join('')}

            </div>
        `;
    }

    async function carregarLista() {
        const q =
            campoPesquisa.value.trim();

        lista.innerHTML = `
            <div class="empty-state">
                Carregando prescrições...
            </div>
        `;

        try {
            const resposta = await fetch(
                `/safe-health/modules/farmacia/api/prescricoes-pendentes.php?acao=listar&q=${encodeURIComponent(q)}`,
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
                    'Não foi possível carregar as prescrições.'
                );
            }

            renderizarLista(
                resultado.dados.prescricoes ?? []
            );

        } catch (erro) {
            lista.innerHTML = `
                <div class="empty-state error">
                    ${escapar(
                        erro instanceof Error
                            ? erro.message
                            : 'Erro ao carregar prescrições.'
                    )}
                </div>
            `;
        }
    }

    function renderizarItens(itens) {
        const container =
            document.getElementById(
                'listaItensPrescricao'
            );

        if (
            !Array.isArray(itens) ||
            itens.length === 0
        ) {
            container.innerHTML = `
                <div class="empty-state compact">
                    Esta prescrição não possui medicamentos ativos.
                </div>
            `;

            return;
        }

        container.innerHTML = itens
            .map(item => `
                <div class="medication-item">
                    <div>
                        <strong>
                            ${escapar(item.medicamento_nome)}
                        </strong>

                        <span>
                            ${escapar(
                                [
                                    item.concentracao,
                                    item.apresentacao
                                ].filter(Boolean).join(' · ')
                            )}
                        </span>
                    </div>

                    <div class="medication-data">
                        <span>
                            <strong>Dose:</strong>
                            ${escapar(item.dose || '-')}
                        </span>

                        <span>
                            <strong>Via:</strong>
                            ${escapar(item.via_administracao || '-')}
                        </span>

                        <span>
                            <strong>Frequência:</strong>
                            ${escapar(item.frequencia || '-')}
                        </span>

                        <span>
                            <strong>Quantidade:</strong>
                            ${escapar(item.quantidade || '-')}
                        </span>
                    </div>

                    <span class="item-status">
                        ${escapar(statusLegivel(item.status_farmacia))}
                    </span>

                    ${item.instrucoes
                        ? `<div class="item-note">${escapar(item.instrucoes)}</div>`
                        : ''}
                </div>
            `)
            .join('');
    }

    async function abrirPrescricao(id) {
        try {
            const resposta = await fetch(
                `/safe-health/modules/farmacia/api/prescricoes-pendentes.php?acao=detalhes&id=${encodeURIComponent(id)}`,
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
                    'Não foi possível abrir a prescrição.'
                );
            }

            const p =
                resultado.dados.prescricao;

            prescricaoSelecionada =
                Number(p.id);

            document.getElementById(
                'detalheTitulo'
            ).textContent =
                `Prescrição #${p.id}`;

            document.getElementById(
                'detalheSubtitulo'
            ).textContent =
                'Prescrição liberada para a Farmácia.';

            document.getElementById(
                'detalheStatus'
            ).textContent =
                statusLegivel(p.status);

            document.getElementById(
                'detalhePaciente'
            ).textContent =
                p.paciente_nome || '-';

            document.getElementById(
                'detalheAtendimento'
            ).textContent =
                p.numero_atendimento || '-';

            document.getElementById(
                'detalheConvenio'
            ).textContent =
                p.convenio || 'Não informado';

            document.getElementById(
                'detalheData'
            ).textContent =
                formatarData(p.data_prescricao);

            renderizarItens(
                resultado.dados.itens ?? []
            );

            const observacoes =
                document.getElementById(
                    'observacoesPrescricao'
                );

            if (p.observacoes) {
                observacoes.hidden = false;
                observacoes.textContent =
                    p.observacoes;
            } else {
                observacoes.hidden = true;
                observacoes.textContent = '';
            }

            detalhesCard.hidden = false;

            detalhesCard.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });

        } catch (erro) {
            mostrarMensagem(
                erro instanceof Error
                    ? erro.message
                    : 'Erro ao abrir prescrição.',
                'error'
            );
        }
    }

    async function iniciarDispensacao() {
        if (!prescricaoSelecionada) {
            return;
        }

        const botao =
            document.getElementById(
                'botaoIniciarDispensacao'
            );

        botao.disabled = true;

        try {
            const resposta = await fetch(
                '/safe-health/modules/farmacia/api/prescricoes-pendentes.php',
                {
                    method: 'POST',

                    headers: {
                        'Content-Type':
                            'application/json',
                        Accept:
                            'application/json'
                    },

                    body: JSON.stringify({
                        acao:
                            'iniciar_dispensacao',

                        prescricao_id:
                            prescricaoSelecionada
                    })
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
                    'Não foi possível iniciar a dispensação.'
                );
            }

            window.location.href =
                resultado.dados.url;

        } catch (erro) {
            mostrarMensagem(
                erro instanceof Error
                    ? erro.message
                    : 'Erro ao iniciar dispensação.',
                'error'
            );

        } finally {
            botao.disabled = false;
        }
    }

    lista.addEventListener(
        'click',
        function (evento) {
            const botao =
                evento.target.closest(
                    '.open-button'
                );

            if (!botao) {
                return;
            }

            abrirPrescricao(
                Number(botao.dataset.id)
            );
        }
    );

    document.getElementById(
        'botaoPesquisar'
    ).addEventListener(
        'click',
        carregarLista
    );

    document.getElementById(
        'botaoLimpar'
    ).addEventListener(
        'click',
        function () {
            campoPesquisa.value = '';
            detalhesCard.hidden = true;
            prescricaoSelecionada = null;
            carregarLista();
        }
    );

    document.getElementById(
        'botaoAtualizar'
    ).addEventListener(
        'click',
        carregarLista
    );

    document.getElementById(
        'botaoIniciarDispensacao'
    ).addEventListener(
        'click',
        iniciarDispensacao
    );

    campoPesquisa.addEventListener(
        'keydown',
        function (evento) {
            if (evento.key === 'Enter') {
                evento.preventDefault();
                carregarLista();
            }
        }
    );

    carregarLista();

    setInterval(
        carregarLista,
        30000
    );
});
