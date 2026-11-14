document.addEventListener('DOMContentLoaded', function () {
    const form =
        document.getElementById('formSaida');

    const listaItens =
        document.getElementById('listaItensSaida');

    const listaSaidas =
        document.getElementById('listaSaidas');

    const mensagem =
        document.getElementById('mensagemSaida');

    const numeroAtendimento =
        document.getElementById('numeroAtendimento');

    const atendimentoId =
        document.getElementById('atendimentoId');

    const pacienteId =
        document.getElementById('pacienteId');

    const dadosAtendimento =
        document.getElementById('dadosAtendimento');

    let estoque = [];

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
            `exit-message ${tipo}`;
        mensagem.textContent = texto;
    }

    function estoqueOptions(
        selecionado = ''
    ) {
        return `
            <option value="">
                Selecione material / lote
            </option>

            ${estoque.map(item => `
                <option
                    value="${Number(item.estoque_id)}"
                    ${String(item.estoque_id) === String(selecionado) ? 'selected' : ''}
                >
                    ${escapar(item.material_nome)}
                    — Lote ${escapar(item.lote)}
                    — Saldo ${escapar(item.quantidade_atual)}
                </option>
            `).join('')}
        `;
    }

    function adicionarItem(item = {}) {
        const linha =
            document.createElement('div');

        linha.className =
            'exit-item';

        linha.innerHTML = `
            <div class="form-grid">

                <div class="field col-7">
                    <label>
                        Material / lote
                        <span>*</span>
                    </label>

                    <select
                        class="item-estoque"
                        required
                    >
                        ${estoqueOptions(
                            item.estoque_id ||
                            ''
                        )}
                    </select>
                </div>

                <div class="field col-3">
                    <label>
                        Quantidade
                        <span>*</span>
                    </label>

                    <input
                        type="number"
                        class="item-quantidade"
                        min="0.001"
                        step="0.001"
                        value="${escapar(item.quantidade || '1')}"
                        required
                    >
                </div>

                <div class="field col-2 action-field">
                    <label>&nbsp;</label>

                    <button
                        type="button"
                        class="remove-item-button"
                    >
                        <i class="fa-solid fa-trash"></i>
                        Remover
                    </button>
                </div>

                <div
                    class="stock-info"
                    hidden
                ></div>

            </div>
        `;

        listaItens.appendChild(linha);

        const select =
            linha.querySelector(
                '.item-estoque'
            );

        atualizarInformacaoEstoque(
            select
        );
    }

    function atualizarInformacaoEstoque(
        select
    ) {
        const linha =
            select.closest('.exit-item');

        const info =
            linha.querySelector(
                '.stock-info'
            );

        const selecionado =
            estoque.find(
                item =>
                    String(item.estoque_id) ===
                    String(select.value)
            );

        if (!selecionado) {
            info.hidden = true;
            info.innerHTML = '';
            return;
        }

        info.hidden = false;

        info.innerHTML = `
            <span>
                <strong>Código:</strong>
                ${escapar(selecionado.codigo || '-')}
            </span>

            <span>
                <strong>Categoria:</strong>
                ${escapar(selecionado.categoria || '-')}
            </span>

            <span>
                <strong>Saldo:</strong>
                ${escapar(selecionado.quantidade_atual)}
                ${escapar(selecionado.unidade_medida || '')}
            </span>

            <span>
                <strong>Localização:</strong>
                ${escapar(selecionado.localizacao || '-')}
            </span>
        `;

        const quantidade =
            linha.querySelector(
                '.item-quantidade'
            );

        quantidade.max =
            selecionado.quantidade_atual;
    }

    function obterItens() {
        return Array.from(
            listaItens.querySelectorAll(
                '.exit-item'
            )
        ).map(linha => ({
            estoque_id:
                linha.querySelector(
                    '.item-estoque'
                ).value,

            quantidade:
                linha.querySelector(
                    '.item-quantidade'
                ).value
        }));
    }

    async function carregarEstoque() {
        const resposta = await fetch(
            '/safe-health/modules/logistica/api/saida-estoque.php?acao=estoque',
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

        estoque =
            resultado.dados.estoque ?? [];
    }

    async function buscarAtendimento() {
        const numero =
            numeroAtendimento.value.trim();

        if (!numero) {
            alert(
                'Informe o número do atendimento.'
            );

            return;
        }

        try {
            const resposta = await fetch(
                `/safe-health/modules/logistica/api/saida-estoque.php?acao=buscar_atendimento&numero=${encodeURIComponent(numero)}`,
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

            atendimentoId.value =
                a.atendimento_id;

            pacienteId.value =
                a.paciente_id;

            numeroAtendimento.value =
                a.numero_atendimento;

            document.getElementById(
                'pacienteNome'
            ).textContent =
                a.paciente_nome || '-';

            document.getElementById(
                'atendimentoNumero'
            ).textContent =
                a.numero_atendimento || '-';

            document.getElementById(
                'atendimentoConvenio'
            ).textContent =
                a.convenio || 'Não informado';

            document.getElementById(
                'atendimentoStatus'
            ).textContent =
                statusLegivel(a.status);

            dadosAtendimento.hidden =
                false;

        } catch (erro) {
            alert(
                erro instanceof Error
                    ? erro.message
                    : 'Erro ao localizar atendimento.'
            );
        }
    }

    function limparAtendimento() {
        atendimentoId.value = '';
        pacienteId.value = '';
        numeroAtendimento.value = '';
        dadosAtendimento.hidden = true;
    }

    function renderizarHistorico(lista) {
        if (
            !Array.isArray(lista) ||
            lista.length === 0
        ) {
            listaSaidas.innerHTML = `
                <div class="empty-state">
                    Nenhuma saída registrada.
                </div>
            `;

            return;
        }

        listaSaidas.innerHTML = `
            <div class="history-table">

                <div class="history-row history-header">
                    <span>Saída</span>
                    <span>Destino</span>
                    <span>Atendimento</span>
                    <span>Paciente</span>
                    <span>Itens</span>
                    <span>Quantidade</span>
                    <span>Usuário</span>
                    <span>Data</span>
                </div>

                ${lista.map(item => `
                    <div class="history-row">

                        <span>
                            <strong>
                                #${Number(item.id)}
                            </strong>
                        </span>

                        <span>
                            ${escapar(item.destino || '-')}
                        </span>

                        <span>
                            ${escapar(
                                item.numero_atendimento ||
                                'Sem vínculo'
                            )}
                        </span>

                        <span>
                            ${escapar(
                                item.paciente_nome ||
                                '-'
                            )}
                        </span>

                        <span>
                            ${Number(item.total_itens || 0)}
                        </span>

                        <span>
                            ${escapar(
                                item.total_quantidade ||
                                '0'
                            )}
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
                                    item.criado_em
                                )
                            )}
                        </span>

                    </div>
                `).join('')}

            </div>
        `;
    }

    async function carregarHistorico() {
        const resposta = await fetch(
            '/safe-health/modules/logistica/api/saida-estoque.php?acao=historico',
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

        renderizarHistorico(
            resultado.dados.saidas ?? []
        );
    }

    form.addEventListener(
        'submit',
        async function (evento) {
            evento.preventDefault();

            if (!form.reportValidity()) {
                return;
            }

            const dados = {
                atendimento_id:
                    atendimentoId.value,

                paciente_id:
                    pacienteId.value,

                destino:
                    document.getElementById(
                        'destino'
                    ).value,

                motivo:
                    document.getElementById(
                        'motivo'
                    ).value,

                observacoes:
                    document.getElementById(
                        'observacoes'
                    ).value,

                itens:
                    obterItens()
            };

            try {
                const resposta = await fetch(
                    '/safe-health/modules/logistica/api/saida-estoque.php',
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
                        'Não foi possível registrar a saída.'
                    );
                }

                mostrarMensagem(
                    resultado.mensagem
                );

                form.reset();
                limparAtendimento();

                await carregarEstoque();

                listaItens.innerHTML = '';

                if (estoque.length > 0) {
                    adicionarItem();
                }

                await carregarHistorico();

            } catch (erro) {
                mostrarMensagem(
                    erro instanceof Error
                        ? erro.message
                        : 'Erro ao registrar saída.',
                    'error'
                );
            }
        }
    );

    form.addEventListener(
        'reset',
        function () {
            setTimeout(
                () => {
                    listaItens.innerHTML = '';

                    if (estoque.length > 0) {
                        adicionarItem();
                    }
                },
                0
            );
        }
    );

    listaItens.addEventListener(
        'change',
        function (evento) {
            if (
                evento.target.matches(
                    '.item-estoque'
                )
            ) {
                atualizarInformacaoEstoque(
                    evento.target
                );
            }
        }
    );

    listaItens.addEventListener(
        'click',
        function (evento) {
            const botao =
                evento.target.closest(
                    '.remove-item-button'
                );

            if (!botao) {
                return;
            }

            if (
                listaItens.querySelectorAll(
                    '.exit-item'
                ).length === 1
            ) {
                alert(
                    'A saída precisa ter pelo menos um material.'
                );

                return;
            }

            botao.closest(
                '.exit-item'
            ).remove();
        }
    );

    document.getElementById(
        'botaoAdicionarItem'
    ).addEventListener(
        'click',
        function () {
            if (estoque.length === 0) {
                alert(
                    'Não existem lotes com saldo disponível.'
                );

                return;
            }

            adicionarItem();
        }
    );

    document.getElementById(
        'botaoBuscarAtendimento'
    ).addEventListener(
        'click',
        buscarAtendimento
    );

    document.getElementById(
        'botaoLimparAtendimento'
    ).addEventListener(
        'click',
        limparAtendimento
    );

    numeroAtendimento.addEventListener(
        'keydown',
        function (evento) {
            if (evento.key === 'Enter') {
                evento.preventDefault();
                buscarAtendimento();
            }
        }
    );

    Promise.all([
        carregarEstoque(),
        carregarHistorico()
    ]).then(
        () => {
            if (estoque.length > 0) {
                adicionarItem();
            } else {
                listaItens.innerHTML = `
                    <div class="empty-state">
                        Não existem materiais com saldo disponível.
                    </div>
                `;
            }
        }
    ).catch(
        erro => {
            alert(
                erro instanceof Error
                    ? erro.message
                    : 'Erro ao carregar a tela.'
            );
        }
    );
});
