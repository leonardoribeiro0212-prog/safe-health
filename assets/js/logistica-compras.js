document.addEventListener('DOMContentLoaded', function () {
    const listaCompras = document.getElementById('listaCompras');
    const form = document.getElementById('formCompra');
    const compraId = document.getElementById('compraId');
    const campoPesquisa = document.getElementById('campoPesquisa');
    const listaItens = document.getElementById('listaItensCompra');
    const mensagem = document.getElementById('mensagemCompra');

    let materiais = [];

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

        const data = new Date(String(valor).replace(' ', 'T'));

        return Number.isNaN(data.getTime())
            ? valor
            : data.toLocaleString('pt-BR');
    }

    function statusLegivel(valor) {
        return String(valor ?? '')
            .replaceAll('_', ' ')
            .toLowerCase()
            .replace(/\b\w/g, letra => letra.toUpperCase());
    }

    function moeda(valor) {
        return Number(valor || 0).toLocaleString('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        });
    }

    async function lerJson(resposta) {
        const texto = await resposta.text();

        try {
            return JSON.parse(texto);
        } catch {
            console.error('Resposta inválida:', texto);
            throw new Error('O servidor retornou uma resposta inválida.');
        }
    }

    function mostrarMensagem(texto, tipo = 'success') {
        mensagem.hidden = false;
        mensagem.className = `purchase-message ${tipo}`;
        mensagem.textContent = texto;
    }

    function materialOptions(selecionado = '') {
        return `
            <option value="">Selecione</option>
            ${materiais.map(item => `
                <option
                    value="${Number(item.id)}"
                    ${String(item.id) === String(selecionado) ? 'selected' : ''}
                >
                    ${escapar(item.nome)}
                    ${item.codigo ? ' — ' + escapar(item.codigo) : ''}
                </option>
            `).join('')}
        `;
    }

    function adicionarItem(item = {}) {
        const linha = document.createElement('div');
        linha.className = 'purchase-item';

        linha.innerHTML = `
            <div class="form-grid">
                <div class="field col-5">
                    <label>Material <span>*</span></label>
                    <select class="item-material" required>
                        ${materialOptions(item.material_id || '')}
                    </select>
                </div>

                <div class="field col-2">
                    <label>Quantidade <span>*</span></label>
                    <input
                        type="number"
                        class="item-quantidade"
                        min="0.001"
                        step="0.001"
                        value="${escapar(item.quantidade || '1')}"
                        required
                    >
                </div>

                <div class="field col-2">
                    <label>Valor unitário</label>
                    <input
                        type="number"
                        class="item-valor"
                        min="0"
                        step="0.01"
                        value="${escapar(item.valor_unitario || '0.00')}"
                    >
                </div>

                <div class="field col-2">
                    <label>Observação</label>
                    <input
                        type="text"
                        class="item-observacao"
                        maxlength="255"
                        value="${escapar(item.observacoes || '')}"
                    >
                </div>

                <div class="field col-1 action-field">
                    <label>&nbsp;</label>
                    <button
                        type="button"
                        class="remove-item-button"
                        title="Remover item"
                    >
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </div>
        `;

        listaItens.appendChild(linha);
    }

    function obterItens() {
        return Array.from(
            listaItens.querySelectorAll('.purchase-item')
        ).map(linha => ({
            material_id:
                linha.querySelector('.item-material').value,
            quantidade:
                linha.querySelector('.item-quantidade').value,
            valor_unitario:
                linha.querySelector('.item-valor').value,
            observacoes:
                linha.querySelector('.item-observacao').value
        }));
    }

    function novaCompra() {
        form.reset();
        compraId.value = '';
        document.getElementById('tituloFormulario').textContent = 'Nova compra';
        listaItens.innerHTML = '';
        adicionarItem();
        mensagem.hidden = true;
        form.hidden = false;

        form.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }

    function renderizarCompras(lista) {
        if (!Array.isArray(lista) || lista.length === 0) {
            listaCompras.innerHTML = `
                <div class="empty-state">
                    Nenhuma compra encontrada.
                </div>
            `;
            return;
        }

        listaCompras.innerHTML = `
            <div class="purchase-table">
                <div class="purchase-row purchase-header">
                    <span>Compra</span>
                    <span>Fornecedor</span>
                    <span>Status</span>
                    <span>Prioridade</span>
                    <span>Itens</span>
                    <span>Valor</span>
                    <span>Data</span>
                    <span>Ação</span>
                </div>

                ${lista.map(item => `
                    <div class="purchase-row">
                        <span><strong>#${Number(item.id)}</strong></span>
                        <span>${escapar(item.fornecedor || '-')}</span>
                        <span>
                            <span class="status-badge">
                                ${escapar(statusLegivel(item.status))}
                            </span>
                        </span>
                        <span>
                            <span class="priority-badge ${item.prioridade === 'URGENTE' ? 'urgent' : ''}">
                                ${escapar(statusLegivel(item.prioridade))}
                            </span>
                        </span>
                        <span>${Number(item.total_itens || 0)}</span>
                        <span>${escapar(moeda(item.valor_total))}</span>
                        <span>${escapar(formatarData(item.criado_em))}</span>
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

    async function carregarCompras() {
        const q = campoPesquisa.value.trim();

        const resposta = await fetch(
            `/safe-health/modules/logistica/api/compras.php?acao=listar&q=${encodeURIComponent(q)}`,
            {
                headers: { Accept: 'application/json' },
                cache: 'no-store'
            }
        );

        const resultado = await lerJson(resposta);

        if (!resposta.ok || !resultado.sucesso) {
            throw new Error(resultado.mensagem || 'Erro ao carregar compras.');
        }

        renderizarCompras(resultado.dados.compras ?? []);
    }

    async function carregarMateriais() {
        const resposta = await fetch(
            '/safe-health/modules/logistica/api/compras.php?acao=materiais',
            {
                headers: { Accept: 'application/json' },
                cache: 'no-store'
            }
        );

        const resultado = await lerJson(resposta);

        if (!resposta.ok || !resultado.sucesso) {
            throw new Error(resultado.mensagem || 'Erro ao carregar materiais.');
        }

        materiais = resultado.dados.materiais ?? [];
    }

    async function abrirCompra(id) {
        const resposta = await fetch(
            `/safe-health/modules/logistica/api/compras.php?acao=detalhes&id=${encodeURIComponent(id)}`,
            {
                headers: { Accept: 'application/json' },
                cache: 'no-store'
            }
        );

        const resultado = await lerJson(resposta);

        if (!resposta.ok || !resultado.sucesso) {
            throw new Error(resultado.mensagem || 'Erro ao abrir compra.');
        }

        const compra = resultado.dados.compra;

        compraId.value = compra.id;
        document.getElementById('fornecedor').value = compra.fornecedor || '';
        document.getElementById('numeroDocumento').value = compra.numero_documento || '';
        document.getElementById('prioridade').value = compra.prioridade || 'ROTINA';
        document.getElementById('status').value = compra.status || 'RASCUNHO';
        document.getElementById('observacoes').value = compra.observacoes || '';
        document.getElementById('tituloFormulario').textContent = `Compra #${compra.id}`;

        listaItens.innerHTML = '';

        (resultado.dados.itens ?? []).forEach(adicionarItem);

        if (listaItens.children.length === 0) {
            adicionarItem();
        }

        mensagem.hidden = true;
        form.hidden = false;

        form.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }

    form.addEventListener('submit', async function (evento) {
        evento.preventDefault();

        const dados = {
            compra_id: Number(compraId.value || 0),
            fornecedor: document.getElementById('fornecedor').value,
            numero_documento: document.getElementById('numeroDocumento').value,
            prioridade: document.getElementById('prioridade').value,
            status: document.getElementById('status').value,
            observacoes: document.getElementById('observacoes').value,
            itens: obterItens()
        };

        try {
            const resposta = await fetch(
                '/safe-health/modules/logistica/api/compras.php',
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json'
                    },
                    body: JSON.stringify(dados)
                }
            );

            const resultado = await lerJson(resposta);

            if (!resposta.ok || !resultado.sucesso) {
                throw new Error(resultado.mensagem || 'Erro ao salvar compra.');
            }

            compraId.value = resultado.dados.compra_id;
            document.getElementById('tituloFormulario').textContent =
                `Compra #${resultado.dados.compra_id}`;

            mostrarMensagem(resultado.mensagem);

            await carregarCompras();

        } catch (erro) {
            mostrarMensagem(
                erro instanceof Error
                    ? erro.message
                    : 'Erro ao salvar compra.',
                'error'
            );
        }
    });

    listaItens.addEventListener('click', function (evento) {
        const botao = evento.target.closest('.remove-item-button');

        if (!botao) return;

        const linha = botao.closest('.purchase-item');

        if (listaItens.querySelectorAll('.purchase-item').length === 1) {
            alert('A compra precisa ter pelo menos um item.');
            return;
        }

        linha.remove();
    });

    listaCompras.addEventListener('click', function (evento) {
        const botao = evento.target.closest('.open-button');

        if (!botao) return;

        abrirCompra(Number(botao.dataset.id)).catch(erro => {
            alert(erro.message);
        });
    });

    document.getElementById('botaoAdicionarItem')
        .addEventListener('click', () => adicionarItem());

    document.getElementById('botaoNovaCompra')
        .addEventListener('click', novaCompra);

    document.getElementById('botaoCancelarEdicao')
        .addEventListener('click', function () {
            form.hidden = true;
        });

    document.getElementById('botaoPesquisar')
        .addEventListener('click', function () {
            carregarCompras().catch(erro => alert(erro.message));
        });

    document.getElementById('botaoLimparPesquisa')
        .addEventListener('click', function () {
            campoPesquisa.value = '';
            carregarCompras().catch(erro => alert(erro.message));
        });

    campoPesquisa.addEventListener('keydown', function (evento) {
        if (evento.key === 'Enter') {
            evento.preventDefault();
            carregarCompras().catch(erro => alert(erro.message));
        }
    });

    Promise.all([
        carregarMateriais(),
        carregarCompras()
    ]).catch(erro => {
        alert(erro instanceof Error ? erro.message : 'Erro ao carregar a tela.');
    });
});
