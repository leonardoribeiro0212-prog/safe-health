document.addEventListener('DOMContentLoaded', function () {
    const numeroAtendimento = document.getElementById('numeroAtendimento');
    const botaoPesquisar = document.getElementById('botaoPesquisar');
    const listaAbertos = document.getElementById('listaAtendimentosAbertos');
    const dadosPacienteCard = document.getElementById('dadosPacienteCard');
    const form = document.getElementById('formAfericao');
    const historicoCard = document.getElementById('historicoCard');
    const historico = document.getElementById('historicoAfericoes');
    const mensagem = document.getElementById('mensagemAfericao');
    const botaoSalvar = document.getElementById('botaoSalvar');
    const atendimentoId = document.getElementById('atendimentoId');
    const pacienteId = document.getElementById('pacienteId');
    const pacienteNome = document.getElementById('pacienteNome');
    const pacienteCpf = document.getElementById('pacienteCpf');
    const atendimentoNumero = document.getElementById('atendimentoNumero');
    const atendimentoConvenio = document.getElementById('atendimentoConvenio');

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

    function statusLegivel(status) {
        return String(status ?? '')
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

    function renderizarAtendimentosAbertos(lista) {
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
                                <i class="fa-solid fa-heart-pulse"></i>
                                Aferir
                            </button>
                        </span>
                    </div>
                `).join('')}
            </div>
        `;
    }

    async function carregarAtendimentosAbertos() {
        try {
            const resposta = await fetch(
                '/safe-health/modules/enfermagem/api/afericoes.php?acao=listar_abertos',
                {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store'
                }
            );

            const resultado = await lerJson(resposta);

            if (!resposta.ok || !resultado.sucesso) {
                throw new Error(resultado.mensagem || 'Não foi possível listar os atendimentos.');
            }

            renderizarAtendimentosAbertos(resultado.dados.atendimentos ?? []);

        } catch (erro) {
            listaAbertos.innerHTML = `
                <div class="history-empty">
                    ${escapar(erro instanceof Error ? erro.message : 'Não foi possível carregar os atendimentos.')}
                </div>
            `;
        }
    }

    function valor(rotulo, conteudo, unidade = '') {
        if (conteudo === null || conteudo === undefined || conteudo === '') return '';
        return `<span><strong>${escapar(rotulo)}:</strong> ${escapar(conteudo)}${unidade ? ' ' + escapar(unidade) : ''}</span>`;
    }

    function renderizarHistorico(lista) {
        if (!Array.isArray(lista) || lista.length === 0) {
            historico.innerHTML = `<div class="history-empty">Nenhuma aferição registrada neste atendimento.</div>`;
            return;
        }

        historico.innerHTML = lista.map((item) => {
            const pressao =
                item.pressao_sistolica || item.pressao_diastolica
                    ? `${item.pressao_sistolica ?? '-'} / ${item.pressao_diastolica ?? '-'}`
                    : null;

            const dados = [
                valor('PA', pressao, pressao ? 'mmHg' : ''),
                valor('FC', item.frequencia_cardiaca, 'bpm'),
                valor('FR', item.frequencia_respiratoria, 'irpm'),
                valor('Temperatura', item.temperatura, '°C'),
                valor('SpO₂', item.saturacao, '%'),
                valor('Glicemia', item.glicemia, 'mg/dL'),
                valor('Peso', item.peso, 'kg'),
                valor('Dor', item.escala_dor)
            ].filter(Boolean).join('');

            return `
                <article class="history-item">
                    <div class="history-header">
                        <strong>${escapar(formatarData(item.data_hora))}</strong>
                    </div>
                    <div class="history-values">
                        ${dados || '<span>Sem sinais vitais informados.</span>'}
                    </div>
                    ${item.observacoes ? `<div class="history-note">${escapar(item.observacoes)}</div>` : ''}
                </article>
            `;
        }).join('');
    }

    async function carregarHistorico() {
        const resposta = await fetch(
            `/safe-health/modules/enfermagem/api/afericoes.php?acao=historico&atendimento_id=${encodeURIComponent(atendimentoId.value)}`,
            {
                headers: { Accept: 'application/json' },
                cache: 'no-store'
            }
        );

        const resultado = await lerJson(resposta);

        if (!resposta.ok || !resultado.sucesso) {
            throw new Error(resultado.mensagem || 'Não foi possível carregar o histórico.');
        }

        renderizarHistorico(resultado.dados.afericoes ?? []);
        historicoCard.hidden = false;
    }

    async function pesquisarAtendimento(numeroForcado = null) {
        const numero = (numeroForcado ?? numeroAtendimento.value).trim();

        if (!numero) {
            alert('Informe o número do atendimento.');
            numeroAtendimento.focus();
            return;
        }

        botaoPesquisar.disabled = true;

        try {
            const resposta = await fetch(
                `/safe-health/modules/enfermagem/api/afericoes.php?acao=buscar_atendimento&numero=${encodeURIComponent(numero)}`,
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

    form.addEventListener('submit', async function (evento) {
        evento.preventDefault();

        botaoSalvar.disabled = true;
        ocultarMensagem();

        const dados = Object.fromEntries(new FormData(form).entries());

        try {
            const resposta = await fetch(
                '/safe-health/modules/enfermagem/api/afericoes.php',
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

            const idAtendimentoAtual = atendimentoId.value;
            const idPacienteAtual = pacienteId.value;

            form.reset();

            atendimentoId.value = idAtendimentoAtual;
            pacienteId.value = idPacienteAtual;

            mostrarMensagem(resultado.mensagem);

            await carregarHistorico();
            await carregarAtendimentosAbertos();

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

    carregarAtendimentosAbertos();

    setInterval(carregarAtendimentosAbertos, 30000);
});
