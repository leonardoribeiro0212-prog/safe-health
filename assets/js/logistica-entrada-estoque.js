document.addEventListener('DOMContentLoaded', function () {
    const campoCompra = document.getElementById('campoCompra');
    const listaCompras = document.getElementById('listaCompras');
    const form = document.getElementById('formEntrada');
    const compraId = document.getElementById('compraId');
    const listaItens = document.getElementById('listaItensEntrada');
    const listaEntradas = document.getElementById('listaEntradas');
    const mensagem = document.getElementById('mensagemEntrada');

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
        mensagem.className = `entry-message ${tipo}`;
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
        linha.className = 'entry-item';

        linha.innerHTML = `
            <div class="form-grid">

                <div class="field col-4">
                    <label>Material <span>*</span></label>
                    <select class="item-material" required>
                        ${materialOptions(item.material_id || '')}
                    </select>
                </div>

                <div class="field col-2">
                    <label>Lote <span>*</span></label>
                    <input
                        type="text"
                        class="item-lote"
                        maxlength="80"
                        value="${escapar(item.lote || '')}"
                        required
                    >
                </div>

                <div class="field col-2">
                    <label>Validade</label>
                    <input
                        type="date"
                        class="item-validade"
                        value="${escapar(item.validade || '')}"
                    >
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
                    <label>Estoque mínimo</label>
                    <input
                        type="number"
                        class="item-minimo"
                        min="0"
                        step="0.001"
                        value="${escapar(item.quantidade_minima || '0')}"
                    >
                </div>

                <div class="field col-4">
                    <label>Localização</label>
                    <input
                        type="text"
                        class="item-localizacao"
                        maxlength="120"
                        value="${escapar(item.localizacao || '')}"
                        placeholder="Ex.: Corredor A / Prateleira 03"
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
            listaItens.querySelectorAll('.entry-item')
        ).map(linha => ({
            material_id:
                linha.querySelector('.item-material').value,
            lote:
                linha.querySelector('.item-lote').value,
            validade:
                linha.querySelector('.item-validade').value,
            quantidade:
                linha.querySelector('.item-quantidade').value,
            quantidade_minima:
                linha.querySelector('.item-minimo').value,
            localizacao:
                linha.querySelector('.item-localizacao').value
        }));
    }

    function prepararEntradaAvulsa() {
        form.reset();
        compraId.value = '';
        document.getElementById('tituloEntrada').textContent =
            'Entrada avulsa';
        listaItens.innerHTML = '';
        adicionarItem();
        mensagem.hidden = true;
        form.hidden = false;

        form.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }

    async function carregarMateriais() {
        const resposta = await fetch(
            '/safe-health/modules/logistica/api/entrada-estoque.php?acao=materiais',
            {
                headers: { Accept: 'application/json' },
                cache: 'no-store'
            }
        );

        const resultado = await lerJson(resposta);

        if (!resposta.ok || !resultado.sucesso) {
            throw new Error(
                resultado.mensagem ||
                'Não foi possível carregar os materiais.'
            );
        }

        materiais = resultado.dados.materiais ?? [];
    }

    function renderizarCompras(lista) {
        if (!Array.isArray(lista) || lista.length === 0) {
            listaCompras.innerHTML = `
                <div class="empty-state compact">
                    Nenhuma compra encontrada.
                </div>
            `;
            return;
        }

        listaCompras.innerHTML = lista.map(item => `
            <button
                type="button"
                class="purchase-option"
                data-id="${Number(item.id)}"
            >
                <div>
                    <strong>Compra #${Number(item.id)}</strong>
                    <span>
                        ${escapar(item.fornecedor || 'Fornecedor não informado')}
                        · ${Number(item.total_itens || 0)} item(ns)
                    </span>
                </div>

                <div class="purchase-option-side">
                    <span class="status-badge">
                        ${escapar(statusLegivel(item.status))}
                    </span>
                    <i class="fa-solid fa-chevron-right"></i>
                </div>
            </button>
        `).join('');
    }

    async function buscarCompras() {
        const q = campoCompra.value.trim();

        const resposta = await fetch(
            `/safe-health/modules/logistica/api/entrada-estoque.php?acao=compras&q=${encodeURIComponent(q)}`,
            {
                headers: { Accept: 'application/json' },
                cache: 'no-store'
            }
        );

        const resultado = await lerJson(resposta);

        if (!resposta.ok || !resultado.sucesso) {
            throw new Error(
                resultado.mensagem ||
                'Não foi possível carregar as compras.'
            );
        }

        renderizarCompras(
            resultado.dados.compras ?? []
        );
    }

    async function carregarCompra(id) {
        const resposta = await fetch(
            `/safe-health/modules/logistica/api/entrada-estoque.php?acao=compra_detalhes&id=${encodeURIComponent(id)}`,
            {
                headers: { Accept: 'application/json' },
                cache: 'no-store'
            }
        );

        const resultado = await lerJson(resposta);

        if (!resposta.ok || !resultado.sucesso) {
            throw new Error(
                resultado.mensagem ||
                'Não foi possível abrir a compra.'
            );
        }

        const compra = resultado.dados.compra;

        form.reset();
        compraId.value = compra.id;

        document.getElementById('tituloEntrada').textContent =
            `Receber compra #${compra.id}`;

        document.getElementById('fornecedor').value =
            compra.fornecedor || '';

        document.getElementById('numeroDocumento').value =
            compra.numero_documento || '';

        listaItens.innerHTML = '';

        (resultado.dados.itens ?? []).forEach(item => {
            adicionarItem({
                material_id: item.material_id,
                quantidade: item.quantidade,
                lote: '',
                validade: '',
                quantidade_minima: '0',
                localizacao: ''
            });
        });

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

    function renderizarHistorico(lista) {
        if (!Array.isArray(lista) || lista.length === 0) {
            listaEntradas.innerHTML = `
                <div class="empty-state">
                    Nenhuma entrada registrada.
                </div>
            `;
            return;
        }

        listaEntradas.innerHTML = `
            <div class="history-table">
                <div class="history-row history-header">
                    <span>Entrada</span>
                    <span>Compra</span>
                    <span>Fornecedor</span>
                    <span>Documento</span>
                    <span>Itens</span>
                    <span>Quantidade</span>
                    <span>Usuário</span>
                    <span>Data</span>
                </div>

                ${lista.map(item => `
                    <div class="history-row">
                        <span><strong>#${Number(item.id)}</strong></span>
                        <span>${item.compra_id ? '#' + Number(item.compra_id) : 'Avulsa'}</span>
                        <span>${escapar(item.fornecedor || '-')}</span>
                        <span>${escapar(item.numero_documento || '-')}</span>
                        <span>${Number(item.total_itens || 0)}</span>
                        <span>${escapar(item.total_quantidade || '0')}</span>
                        <span>${escapar(item.usuario_nome || 'Não identificado')}</span>
                        <span>${escapar(formatarData(item.criado_em))}</span>
                    </div>
                `).join('')}
            </div>
        `;
    }

    async function carregarHistorico() {
        const resposta = await fetch(
            '/safe-health/modules/logistica/api/entrada-estoque.php?acao=historico',
            {
                headers: { Accept: 'application/json' },
                cache: 'no-store'
            }
        );

        const resultado = await lerJson(resposta);

        if (!resposta.ok || !resultado.sucesso) {
            throw new Error(
                resultado.mensagem ||
                'Não foi possível carregar as entradas.'
            );
        }

        renderizarHistorico(
            resultado.dados.entradas ?? []
        );
    }

    form.addEventListener('submit', async function (evento) {
        evento.preventDefault();

        const dados = {
            compra_id: Number(compraId.value || 0),
            fornecedor: document.getElementById('fornecedor').value,
            numero_documento: document.getElementById('numeroDocumento').value,
            observacoes: document.getElementById('observacoes').value,
            itens: obterItens()
        };

        try {
            const resposta = await fetch(
                '/safe-health/modules/logistica/api/entrada-estoque.php',
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
                throw new Error(
                    resultado.mensagem ||
                    'Não foi possível registrar a entrada.'
                );
            }

            mostrarMensagem(resultado.mensagem);

            await carregarHistorico();
            await buscarCompras();

            setTimeout(() => {
                form.hidden = true;
            }, 900);

        } catch (erro) {
            mostrarMensagem(
                erro instanceof Error
                    ? erro.message
                    : 'Erro ao registrar entrada.',
                'error'
            );
        }
    });

    listaItens.addEventListener('click', function (evento) {
        const botao = evento.target.closest('.remove-item-button');

        if (!botao) return;

        if (
            listaItens.querySelectorAll('.entry-item').length === 1
        ) {
            alert('A entrada precisa ter pelo menos um material.');
            return;
        }

        botao.closest('.entry-item').remove();
    });

    listaCompras.addEventListener('click', function (evento) {
        const botao = evento.target.closest('.purchase-option');

        if (!botao) return;

        carregarCompra(Number(botao.dataset.id))
            .catch(erro => alert(erro.message));
    });

    document.getElementById('botaoBuscarCompra')
        .addEventListener('click', function () {
            buscarCompras().catch(erro => alert(erro.message));
        });

    document.getElementById('botaoEntradaAvulsa')
        .addEventListener('click', prepararEntradaAvulsa);

    document.getElementById('botaoAdicionarItem')
        .addEventListener('click', () => adicionarItem());

    document.getElementById('botaoCancelar')
        .addEventListener('click', function () {
            form.hidden = true;
        });

    campoCompra.addEventListener('keydown', function (evento) {
        if (evento.key === 'Enter') {
            evento.preventDefault();
            buscarCompras().catch(erro => alert(erro.message));
        }
    });

    Promise.all([
        carregarMateriais(),
        carregarHistorico()
    ]).then(() => {
        buscarCompras().catch(() => {});
    }).catch(erro => {
        alert(
            erro instanceof Error
                ? erro.message
                : 'Erro ao carregar a tela.'
        );
    });
});
