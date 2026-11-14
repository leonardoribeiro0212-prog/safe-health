const tabelaSenhas =
    document.getElementById('tabelaSenhas');

const totalAguardando =
    document.getElementById('totalAguardando');

const senhaSelecionadaResumo =
    document.getElementById('senhaSelecionadaResumo');

const ultimaAtualizacao =
    document.getElementById('ultimaAtualizacao');

const senhaSelecionada =
    document.getElementById('senhaSelecionada');

const filaSelecionada =
    document.getElementById('filaSelecionada');

const ticketBadge =
    document.getElementById('ticketBadge');

const senhaId =
    document.getElementById('senhaId');

const camposTriagem =
    document.getElementById('camposTriagem');

const formTriagem =
    document.getElementById('formTriagem');

const botaoAtualizar =
    document.getElementById('botaoAtualizar');

const botaoCancelar =
    document.getElementById('botaoCancelar');

const botaoFinalizar =
    document.getElementById('botaoFinalizar');

const salaTriagem =
    document.getElementById('salaTriagem');

const toast =
    document.getElementById('toast');

let carregando = false;
let salvando = false;
let chamando = false;
let senhaAtualId = null;

function escaparHtml(valor) {
    return String(valor ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function mostrarMensagem(
    mensagem,
    tipo = 'success'
) {
    toast.textContent = mensagem;
    toast.className = `triagem-toast show ${tipo}`;

    window.setTimeout(() => {
        toast.className = 'triagem-toast';
    }, 3500);
}

function formatarData(data) {
    if (!data) {
        return '--';
    }

    const valor = String(data);

    if (
        valor.includes('-') &&
        valor.includes(':')
    ) {
        const partes = valor.split(' ');
        const dataPartes = partes[0].split('-');

        return `${dataPartes[2]}/${dataPartes[1]}/${dataPartes[0]} ${partes[1].slice(0, 5)}`;
    }

    return valor;
}

function formatarTempoEspera(minutos) {
    const totalMinutos = Number(minutos ?? 0);

    if (
        !Number.isFinite(totalMinutos) ||
        totalMinutos <= 0
    ) {
        return 'Menos de 1 min';
    }

    if (totalMinutos < 60) {
        return `${totalMinutos} min`;
    }

    const horas = Math.floor(totalMinutos / 60);
    const minutosRestantes = totalMinutos % 60;

    if (minutosRestantes === 0) {
        return `${horas}h`;
    }

    return `${horas}h ${minutosRestantes}min`;
}

async function lerRespostaJson(resposta) {
    const texto = await resposta.text();

    if (texto.trim() === '') {
        throw new Error(
            'O servidor não retornou uma resposta.'
        );
    }

    try {
        return JSON.parse(texto);
    } catch {
        console.error(
            'Resposta inválida recebida do servidor:',
            texto
        );

        throw new Error(
            'O servidor retornou uma resposta inválida.'
        );
    }
}

function selecionarSenha(
    id,
    numero,
    fila
) {
    const idNumerico = Number(id);

    if (
        !Number.isInteger(idNumerico) ||
        idNumerico <= 0
    ) {
        mostrarMensagem(
            'A senha selecionada é inválida.',
            'error'
        );

        return;
    }

    senhaAtualId = idNumerico;
    senhaId.value = String(idNumerico);

    senhaSelecionadaResumo.textContent = numero;
    senhaSelecionada.textContent = `Senha ${numero}`;
    filaSelecionada.textContent = fila;
    ticketBadge.textContent = numero;

    camposTriagem.disabled = false;

    document
        .querySelectorAll('#tabelaSenhas tr')
        .forEach((linha) => {
            linha.classList.remove('selected-row');
        });

    const linhaSelecionada =
        document.querySelector(
            `#tabelaSenhas tr[data-senha-id="${idNumerico}"]`
        );

    if (linhaSelecionada) {
        linhaSelecionada.classList.add(
            'selected-row'
        );
    }

    document
        .getElementById('queixaPrincipal')
        .focus();
}

function limparSelecao() {
    senhaAtualId = null;
    senhaId.value = '';

    senhaSelecionadaResumo.textContent = '---';

    senhaSelecionada.textContent =
        'Nenhuma senha selecionada';

    filaSelecionada.textContent =
        'Selecione uma senha na lista ao lado.';

    ticketBadge.textContent = '---';

    formTriagem.reset();
    camposTriagem.disabled = true;

    document
        .querySelectorAll('#tabelaSenhas tr')
        .forEach((linha) => {
            linha.classList.remove('selected-row');
        });
}

function renderizarTabela(senhas) {
    if (
        !Array.isArray(senhas) ||
        senhas.length === 0
    ) {
        tabelaSenhas.innerHTML = `
            <tr>
                <td
                    colspan="5"
                    class="empty-row"
                >
                    Nenhuma senha aguardando triagem.
                </td>
            </tr>
        `;

        totalAguardando.textContent = '0';

        return;
    }

    totalAguardando.textContent =
        String(senhas.length);

    tabelaSenhas.innerHTML = senhas
        .map((senha) => {
            const id = Number(senha.id);

            const numero =
                escaparHtml(senha.numero);

            const fila =
                escaparHtml(
                    senha.fila_nome ??
                    senha.fila ??
                    'Fila não informada'
                );

            const codigoFila =
                escaparHtml(
                    senha.fila_codigo ??
                    senha.codigo_fila ??
                    ''
                );

            const emissao =
                escaparHtml(
                    formatarData(
                        senha.emitida_em ??
                        senha.data_emissao
                    )
                );

            const espera =
                escaparHtml(
                    formatarTempoEspera(
                        senha.tempo_espera_minutos
                    )
                );

            return `
                <tr
                    data-senha-id="${id}"
                    class="${senhaAtualId === id ? 'selected-row' : ''}"
                >
                    <td>
                        <strong class="ticket-number">
                            ${numero}
                        </strong>
                    </td>

                    <td>
                        <div class="queue-name">
                            ${fila}
                        </div>

                        <small>
                            ${codigoFila}
                        </small>
                    </td>

                    <td>
                        ${emissao}
                    </td>

                    <td>
                        <span class="waiting-time">
                            ${espera}
                        </span>
                    </td>

                    <td>
                        <div class="triage-row-actions">

                            <button
                                type="button"
                                class="call-triage-button"
                                data-acao="chamar"
                                data-id="${id}"
                                data-numero="${numero}"
                            >
                                <i class="fa-solid fa-bullhorn"></i>
                                Chamar
                            </button>

                            <button
                                type="button"
                                class="select-button"
                                data-acao="triar"
                                data-id="${id}"
                                data-numero="${numero}"
                                data-fila="${fila}"
                            >
                                <i class="fa-solid fa-stethoscope"></i>
                                Triar
                            </button>

                            <button
                                type="button"
                                class="delete-triage-button"
                                data-acao="excluir"
                                data-id="${id}"
                                data-numero="${numero}"
                            >
                                <i class="fa-solid fa-trash-can"></i>
                                Excluir
                            </button>

                        </div>
                    </td>
                </tr>
            `;
        })
        .join('');
}

async function carregarSenhas() {
    if (carregando || chamando) {
        return;
    }

    carregando = true;
    botaoAtualizar.disabled = true;

    try {
        const resposta = await fetch(
            '/safe-health/modules/atendimento/triagem/buscar.php',
            {
                headers: {
                    Accept: 'application/json'
                },

                cache: 'no-store'
            }
        );

        const resultado =
            await lerRespostaJson(resposta);

        if (!resposta.ok || !resultado.sucesso) {
            throw new Error(
                resultado.mensagem ||
                'Não foi possível carregar as senhas.'
            );
        }

        const senhas =
            resultado.dados?.senhas ?? [];

        renderizarTabela(senhas);

        ultimaAtualizacao.textContent =
            new Date().toLocaleTimeString(
                'pt-BR',
                {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                }
            );

        if (
            senhaAtualId !== null &&
            !senhas.some(
                (senha) =>
                    Number(senha.id) === senhaAtualId
            )
        ) {
            limparSelecao();
        }

    } catch (erro) {
        console.error(erro);

        tabelaSenhas.innerHTML = `
            <tr>
                <td
                    colspan="5"
                    class="empty-row error"
                >
                    Não foi possível carregar as senhas.
                </td>
            </tr>
        `;

        mostrarMensagem(
            erro instanceof Error
                ? erro.message
                : 'Não foi possível carregar as senhas.',
            'error'
        );
    } finally {
        carregando = false;
        botaoAtualizar.disabled = false;
    }
}

async function chamarSenha(
    id,
    numero,
    botao
) {
    if (chamando) {
        return;
    }

    const senhaIdNumerico = Number(id);
    const sala = String(
        salaTriagem?.value ?? ''
    ).trim();

    if (
        !Number.isInteger(senhaIdNumerico) ||
        senhaIdNumerico <= 0
    ) {
        mostrarMensagem(
            'A senha informada é inválida.',
            'error'
        );

        return;
    }

    if (sala === '') {
        mostrarMensagem(
            'Selecione a sala de triagem.',
            'error'
        );

        return;
    }

    chamando = true;

    const htmlOriginal = botao.innerHTML;

    botao.disabled = true;
    botao.innerHTML = `
        <i class="fa-solid fa-spinner fa-spin"></i>
        Chamando
    `;

    try {
        const resposta = await fetch(
            '/safe-health/modules/atendimento/triagem/chamar-senha.php',
            {
                method: 'POST',

                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json'
                },

                body: JSON.stringify({
                    senha_id: senhaIdNumerico,
                    sala
                })
            }
        );

        const resultado =
            await lerRespostaJson(resposta);

        if (!resposta.ok || !resultado.sucesso) {
            throw new Error(
                resultado.mensagem ||
                'Não foi possível chamar a senha.'
            );
        }

        mostrarMensagem(
            `Senha ${resultado.dados.numero ?? numero} chamada para ${resultado.dados.sala ?? sala}.`
        );

        botao.innerHTML = `
            <i class="fa-solid fa-volume-high"></i>
            Rechamar
        `;

    } catch (erro) {
        console.error(erro);

        mostrarMensagem(
            erro instanceof Error
                ? erro.message
                : 'Não foi possível chamar a senha.',
            'error'
        );

        botao.innerHTML = htmlOriginal;
    } finally {
        chamando = false;
        botao.disabled = false;
    }
}

async function excluirSenhaTriagem(
    senhaIdValor,
    numero
) {
    const id = Number(senhaIdValor);

    if (!Number.isInteger(id) || id <= 0) {
        mostrarMensagem(
            'Senha inválida.',
            'error'
        );
        return;
    }

    const confirmou = window.confirm(
        `Tem certeza que deseja excluir a senha ${numero}?\n\n` +
        'Esta ação só será permitida enquanto a senha estiver aguardando a triagem.'
    );

    if (!confirmou) {
        return;
    }

    try {
        const resposta = await fetch(
            '/safe-health/modules/atendimento/triagem/excluir-senha.php',
            {
                method: 'POST',

                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json'
                },

                body: JSON.stringify({
                    senha_id: id
                })
            }
        );

        const resultado =
            await lerRespostaJson(resposta);

        if (!resposta.ok || !resultado.sucesso) {
            throw new Error(
                resultado.mensagem ||
                'Não foi possível excluir a senha.'
            );
        }

        if (senhaAtualId === id) {
            limparSelecao();
        }

        mostrarMensagem(
            resultado.mensagem ||
            `Senha ${numero} excluída com sucesso.`
        );

        await carregarSenhas();

    } catch (erro) {
        console.error(erro);

        mostrarMensagem(
            erro instanceof Error
                ? erro.message
                : 'Não foi possível excluir a senha.',
            'error'
        );
    }
}

async function finalizarTriagem(evento) {
    evento.preventDefault();

    if (salvando) {
        return;
    }

    if (!senhaAtualId) {
        mostrarMensagem(
            'Selecione uma senha antes de finalizar.',
            'error'
        );

        return;
    }

    if (!formTriagem.reportValidity()) {
        return;
    }

    const dadosFormulario =
        new FormData(formTriagem);

    const dados = Object.fromEntries(
        dadosFormulario.entries()
    );

    dados.senha_id = senhaAtualId;

    salvando = true;
    botaoFinalizar.disabled = true;

    try {
        const resposta = await fetch(
            '/safe-health/modules/atendimento/triagem/salvar.php',
            {
                method: 'POST',

                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json'
                },

                body: JSON.stringify(dados)
            }
        );

        const resultado =
            await lerRespostaJson(resposta);

        if (!resposta.ok || !resultado.sucesso) {
            throw new Error(
                resultado.mensagem ||
                'Não foi possível finalizar a triagem.'
            );
        }

        mostrarMensagem(
            resultado.mensagem ||
            'Triagem finalizada com sucesso.'
        );

        limparSelecao();
        await carregarSenhas();

    } catch (erro) {
        console.error(erro);

        mostrarMensagem(
            erro instanceof Error
                ? erro.message
                : 'Não foi possível finalizar a triagem.',
            'error'
        );
    } finally {
        salvando = false;
        botaoFinalizar.disabled = false;
    }
}

tabelaSenhas.addEventListener(
    'click',
    (evento) => {
        const botao = evento.target.closest(
            'button[data-acao]'
        );

        if (!botao || botao.disabled) {
            return;
        }

        if (botao.dataset.acao === 'chamar') {
            chamarSenha(
                botao.dataset.id,
                botao.dataset.numero,
                botao
            );

            return;
        }

        if (botao.dataset.acao === 'triar') {
            selecionarSenha(
                botao.dataset.id,
                botao.dataset.numero,
                botao.dataset.fila
            );

            return;
        }

        if (botao.dataset.acao === 'excluir') {
            excluirSenhaTriagem(
                botao.dataset.id,
                botao.dataset.numero
            );
        }
    }
);

botaoAtualizar.addEventListener(
    'click',
    carregarSenhas
);

botaoCancelar.addEventListener(
    'click',
    limparSelecao
);

formTriagem.addEventListener(
    'submit',
    finalizarTriagem
);

carregarSenhas();

window.setInterval(
    carregarSenhas,
    5000
);
