document.addEventListener('DOMContentLoaded', function () {
    const numeroAtendimento = document.getElementById('numeroAtendimento');
    const botaoPesquisar = document.getElementById('botaoPesquisar');
    const listaAbertos = document.getElementById('listaAtendimentosAbertos');
    const dadosPacienteCard = document.getElementById('dadosPacienteCard');
    const form = document.getElementById('formAdministracao');
    const historicoCard = document.getElementById('historicoCard');
    const historico = document.getElementById('historicoAdministracoes');
    const mensagem = document.getElementById('mensagemAdministracao');
    const botaoSalvar = document.getElementById('botaoSalvar');

    const atendimentoId = document.getElementById('atendimentoId');
    const pacienteId = document.getElementById('pacienteId');
    const pacienteNome = document.getElementById('pacienteNome');
    const pacienteCpf = document.getElementById('pacienteCpf');
    const atendimentoNumero = document.getElementById('atendimentoNumero');
    const atendimentoConvenio = document.getElementById('atendimentoConvenio');

    const medicamentoId = document.getElementById('medicamentoId');
    const viaAdministracao = document.getElementById('viaAdministracao');
    const status = document.getElementById('status');
    const dataHoraAdministracao = document.getElementById('dataHoraAdministracao');
    const campoDataAdministracao = document.getElementById('campoDataAdministracao');
    const campoMotivo = document.getElementById('campoMotivo');
    const motivoNaoAdministracao = document.getElementById('motivoNaoAdministracao');

    let medicamentos = [];

    function escapar(valor) {
        return String(valor ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function formatarCpf(valor) {
        const n = String(valor ?? '').replace(/\D/g, '').slice(0, 11);

        return n
            .replace(/^(\d{3})(\d)/, '$1.$2')
            .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
            .replace(/\.(\d{3})(\d)/, '.$1-$2');
    }

    function formatarData(valor) {
        if (!valor) return '-';

        const data = new Date(String(valor).replace(' ', 'T'));

        return Number.isNaN(data.getTime())
            ? valor
            : data.toLocaleString('pt-BR');
    }

    function agoraLocalInput() {
        const agora = new Date();
        const ajuste = agora.getTimezoneOffset() * 60000;
        return new Date(agora.getTime() - ajuste)
            .toISOString()
            .slice(0, 16);
    }

    function statusLegivel(valor) {
        return String(valor ?? '')
            .replaceAll('_', ' ')
            .toLowerCase()
            .replace(/\b\w/g, letra => letra.toUpperCase());
    }

    function mostrarMensagem(texto, tipo = 'success') {
        mensagem.hidden = false;
        mensagem.className = `nursing-message ${tipo}`;
        mensagem.textContent = texto;
    }

    function ocultarMensagem() {
        mensagem.hidden = true;
        mensagem.textContent = '';
        mensagem.className = 'nursing-message';
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

    function atualizarCamposStatus() {
        const naoAdministrado = status.value === 'NAO_ADMINISTRADO';

        campoMotivo.hidden = !naoAdministrado;
        campoDataAdministracao.hidden = naoAdministrado;

        motivoNaoAdministracao.required = naoAdministrado;

        if (naoAdministrado) {
            dataHoraAdministracao.value = '';
        } else if (!dataHoraAdministracao.value) {
            dataHoraAdministracao.value = agoraLocalInput();
        }
    }

    function renderizarAtendimentos(lista) {
        if (!Array.isArray(lista) || lista.length === 0) {
            listaAbertos.innerHTML = `
                <div class="history-empty">
                    Nenhum atendimento em aberto no momento.
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
                        <span><strong>${escapar(item.numero_atendimento)}</strong></span>
                        <span>${escapar(item.paciente_nome)}</span>
                        <span>${escapar(item.tipo_atendimento || '-')}</span>
                        <span>${escapar(statusLegivel(item.status))}</span>
                        <span>${escapar(formatarData(item.data_entrada))}</span>
                        <span>
                            <button
                                type="button"
                                class="select-attendance-button"
                                data-atendimento="${escapar(item.numero_atendimento)}"
                            >
                                <i class="fa-solid fa-pills"></i>
                                Administrar
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
                '/safe-health/modules/enfermagem/api/administracoes.php?acao=listar_abertos',
                {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store'
                }
            );

            const resultado = await lerJson(resposta);

            if (!resposta.ok || !resultado.sucesso) {
                throw new Error(resultado.mensagem || 'Não foi possível carregar os atendimentos.');
            }

            renderizarAtendimentos(resultado.dados.atendimentos ?? []);
        } catch (erro) {
            listaAbertos.innerHTML = `
                <div class="history-empty">
                    ${escapar(erro instanceof Error ? erro.message : 'Erro ao carregar atendimentos.')}
                </div>
            `;
        }
    }

    async function carregarMedicamentos() {
        const resposta = await fetch(
            '/safe-health/modules/enfermagem/api/administracoes.php?acao=medicamentos',
            {
                headers: { Accept: 'application/json' },
                cache: 'no-store'
            }
        );

        const resultado = await lerJson(resposta);

        if (!resposta.ok || !resultado.sucesso) {
            throw new Error(resultado.mensagem || 'Não foi possível carregar os medicamentos.');
        }

        medicamentos = resultado.dados.medicamentos ?? [];

        medicamentoId.innerHTML = `
            <option value="">Selecione</option>
            ${medicamentos.map(item => {
                const detalhes = [
                    item.concentracao,
                    item.apresentacao
                ].filter(Boolean).join(' · ');

                return `
                    <option value="${Number(item.id)}">
                        ${escapar(item.nome)}${detalhes ? ' — ' + escapar(detalhes) : ''}
                    </option>
                `;
            }).join('')}
        `;
    }

    function renderizarHistorico(lista) {
        if (!Array.isArray(lista) || lista.length === 0) {
            historico.innerHTML = `
                <div class="history-empty">
                    Nenhuma administração registrada neste atendimento.
                </div>
            `;
            return;
        }

        historico.innerHTML = lista.map(item => `
            <article class="history-item">
                <div class="history-top">
                    <div>
                        <strong>${escapar(item.medicamento_nome)}</strong>
                        <span>
                            ${escapar(item.dose || 'Dose não informada')}
                            ${item.via_administracao ? ' · ' + escapar(item.via_administracao) : ''}
                        </span>
                    </div>

                    <span class="status-badge ${item.status === 'ADMINISTRADO' ? 'administered' : 'not-administered'}">
                        ${escapar(statusLegivel(item.status))}
                    </span>
                </div>

                <div class="history-meta">
                    <span>
                        <strong>Previsto:</strong>
                        ${escapar(formatarData(item.horario_previsto))}
                    </span>

                    <span>
                        <strong>Realizado:</strong>
                        ${escapar(formatarData(item.data_hora_administracao))}
                    </span>

                    <span>
                        <strong>Profissional:</strong>
                        ${escapar(item.usuario_nome || 'Não identificado')}
                    </span>
                </div>

                ${item.motivo_nao_administracao
                    ? `<div class="history-note"><strong>Motivo:</strong> ${escapar(item.motivo_nao_administracao)}</div>`
                    : ''}

                ${item.observacoes
                    ? `<div class="history-note">${escapar(item.observacoes)}</div>`
                    : ''}
            </article>
        `).join('');
    }

    async function carregarHistorico() {
        const resposta = await fetch(
            `/safe-health/modules/enfermagem/api/administracoes.php?acao=historico&atendimento_id=${encodeURIComponent(atendimentoId.value)}`,
            {
                headers: { Accept: 'application/json' },
                cache: 'no-store'
            }
        );

        const resultado = await lerJson(resposta);

        if (!resposta.ok || !resultado.sucesso) {
            throw new Error(resultado.mensagem || 'Não foi possível carregar o histórico.');
        }

        renderizarHistorico(resultado.dados.administracoes ?? []);
        historicoCard.hidden = false;
    }

    async function pesquisarAtendimento(numeroForcado = null) {
        const numero = String(numeroForcado ?? numeroAtendimento.value).trim();

        if (!numero) {
            alert('Informe o número do atendimento.');
            numeroAtendimento.focus();
            return;
        }

        botaoPesquisar.disabled = true;

        try {
            const resposta = await fetch(
                `/safe-health/modules/enfermagem/api/administracoes.php?acao=buscar_atendimento&numero=${encodeURIComponent(numero)}`,
                {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store'
                }
            );

            const resultado = await lerJson(resposta);

            if (!resposta.ok || !resultado.sucesso) {
                throw new Error(resultado.mensagem || 'Atendimento não encontrado.');
            }

            const atendimento = resultado.dados.atendimento;

            numeroAtendimento.value = atendimento.numero_atendimento || numero;
            atendimentoId.value = String(atendimento.atendimento_id);
            pacienteId.value = String(atendimento.paciente_id);
            pacienteNome.textContent = atendimento.paciente_nome || '-';
            pacienteCpf.textContent = atendimento.paciente_cpf
                ? formatarCpf(atendimento.paciente_cpf)
                : 'Não informado';
            atendimentoNumero.textContent = atendimento.numero_atendimento || '-';
            atendimentoConvenio.textContent = atendimento.convenio || 'Não informado';

            dadosPacienteCard.hidden = false;
            form.hidden = false;
            ocultarMensagem();

            await carregarHistorico();

            dadosPacienteCard.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        } catch (erro) {
            dadosPacienteCard.hidden = true;
            form.hidden = true;
            historicoCard.hidden = true;

            alert(
                erro instanceof Error
                    ? erro.message
                    : 'Não foi possível localizar o atendimento.'
            );
        } finally {
            botaoPesquisar.disabled = false;
        }
    }

    medicamentoId.addEventListener('change', function () {
        const selecionado = medicamentos.find(
            item => String(item.id) === medicamentoId.value
        );

        if (selecionado?.via_administracao && !viaAdministracao.value) {
            viaAdministracao.value = selecionado.via_administracao;
        }
    });

    status.addEventListener('change', atualizarCamposStatus);

    form.addEventListener('reset', function () {
        setTimeout(() => {
            status.value = 'ADMINISTRADO';
            dataHoraAdministracao.value = agoraLocalInput();
            atualizarCamposStatus();
        }, 0);
    });

    form.addEventListener('submit', async function (evento) {
        evento.preventDefault();

        if (!form.reportValidity()) {
            return;
        }

        botaoSalvar.disabled = true;
        ocultarMensagem();

        const dados = Object.fromEntries(
            new FormData(form).entries()
        );

        try {
            const resposta = await fetch(
                '/safe-health/modules/enfermagem/api/administracoes.php',
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
                throw new Error(resultado.mensagem || 'Não foi possível salvar.');
            }

            const atendimentoAtual = atendimentoId.value;
            const pacienteAtual = pacienteId.value;

            form.reset();

            atendimentoId.value = atendimentoAtual;
            pacienteId.value = pacienteAtual;

            mostrarMensagem(resultado.mensagem);

            await carregarHistorico();
            await carregarAtendimentos();

        } catch (erro) {
            mostrarMensagem(
                erro instanceof Error ? erro.message : 'Não foi possível salvar.',
                'error'
            );
        } finally {
            botaoSalvar.disabled = false;
        }
    });

    botaoPesquisar.addEventListener('click', () => pesquisarAtendimento());

    numeroAtendimento.addEventListener('keydown', function (evento) {
        if (evento.key === 'Enter') {
            evento.preventDefault();
            pesquisarAtendimento();
        }
    });

    listaAbertos.addEventListener('click', function (evento) {
        const botao = evento.target.closest('.select-attendance-button');

        if (!botao) return;

        pesquisarAtendimento(botao.dataset.atendimento || '');
    });

    dataHoraAdministracao.value = agoraLocalInput();
    atualizarCamposStatus();

    carregarMedicamentos().catch(erro => {
        alert(erro instanceof Error ? erro.message : 'Erro ao carregar medicamentos.');
    });

    carregarAtendimentos();

    setInterval(carregarAtendimentos, 30000);
});
