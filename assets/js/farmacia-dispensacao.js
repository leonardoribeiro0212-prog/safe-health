document.addEventListener('DOMContentLoaded', function () {
    const campoPesquisa =
        document.getElementById('campoPesquisa');

    const dadosCard =
        document.getElementById('dadosPrescricaoCard');

    const itensCard =
        document.getElementById('itensCard');

    const listaItens =
        document.getElementById('listaItensDispensacao');

    const mensagem =
        document.getElementById('mensagemDispensacao');

    let prescricaoAtual = null;

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

    function renderizarItens(itens) {
        if (
            !Array.isArray(itens) ||
            itens.length === 0
        ) {
            listaItens.innerHTML = `
                <div class="empty-state">
                    Nenhum medicamento encontrado nesta prescrição.
                </div>
            `;

            return;
        }

        listaItens.innerHTML =
            itens.map(item => {
                const quantidade =
                    Number(item.quantidade || 0);

                const jaDispensada =
                    Number(
                        item.quantidade_ja_dispensada ||
                        0
                    );

                const restante =
                    Math.max(
                        0,
                        quantidade -
                        jaDispensada
                    );

                const concluido =
                    item.status_farmacia ===
                    'DISPENSADO' ||
                    restante <= 0.000001;

                return `
                    <article
                        class="dispense-item ${concluido ? 'completed' : ''}"
                        data-item-id="${Number(item.prescricao_item_id)}"
                    >
                        <div class="dispense-header">
                            <div>
                                <strong>
                                    ${escapar(item.medicamento_nome)}
                                </strong>

                                <span>
                                    ${escapar(
                                        [
                                            item.concentracao,
                                            item.apresentacao
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')
                                    )}
                                </span>
                            </div>

                            <span class="status-badge">
                                ${escapar(
                                    statusLegivel(
                                        item.status_farmacia
                                    )
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
                                <strong>Solicitado:</strong>
                                ${escapar(item.quantidade)}
                            </span>

                            <span>
                                <strong>Já dispensado:</strong>
                                ${escapar(
                                    jaDispensada.toFixed(3)
                                )}
                            </span>

                            <span>
                                <strong>Restante:</strong>
                                ${escapar(
                                    restante.toFixed(3)
                                )}
                            </span>
                        </div>

                        ${item.instrucoes
                            ? `
                                <div class="item-note">
                                    ${escapar(item.instrucoes)}
                                </div>
                            `
                            : ''}

                        ${
                            concluido
                                ? `
                                    <div class="completed-message">
                                        <i class="fa-solid fa-circle-check"></i>
                                        Item totalmente dispensado.
                                    </div>
                                `
                                : `
                                    <form class="dispense-form">
                                        <input
                                            type="hidden"
                                            name="prescricao_item_id"
                                            value="${Number(item.prescricao_item_id)}"
                                        >

                                        <div class="form-grid">

                                            <div class="field col-3">
                                                <label>
                                                    Quantidade a dispensar
                                                    <span>*</span>
                                                </label>

                                                <input
                                                    type="number"
                                                    name="quantidade_dispensada"
                                                    min="0.001"
                                                    max="${restante}"
                                                    step="0.001"
                                                    value="${restante}"
                                                    required
                                                >
                                            </div>

                                            <div class="field col-3">
                                                <label>Lote</label>

                                                <input
                                                    type="text"
                                                    name="lote"
                                                    maxlength="80"
                                                >
                                            </div>

                                            <div class="field col-3">
                                                <label>Validade</label>

                                                <input
                                                    type="date"
                                                    name="validade"
                                                >
                                            </div>

                                            <div class="field col-12">
                                                <label>Observações</label>

                                                <textarea
                                                    name="observacoes"
                                                    rows="3"
                                                    placeholder="Observações da dispensação..."
                                                ></textarea>
                                            </div>

                                        </div>

                                        <div class="item-actions">
                                            <button
                                                type="submit"
                                                class="primary-button"
                                            >
                                                <i class="fa-solid fa-box-open"></i>
                                                Registrar dispensação
                                            </button>
                                        </div>
                                    </form>
                                `
                        }

                        <button
                            type="button"
                            class="history-button"
                            data-history-id="${Number(item.prescricao_item_id)}"
                        >
                            <i class="fa-solid fa-clock-rotate-left"></i>
                            Ver histórico deste item
                        </button>

                        <div
                            class="item-history"
                            id="historico-item-${Number(item.prescricao_item_id)}"
                            hidden
                        ></div>

                    </article>
                `;
            }).join('');
    }

    async function buscarPrescricao() {
        const termo =
            campoPesquisa.value.trim();

        if (!termo) {
            alert(
                'Informe o número da prescrição ou atendimento.'
            );

            return;
        }

        ocultarMensagem();

        try {
            const resposta = await fetch(
                `/safe-health/modules/farmacia/api/dispensacao.php?acao=buscar&termo=${encodeURIComponent(termo)}`,
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
                    'Prescrição não encontrada.'
                );
            }

            const p =
                resultado.dados.prescricao;

            prescricaoAtual =
                Number(p.id);

            campoPesquisa.value =
                String(p.id);

            document.getElementById(
                'tituloPrescricao'
            ).textContent =
                `Prescrição #${p.id}`;

            document.getElementById(
                'subtituloPrescricao'
            ).textContent =
                'Dispensação vinculada ao atendimento do paciente.';

            document.getElementById(
                'statusPrescricao'
            ).textContent =
                statusLegivel(p.status);

            document.getElementById(
                'pacienteNome'
            ).textContent =
                p.paciente_nome || '-';

            document.getElementById(
                'atendimentoNumero'
            ).textContent =
                p.numero_atendimento || '-';

            document.getElementById(
                'atendimentoConvenio'
            ).textContent =
                p.convenio || 'Não informado';

            document.getElementById(
                'dataPrescricao'
            ).textContent =
                formatarData(
                    p.data_prescricao
                );

            renderizarItens(
                resultado.dados.itens ?? []
            );

            dadosCard.hidden = false;
            itensCard.hidden = false;

            dadosCard.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });

        } catch (erro) {
            dadosCard.hidden = true;
            itensCard.hidden = true;

            mostrarMensagem(
                erro instanceof Error
                    ? erro.message
                    : 'Erro ao buscar prescrição.',
                'error'
            );
        }
    }

    async function salvarDispensacao(form) {
        if (!prescricaoAtual) {
            return;
        }

        const botao =
            form.querySelector(
                'button[type="submit"]'
            );

        botao.disabled = true;

        const dados =
            Object.fromEntries(
                new FormData(form).entries()
            );

        dados.prescricao_id =
            prescricaoAtual;

        try {
            const resposta = await fetch(
                '/safe-health/modules/farmacia/api/dispensacao.php',
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
                    'Não foi possível registrar.'
                );
            }

            mostrarMensagem(
                resultado.mensagem
            );

            await buscarPrescricao();

        } catch (erro) {
            mostrarMensagem(
                erro instanceof Error
                    ? erro.message
                    : 'Erro ao registrar dispensação.',
                'error'
            );

        } finally {
            botao.disabled = false;
        }
    }

    async function carregarHistoricoItem(
        itemId,
        container
    ) {
        container.hidden = false;
        container.innerHTML =
            '<div class="empty-state compact">Carregando histórico...</div>';

        try {
            const resposta = await fetch(
                `/safe-health/modules/farmacia/api/dispensacao.php?acao=historico_item&item_id=${encodeURIComponent(itemId)}`,
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
                    'Erro ao carregar histórico.'
                );
            }

            const registros =
                resultado.dados.dispensacoes ??
                [];

            if (registros.length === 0) {
                container.innerHTML =
                    '<div class="empty-state compact">Nenhuma dispensação registrada.</div>';

                return;
            }

            container.innerHTML =
                registros.map(item => `
                    <div class="history-entry">
                        <div>
                            <strong>
                                ${escapar(item.quantidade_dispensada)}
                            </strong>
                            dispensado(s)
                        </div>

                        <span>
                            ${escapar(formatarData(item.data_dispensacao))}
                            ·
                            ${escapar(item.usuario_nome || 'Profissional não identificado')}
                        </span>

                        ${
                            item.lote
                                ? `<span>Lote: ${escapar(item.lote)}</span>`
                                : ''
                        }

                        ${
                            item.validade
                                ? `<span>Validade: ${escapar(item.validade)}</span>`
                                : ''
                        }

                        ${
                            item.observacoes
                                ? `<div class="history-note">${escapar(item.observacoes)}</div>`
                                : ''
                        }
                    </div>
                `).join('');

        } catch (erro) {
            container.innerHTML =
                `<div class="empty-state compact">${escapar(
                    erro instanceof Error
                        ? erro.message
                        : 'Erro ao carregar histórico.'
                )}</div>`;
        }
    }

    listaItens.addEventListener(
        'submit',
        function (evento) {
            const form =
                evento.target.closest(
                    '.dispense-form'
                );

            if (!form) {
                return;
            }

            evento.preventDefault();

            salvarDispensacao(form);
        }
    );

    listaItens.addEventListener(
        'click',
        function (evento) {
            const botao =
                evento.target.closest(
                    '.history-button'
                );

            if (!botao) {
                return;
            }

            const itemId =
                Number(
                    botao.dataset.historyId
                );

            const container =
                document.getElementById(
                    `historico-item-${itemId}`
                );

            if (!container.hidden) {
                container.hidden = true;
                return;
            }

            carregarHistoricoItem(
                itemId,
                container
            );
        }
    );

    document.getElementById(
        'botaoPesquisar'
    ).addEventListener(
        'click',
        buscarPrescricao
    );

    campoPesquisa.addEventListener(
        'keydown',
        function (evento) {
            if (evento.key === 'Enter') {
                evento.preventDefault();
                buscarPrescricao();
            }
        }
    );

    if (
        Number(
            window.SAFE_HEALTH_PRESCRICAO_INICIAL ||
            0
        ) > 0
    ) {
        buscarPrescricao();
    }
});
