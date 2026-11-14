document.addEventListener('DOMContentLoaded', function () {
    const campoPesquisa =
        document.getElementById('campoPesquisa');

    const listaEstoque =
        document.getElementById('listaEstoque');

    const listaMovimentacoes =
        document.getElementById('listaMovimentacoes');

    const form =
        document.getElementById('formMovimentacao');

    const medicamentoId =
        document.getElementById('medicamentoId');

    const mensagem =
        document.getElementById('mensagemEstoque');

    function escapar(valor) {
        return String(valor ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
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

    function formatarValidade(valor) {
        if (!valor) {
            return '-';
        }

        const partes =
            String(valor).split('-');

        if (partes.length !== 3) {
            return valor;
        }

        return `${partes[2]}/${partes[1]}/${partes[0]}`;
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
            `stock-message ${tipo}`;
        mensagem.textContent = texto;
    }

    function renderizarEstoque(lista) {
        if (
            !Array.isArray(lista) ||
            lista.length === 0
        ) {
            listaEstoque.innerHTML = `
                <div class="empty-state">
                    Nenhum item encontrado no estoque.
                </div>
            `;

            return;
        }

        listaEstoque.innerHTML = `
            <div class="stock-table">

                <div class="stock-row stock-header">
                    <span>Medicamento</span>
                    <span>Lote</span>
                    <span>Validade</span>
                    <span>Saldo</span>
                    <span>Mínimo</span>
                    <span>Localização</span>
                    <span>Situação</span>
                    <span>Ação</span>
                </div>

                ${lista.map(item => `
                    <div class="stock-row">
                        <span>
                            <strong>
                                ${escapar(item.medicamento_nome)}
                            </strong>

                            <small>
                                ${escapar(
                                    [
                                        item.concentracao,
                                        item.apresentacao
                                    ].filter(Boolean).join(' · ')
                                )}
                            </small>
                        </span>

                        <span>
                            ${escapar(item.lote)}
                        </span>

                        <span>
                            ${escapar(
                                formatarValidade(
                                    item.validade
                                )
                            )}
                        </span>

                        <span>
                            <strong>
                                ${escapar(item.quantidade_atual)}
                            </strong>
                        </span>

                        <span>
                            ${escapar(item.quantidade_minima)}
                        </span>

                        <span>
                            ${escapar(item.localizacao || '-')}
                        </span>

                        <span>
                            <span class="situation-badge ${String(item.situacao).toLowerCase()}">
                                ${escapar(
                                    statusLegivel(
                                        item.situacao
                                    )
                                )}
                            </span>
                        </span>

                        <span>
                            <button
                                type="button"
                                class="move-button"
                                data-id="${Number(item.id)}"
                                data-medicamento="${Number(item.medicamento_id)}"
                                data-lote="${escapar(item.lote)}"
                                data-validade="${escapar(item.validade || '')}"
                                data-minimo="${escapar(item.quantidade_minima)}"
                                data-localizacao="${escapar(item.localizacao || '')}"
                            >
                                <i class="fa-solid fa-arrow-right-arrow-left"></i>
                                Movimentar
                            </button>
                        </span>
                    </div>
                `).join('')}

            </div>
        `;
    }

    function renderizarMovimentacoes(lista) {
        if (
            !Array.isArray(lista) ||
            lista.length === 0
        ) {
            listaMovimentacoes.innerHTML = `
                <div class="empty-state">
                    Nenhuma movimentação registrada.
                </div>
            `;

            return;
        }

        listaMovimentacoes.innerHTML =
            lista.map(item => `
                <article class="movement-item">
                    <div class="movement-top">
                        <div>
                            <strong>
                                ${escapar(item.medicamento_nome)}
                            </strong>

                            <span>
                                Lote ${escapar(item.lote)}
                            </span>
                        </div>

                        <span class="movement-type">
                            ${escapar(
                                statusLegivel(
                                    item.tipo
                                )
                            )}
                        </span>
                    </div>

                    <div class="movement-meta">
                        <span>
                            <strong>Quantidade:</strong>
                            ${escapar(item.quantidade)}
                        </span>

                        <span>
                            <strong>Saldo anterior:</strong>
                            ${escapar(item.saldo_anterior)}
                        </span>

                        <span>
                            <strong>Saldo posterior:</strong>
                            ${escapar(item.saldo_posterior)}
                        </span>

                        <span>
                            <strong>Data:</strong>
                            ${escapar(formatarData(item.criado_em))}
                        </span>

                        <span>
                            <strong>Usuário:</strong>
                            ${escapar(
                                item.usuario_nome ||
                                'Não identificado'
                            )}
                        </span>
                    </div>

                    ${
                        item.referencia
                            ? `<div class="movement-note"><strong>Referência:</strong> ${escapar(item.referencia)}</div>`
                            : ''
                    }

                    ${
                        item.observacoes
                            ? `<div class="movement-note">${escapar(item.observacoes)}</div>`
                            : ''
                    }
                </article>
            `).join('');
    }

    async function carregarMedicamentos() {
        const resposta = await fetch(
            '/safe-health/modules/farmacia/api/estoque.php?acao=medicamentos',
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
                'Erro ao carregar medicamentos.'
            );
        }

        medicamentoId.innerHTML = `
            <option value="">Selecione</option>

            ${(resultado.dados.medicamentos ?? [])
                .map(item => `
                    <option value="${Number(item.id)}">
                        ${escapar(item.nome)}
                        ${
                            item.concentracao
                                ? ' — ' + escapar(item.concentracao)
                                : ''
                        }
                    </option>
                `).join('')}
        `;
    }

    async function carregarEstoque() {
        const q =
            campoPesquisa.value.trim();

        try {
            const resposta = await fetch(
                `/safe-health/modules/farmacia/api/estoque.php?acao=listar&q=${encodeURIComponent(q)}`,
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
                    'Erro ao carregar estoque.'
                );
            }

            renderizarEstoque(
                resultado.dados.estoque ?? []
            );

        } catch (erro) {
            listaEstoque.innerHTML = `
                <div class="empty-state">
                    ${escapar(
                        erro instanceof Error
                            ? erro.message
                            : 'Erro ao carregar estoque.'
                    )}
                </div>
            `;
        }
    }

    async function carregarResumo() {
        const resposta = await fetch(
            '/safe-health/modules/farmacia/api/estoque.php?acao=resumo',
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
            return;
        }

        const r =
            resultado.dados.resumo || {};

        document.getElementById(
            'resumoItens'
        ).textContent =
            Number(r.itens || 0);

        document.getElementById(
            'resumoBaixo'
        ).textContent =
            Number(r.baixo || 0);

        document.getElementById(
            'resumoVencimento'
        ).textContent =
            Number(r.vencimento || 0);

        document.getElementById(
            'resumoSemSaldo'
        ).textContent =
            Number(r.sem_saldo || 0);
    }

    async function carregarMovimentacoes() {
        const resposta = await fetch(
            '/safe-health/modules/farmacia/api/estoque.php?acao=movimentacoes',
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
            return;
        }

        renderizarMovimentacoes(
            resultado.dados.movimentacoes ?? []
        );
    }

    async function atualizarTudo() {
        await Promise.allSettled([
            carregarEstoque(),
            carregarResumo(),
            carregarMovimentacoes()
        ]);
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
                    '/safe-health/modules/farmacia/api/estoque.php',
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
                        'Não foi possível registrar a movimentação.'
                    );
                }

                mostrarMensagem(
                    resultado.mensagem
                );

                form.reset();

                document.getElementById(
                    'quantidade'
                ).value = '1';

                document.getElementById(
                    'quantidadeMinima'
                ).value = '0';

                await atualizarTudo();

            } catch (erro) {
                mostrarMensagem(
                    erro instanceof Error
                        ? erro.message
                        : 'Erro ao registrar movimentação.',
                    'error'
                );
            }
        }
    );

    listaEstoque.addEventListener(
        'click',
        function (evento) {
            const botao =
                evento.target.closest(
                    '.move-button'
                );

            if (!botao) {
                return;
            }

            document.getElementById(
                'estoqueId'
            ).value =
                botao.dataset.id || '';

            medicamentoId.value =
                botao.dataset.medicamento || '';

            document.getElementById(
                'lote'
            ).value =
                botao.dataset.lote || '';

            document.getElementById(
                'validade'
            ).value =
                botao.dataset.validade || '';

            document.getElementById(
                'quantidadeMinima'
            ).value =
                botao.dataset.minimo || '0';

            document.getElementById(
                'localizacao'
            ).value =
                botao.dataset.localizacao || '';

            form.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    );

    document.getElementById(
        'botaoPesquisar'
    ).addEventListener(
        'click',
        carregarEstoque
    );

    document.getElementById(
        'botaoLimpar'
    ).addEventListener(
        'click',
        function () {
            campoPesquisa.value = '';
            carregarEstoque();
        }
    );

    document.getElementById(
        'botaoAtualizar'
    ).addEventListener(
        'click',
        atualizarTudo
    );

    campoPesquisa.addEventListener(
        'keydown',
        function (evento) {
            if (evento.key === 'Enter') {
                evento.preventDefault();
                carregarEstoque();
            }
        }
    );

    carregarMedicamentos().catch(
        erro => {
            alert(
                erro instanceof Error
                    ? erro.message
                    : 'Erro ao carregar medicamentos.'
            );
        }
    );

    atualizarTudo();
});
