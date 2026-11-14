document.addEventListener('DOMContentLoaded', function () {
    const form =
        document.getElementById('formInventario');

    const listaContagem =
        document.getElementById('listaContagem');

    const listaInventarios =
        document.getElementById('listaInventarios');

    const campoPesquisa =
        document.getElementById('campoPesquisa');

    const mensagem =
        document.getElementById('mensagemInventario');

    let estoqueAtual = [];

    function escapar(valor) {
        return String(valor ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function formatarData(valor) {
        if (!valor) {
            return '-';
        }

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
            `inventory-message ${tipo}`;
        mensagem.textContent = texto;
    }

    function renderizarContagem(lista) {
        if (
            !Array.isArray(lista) ||
            lista.length === 0
        ) {
            listaContagem.innerHTML = `
                <div class="empty-state">
                    Nenhum item encontrado no estoque.
                </div>
            `;

            return;
        }

        listaContagem.innerHTML = `
            <div class="count-table">

                <div class="count-row count-header">
                    <span>Material</span>
                    <span>Lote</span>
                    <span>Localização</span>
                    <span>Saldo sistema</span>
                    <span>Contagem física</span>
                    <span>Diferença</span>
                    <span>Justificativa</span>
                </div>

                ${lista.map(item => `
                    <div
                        class="count-row inventory-item"
                        data-estoque-id="${Number(item.estoque_id)}"
                        data-saldo="${Number(item.quantidade_atual || 0)}"
                    >

                        <span>
                            <strong>
                                ${escapar(item.material_nome)}
                            </strong>

                            <small>
                                ${escapar(
                                    item.codigo || ''
                                )}
                                ${
                                    item.unidade_medida
                                        ? ' · ' + escapar(item.unidade_medida)
                                        : ''
                                }
                            </small>
                        </span>

                        <span>
                            ${escapar(item.lote)}
                        </span>

                        <span>
                            ${escapar(item.localizacao || '-')}
                        </span>

                        <span>
                            <strong>
                                ${escapar(item.quantidade_atual)}
                            </strong>
                        </span>

                        <span>
                            <input
                                type="number"
                                class="count-input"
                                min="0"
                                step="0.001"
                                value="${escapar(item.quantidade_atual)}"
                            >
                        </span>

                        <span>
                            <strong class="difference-value normal">
                                0.000
                            </strong>
                        </span>

                        <span>
                            <input
                                type="text"
                                class="justification-input"
                                maxlength="255"
                                placeholder="Obrigatória se houver diferença"
                                disabled
                            >
                        </span>

                    </div>
                `).join('')}

            </div>
        `;
    }

    function atualizarDiferenca(linha) {
        const saldo =
            Number(
                linha.dataset.saldo ||
                0
            );

        const input =
            linha.querySelector(
                '.count-input'
            );

        const justificativa =
            linha.querySelector(
                '.justification-input'
            );

        const valor =
            Number(
                String(input.value)
                    .replace(',', '.')
            );

        const diferenca =
            Number.isFinite(valor)
                ? valor - saldo
                : 0;

        const display =
            linha.querySelector(
                '.difference-value'
            );

        display.textContent =
            diferenca.toFixed(3);

        display.classList.remove(
            'positive',
            'negative',
            'normal'
        );

        if (diferenca > 0.000001) {
            display.classList.add(
                'positive'
            );

            justificativa.disabled =
                false;

        } else if (
            diferenca < -0.000001
        ) {
            display.classList.add(
                'negative'
            );

            justificativa.disabled =
                false;

        } else {
            display.classList.add(
                'normal'
            );

            justificativa.disabled =
                true;

            justificativa.value = '';
        }
    }

    function obterItens() {
        return Array.from(
            listaContagem.querySelectorAll(
                '.inventory-item'
            )
        ).map(linha => ({
            estoque_id:
                linha.dataset.estoqueId,

            quantidade_contada:
                linha.querySelector(
                    '.count-input'
                ).value,

            justificativa:
                linha.querySelector(
                    '.justification-input'
                ).value
        }));
    }

    async function carregarEstoque() {
        const q =
            campoPesquisa.value.trim();

        const resposta = await fetch(
            `/safe-health/modules/logistica/api/inventario.php?acao=estoque&q=${encodeURIComponent(q)}`,
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
                'Não foi possível carregar o estoque.'
            );
        }

        estoqueAtual =
            resultado.dados.estoque ?? [];

        renderizarContagem(
            estoqueAtual
        );
    }

    function renderizarHistorico(lista) {
        if (
            !Array.isArray(lista) ||
            lista.length === 0
        ) {
            listaInventarios.innerHTML = `
                <div class="empty-state">
                    Nenhum inventário realizado.
                </div>
            `;

            return;
        }

        listaInventarios.innerHTML = `
            <div class="history-table">

                <div class="history-row history-header">
                    <span>Inventário</span>
                    <span>Status</span>
                    <span>Itens</span>
                    <span>Divergências</span>
                    <span>Usuário</span>
                    <span>Finalizado em</span>
                    <span>Ação</span>
                </div>

                ${lista.map(item => `
                    <div class="history-row">

                        <span>
                            <strong>
                                #${Number(item.id)}
                            </strong>
                        </span>

                        <span>
                            ${escapar(
                                statusLegivel(item.status)
                            )}
                        </span>

                        <span>
                            ${Number(item.total_itens || 0)}
                        </span>

                        <span>
                            ${Number(item.itens_divergentes || 0)}
                        </span>

                        <span>
                            ${escapar(
                                item.usuario_nome ||
                                'Não identificado'
                            )}
                        </span>

                        <span>
                            ${escapar(
                                formatarData(
                                    item.finalizado_em ||
                                    item.criado_em
                                )
                            )}
                        </span>

                        <span>
                            <button
                                type="button"
                                class="open-history-button"
                                data-id="${Number(item.id)}"
                            >
                                <i class="fa-solid fa-eye"></i>
                                Ver
                            </button>
                        </span>

                    </div>
                `).join('')}

            </div>
        `;
    }

    async function carregarHistorico() {
        const resposta = await fetch(
            '/safe-health/modules/logistica/api/inventario.php?acao=historico',
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
            resultado.dados.inventarios ??
            []
        );
    }

    async function abrirDetalhes(id) {
        const resposta = await fetch(
            `/safe-health/modules/logistica/api/inventario.php?acao=detalhes&id=${encodeURIComponent(id)}`,
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
                'Não foi possível abrir o inventário.'
            );
        }

        const inv =
            resultado.dados.inventario;

        const itens =
            resultado.dados.itens ?? [];

        const linhas =
            itens.map(item => {
                const diferenca =
                    Number(item.diferenca || 0);

                return `
                    ${item.material_nome}
                    | lote ${item.lote}
                    | sistema ${item.quantidade_sistema}
                    | contado ${item.quantidade_contada}
                    | diferença ${diferenca.toFixed(3)}
                    ${item.justificativa ? '| ' + item.justificativa : ''}
                `;
            }).join('\n');

        alert(
            `Inventário #${inv.id}\n` +
            `Status: ${statusLegivel(inv.status)}\n` +
            `Usuário: ${inv.usuario_nome || 'Não identificado'}\n` +
            `Finalizado: ${formatarData(inv.finalizado_em || inv.criado_em)}\n\n` +
            linhas
        );
    }

    function iniciarInventario() {
        mensagem.hidden = true;
        form.hidden = false;

        carregarEstoque()
            .then(() => {
                form.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            })
            .catch(
                erro => {
                    alert(
                        erro instanceof Error
                            ? erro.message
                            : 'Erro ao carregar estoque.'
                    );
                }
            );
    }

    form.addEventListener(
        'submit',
        async function (evento) {
            evento.preventDefault();

            const dados = {
                observacoes:
                    document.getElementById(
                        'observacoes'
                    ).value,

                itens:
                    obterItens()
            };

            try {
                const resposta = await fetch(
                    '/safe-health/modules/logistica/api/inventario.php',
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
                        'Não foi possível finalizar o inventário.'
                    );
                }

                mostrarMensagem(
                    resultado.mensagem
                );

                await carregarHistorico();
                await carregarEstoque();

                setTimeout(
                    () => {
                        form.hidden = true;
                        form.reset();
                    },
                    900
                );

            } catch (erro) {
                mostrarMensagem(
                    erro instanceof Error
                        ? erro.message
                        : 'Erro ao finalizar inventário.',
                    'error'
                );
            }
        }
    );

    listaContagem.addEventListener(
        'input',
        function (evento) {
            if (
                evento.target.matches(
                    '.count-input'
                )
            ) {
                atualizarDiferenca(
                    evento.target.closest(
                        '.inventory-item'
                    )
                );
            }
        }
    );

    listaInventarios.addEventListener(
        'click',
        function (evento) {
            const botao =
                evento.target.closest(
                    '.open-history-button'
                );

            if (!botao) {
                return;
            }

            abrirDetalhes(
                Number(botao.dataset.id)
            ).catch(
                erro => {
                    alert(
                        erro instanceof Error
                            ? erro.message
                            : 'Erro ao abrir inventário.'
                    );
                }
            );
        }
    );

    document.getElementById(
        'botaoNovoInventario'
    ).addEventListener(
        'click',
        iniciarInventario
    );

    document.getElementById(
        'botaoCancelarInventario'
    ).addEventListener(
        'click',
        function () {
            form.hidden = true;
            form.reset();
        }
    );

    document.getElementById(
        'botaoPesquisar'
    ).addEventListener(
        'click',
        function () {
            if (!form.hidden) {
                carregarEstoque().catch(
                    erro => alert(erro.message)
                );
            }
        }
    );

    document.getElementById(
        'botaoLimpar'
    ).addEventListener(
        'click',
        function () {
            campoPesquisa.value = '';

            if (!form.hidden) {
                carregarEstoque().catch(
                    erro => alert(erro.message)
                );
            }
        }
    );

    campoPesquisa.addEventListener(
        'keydown',
        function (evento) {
            if (
                evento.key === 'Enter' &&
                !form.hidden
            ) {
                evento.preventDefault();

                carregarEstoque().catch(
                    erro => alert(erro.message)
                );
            }
        }
    );

    carregarHistorico().catch(
        erro => {
            alert(
                erro instanceof Error
                    ? erro.message
                    : 'Erro ao carregar histórico.'
            );
        }
    );
});
