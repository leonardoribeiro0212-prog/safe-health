document.addEventListener('DOMContentLoaded', function () {
    const campoPesquisa =
        document.getElementById('campoPesquisa');

    const listaEstoque =
        document.getElementById('listaEstoque');

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

    function renderizarEstoque(lista) {
        if (
            !Array.isArray(lista) ||
            lista.length === 0
        ) {
            listaEstoque.innerHTML = `
                <div class="empty-state">
                    Nenhum material encontrado no Almoxarifado.
                </div>
            `;

            return;
        }

        listaEstoque.innerHTML = `
            <div class="warehouse-table">

                <div class="warehouse-row warehouse-header">
                    <span>Material</span>
                    <span>Código</span>
                    <span>Categoria</span>
                    <span>Lote</span>
                    <span>Validade</span>
                    <span>Saldo</span>
                    <span>Mínimo</span>
                    <span>Localização</span>
                    <span>Situação</span>
                </div>

                ${lista.map(item => `
                    <div class="warehouse-row">

                        <span>
                            <strong>
                                ${escapar(item.material_nome)}
                            </strong>

                            <small>
                                ${escapar(item.unidade_medida || '')}
                            </small>
                        </span>

                        <span>
                            ${escapar(item.codigo || '-')}
                        </span>

                        <span>
                            ${escapar(item.categoria || '-')}
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

                    </div>
                `).join('')}

            </div>
        `;
    }

    async function carregarEstoque() {
        const q =
            campoPesquisa.value.trim();

        listaEstoque.innerHTML = `
            <div class="empty-state">
                Carregando estoque...
            </div>
        `;

        try {
            const resposta = await fetch(
                `/safe-health/modules/logistica/api/almoxarifado.php?acao=listar&q=${encodeURIComponent(q)}`,
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

            renderizarEstoque(
                resultado.dados.estoque ?? []
            );

        } catch (erro) {
            listaEstoque.innerHTML = `
                <div class="empty-state error">
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
        try {
            const resposta = await fetch(
                '/safe-health/modules/logistica/api/almoxarifado.php?acao=resumo',
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

        } catch (erro) {
            console.error(erro);
        }
    }

    async function atualizarTudo() {
        await Promise.allSettled([
            carregarEstoque(),
            carregarResumo()
        ]);
    }

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

    atualizarTudo();
});
