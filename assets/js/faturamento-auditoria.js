document.addEventListener('DOMContentLoaded', function () {
    const campoPesquisa = document.getElementById('campoPesquisa');
    const listaContas = document.getElementById('listaContas');
    const detalhesCard = document.getElementById('detalhesCard');
    const listaItens = document.getElementById('listaItens');
    const mensagem = document.getElementById('mensagemAuditoria');

    let contaAtual = null;

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
            .replace(/\b\w/g, letra => letra.toUpperCase());
    }

    function formatarData(valor) {
        if (!valor) return '-';

        const data = new Date(String(valor).replace(' ', 'T'));

        return Number.isNaN(data.getTime())
            ? valor
            : data.toLocaleString('pt-BR');
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
        mensagem.className = `audit-message ${tipo}`;
        mensagem.textContent = texto;
    }

    function ocultarMensagem() {
        mensagem.hidden = true;
        mensagem.textContent = '';
    }

    async function carregarResumo() {
        try {
            const resposta = await fetch(
                '/safe-health/modules/faturamento/api/auditoria.php?acao=resumo',
                {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store'
                }
            );

            const resultado = await lerJson(resposta);

            if (!resposta.ok || !resultado.sucesso) return;

            const r = resultado.dados.resumo || {};

            document.getElementById('resumoContas').textContent =
                Number(r.contas || 0);

            document.getElementById('resumoPendentes').textContent =
                Number(r.pendentes || 0);

            document.getElementById('resumoAuditados').textContent =
                Number(r.auditados || 0);

            document.getElementById('resumoGlosados').textContent =
                Number(r.glosados || 0);

        } catch (erro) {
            console.error(erro);
        }
    }

    function renderizarContas(lista) {
        if (!Array.isArray(lista) || lista.length === 0) {
            listaContas.innerHTML = `
                <div class="empty-state">
                    Nenhuma conta está aguardando auditoria.
                </div>
            `;
            return;
        }

        listaContas.innerHTML = `
            <div class="accounts-table">
                <div class="account-row account-header">
                    <span>Conta</span>
                    <span>Atendimento</span>
                    <span>Paciente</span>
                    <span>Convênio</span>
                    <span>Itens</span>
                    <span>Pendentes</span>
                    <span>Glosas</span>
                    <span>Valor líquido</span>
                    <span>Ação</span>
                </div>

                ${lista.map(item => `
                    <div class="account-row">
                        <span><strong>#${Number(item.id)}</strong></span>
                        <span>${escapar(item.numero_atendimento)}</span>
                        <span>${escapar(item.paciente_nome)}</span>
                        <span>${escapar(item.convenio || 'Particular')}</span>
                        <span>${Number(item.total_itens || 0)}</span>
                        <span>${Number(item.itens_pendentes || 0)}</span>
                        <span>${Number(item.itens_glosados || 0)}</span>
                        <span><strong>${escapar(moeda(item.valor_liquido))}</strong></span>
                        <span>
                            <button
                                type="button"
                                class="open-button"
                                data-id="${Number(item.id)}"
                            >
                                <i class="fa-solid fa-clipboard-check"></i>
                                Auditar
                            </button>
                        </span>
                    </div>
                `).join('')}
            </div>
        `;
    }

    async function carregarContas() {
        const q = campoPesquisa.value.trim();

        listaContas.innerHTML =
            '<div class="empty-state">Carregando contas...</div>';

        try {
            const resposta = await fetch(
                `/safe-health/modules/faturamento/api/auditoria.php?acao=listar&q=${encodeURIComponent(q)}`,
                {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store'
                }
            );

            const resultado = await lerJson(resposta);

            if (!resposta.ok || !resultado.sucesso) {
                throw new Error(resultado.mensagem || 'Não foi possível carregar as contas.');
            }

            renderizarContas(resultado.dados.contas ?? []);

        } catch (erro) {
            listaContas.innerHTML = `
                <div class="empty-state error">
                    ${escapar(
                        erro instanceof Error
                            ? erro.message
                            : 'Erro ao carregar contas.'
                    )}
                </div>
            `;
        }
    }

    function renderizarItens(itens) {
        if (!Array.isArray(itens) || itens.length === 0) {
            listaItens.innerHTML = `
                <div class="empty-state">
                    Esta conta não possui itens.
                </div>
            `;
            return;
        }

        listaItens.innerHTML = itens.map(item => `
            <article class="audit-item" data-item-id="${Number(item.id)}">
                <div class="item-main">
                    <div class="item-description">
                        <span class="item-type">
                            ${escapar(statusLegivel(item.tipo_item))}
                        </span>

                        <strong>${escapar(item.descricao)}</strong>

                        <small>
                            Lançado em ${escapar(formatarData(item.criado_em))}
                        </small>
                    </div>

                    <div class="item-values">
                        <span>
                            <small>Quantidade</small>
                            <strong>${escapar(item.quantidade)}</strong>
                        </span>

                        <span>
                            <small>Valor unitário</small>
                            <strong>${escapar(moeda(item.valor_unitario))}</strong>
                        </span>

                        <span>
                            <small>Total</small>
                            <strong>${escapar(moeda(item.valor_total))}</strong>
                        </span>
                    </div>

                    <span class="status-badge ${
                        item.status_item === 'AUDITADO'
                            ? 'audited'
                            : item.status_item === 'GLOSADO'
                                ? 'denied'
                                : 'pending'
                    }">
                        ${escapar(statusLegivel(item.status_item))}
                    </span>
                </div>

                <div class="item-audit-controls">
                    <input
                        type="text"
                        class="item-observation"
                        maxlength="500"
                        placeholder="${
                            item.status_item === 'GLOSADO'
                                ? 'Informe o motivo da glosa'
                                : 'Observação do auditor (opcional)'
                        }"
                    >

                    <div class="item-buttons">
                        <button
                            type="button"
                            class="audit-action-button approve"
                            data-status="AUDITADO"
                        >
                            <i class="fa-solid fa-check"></i>
                            Auditar
                        </button>

                        <button
                            type="button"
                            class="audit-action-button deny"
                            data-status="GLOSADO"
                        >
                            <i class="fa-solid fa-ban"></i>
                            Glosar
                        </button>

                        <button
                            type="button"
                            class="audit-action-button reset"
                            data-status="PENDENTE"
                        >
                            <i class="fa-solid fa-rotate-left"></i>
                            Pendente
                        </button>
                    </div>
                </div>
            </article>
        `).join('');
    }

    async function abrirConta(id) {
        ocultarMensagem();

        try {
            const resposta = await fetch(
                `/safe-health/modules/faturamento/api/auditoria.php?acao=detalhes&conta_id=${encodeURIComponent(id)}`,
                {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store'
                }
            );

            const resultado = await lerJson(resposta);

            if (!resposta.ok || !resultado.sucesso) {
                throw new Error(resultado.mensagem || 'Não foi possível abrir a conta.');
            }

            const conta = resultado.dados.conta;
            contaAtual = Number(conta.id);

            document.getElementById('tituloConta').textContent =
                `Auditoria da Conta #${conta.id}`;

            document.getElementById('statusConta').textContent =
                statusLegivel(conta.status);

            document.getElementById('pacienteNome').textContent =
                conta.paciente_nome || '-';

            document.getElementById('atendimentoNumero').textContent =
                conta.numero_atendimento || '-';

            document.getElementById('convenio').textContent =
                conta.convenio || 'Particular';

            document.getElementById('dataAbertura').textContent =
                formatarData(conta.data_abertura);

            document.getElementById('valorBruto').textContent =
                moeda(conta.valor_bruto);

            document.getElementById('valorDesconto').textContent =
                moeda(conta.valor_desconto);

            document.getElementById('valorLiquido').textContent =
                moeda(conta.valor_liquido);

            document.getElementById('linkContaHospitalar').href =
                `/safe-health/modules/faturamento/conta-hospitalar.php?conta_id=${conta.id}`;

            renderizarItens(resultado.dados.itens ?? []);

            detalhesCard.hidden = false;

            detalhesCard.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });

        } catch (erro) {
            mostrarMensagem(
                erro instanceof Error
                    ? erro.message
                    : 'Erro ao abrir a conta.',
                'error'
            );

            detalhesCard.hidden = false;
        }
    }

    async function alterarStatusItem(item, novoStatus) {
        if (!contaAtual) return;

        const itemId = Number(item.dataset.itemId);
        const observacao =
            item.querySelector('.item-observation').value.trim();

        if (novoStatus === 'GLOSADO' && !observacao) {
            alert('Informe o motivo da glosa.');
            item.querySelector('.item-observation').focus();
            return;
        }

        try {
            const resposta = await fetch(
                '/safe-health/modules/faturamento/api/auditoria.php',
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json'
                    },
                    body: JSON.stringify({
                        acao: 'alterar_status_item',
                        conta_id: contaAtual,
                        item_id: itemId,
                        status_item: novoStatus,
                        observacao
                    })
                }
            );

            const resultado = await lerJson(resposta);

            if (!resposta.ok || !resultado.sucesso) {
                throw new Error(resultado.mensagem || 'Não foi possível atualizar o item.');
            }

            await abrirConta(contaAtual);
            await carregarContas();
            await carregarResumo();

        } catch (erro) {
            mostrarMensagem(
                erro instanceof Error
                    ? erro.message
                    : 'Erro ao atualizar item.',
                'error'
            );
        }
    }

    async function concluirAuditoria() {
        if (!contaAtual) return;

        try {
            const resposta = await fetch(
                '/safe-health/modules/faturamento/api/auditoria.php',
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json'
                    },
                    body: JSON.stringify({
                        acao: 'concluir_auditoria',
                        conta_id: contaAtual,
                        observacoes:
                            document.getElementById('observacaoFinal').value.trim()
                    })
                }
            );

            const resultado = await lerJson(resposta);

            if (!resposta.ok || !resultado.sucesso) {
                throw new Error(resultado.mensagem || 'Não foi possível concluir a auditoria.');
            }

            mostrarMensagem(resultado.mensagem);

            await abrirConta(contaAtual);
            await carregarContas();
            await carregarResumo();

        } catch (erro) {
            mostrarMensagem(
                erro instanceof Error
                    ? erro.message
                    : 'Erro ao concluir auditoria.',
                'error'
            );
        }
    }

    listaContas.addEventListener('click', function (evento) {
        const botao = evento.target.closest('.open-button');

        if (!botao) return;

        abrirConta(Number(botao.dataset.id));
    });

    listaItens.addEventListener('click', function (evento) {
        const botao = evento.target.closest('.audit-action-button');

        if (!botao) return;

        alterarStatusItem(
            botao.closest('.audit-item'),
            botao.dataset.status
        );
    });

    document.getElementById('botaoConcluir')
        .addEventListener('click', concluirAuditoria);

    document.getElementById('botaoPesquisar')
        .addEventListener('click', carregarContas);

    document.getElementById('botaoLimpar')
        .addEventListener('click', function () {
            campoPesquisa.value = '';
            carregarContas();
        });

    document.getElementById('botaoAtualizar')
        .addEventListener('click', async function () {
            await Promise.allSettled([
                carregarContas(),
                carregarResumo()
            ]);

            if (contaAtual) {
                await abrirConta(contaAtual);
            }
        });

    campoPesquisa.addEventListener('keydown', function (evento) {
        if (evento.key === 'Enter') {
            evento.preventDefault();
            carregarContas();
        }
    });

    Promise.all([
        carregarContas(),
        carregarResumo()
    ]).catch(erro => {
        console.error(erro);
    });
});
