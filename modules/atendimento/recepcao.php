<?php

$tituloPagina = 'Recepção';
$codigoTela = 'atendimento.recepcao';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="app-content recepcao-layout">
    <?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

    <main class="main-content">
        <link
            rel="stylesheet"
            href="/safe-health/assets/css/recepcao.css"
        >

        <section class="recepcao-page">

            <section class="summary-grid">

                <article class="summary-card">
                    <div class="summary-icon waiting">
                        <i class="fa-solid fa-users"></i>
                    </div>

                    <div>
                        <span>Aguardando</span>
                        <strong id="totalAguardando">0</strong>
                    </div>
                </article>

                <article class="summary-card">
                    <div class="summary-icon current">
                        <i class="fa-solid fa-bullhorn"></i>
                    </div>

                    <div>
                        <span>Senha chamada</span>
                        <strong id="senhaAtual">---</strong>
                    </div>
                </article>

                <article class="summary-card">
                    <div class="summary-icon update">
                        <i class="fa-solid fa-clock"></i>
                    </div>

                    <div>
                        <span>Última atualização</span>
                        <strong
                            class="update-time"
                            id="ultimaAtualizacao"
                        >
                            --:--:--
                        </strong>
                    </div>
                </article>

            </section>

            <section class="queue-section">

                <div class="section-toolbar">

                    <div class="tabs">
                        <button
                            type="button"
                            class="tab active"
                            data-tipo="URGENCIA"
                        >
                            <i class="fa-solid fa-truck-medical"></i>
                            Urgência
                        </button>

                        <button
                            type="button"
                            class="tab"
                            data-tipo="AMBULATORIO"
                        >
                            <i class="fa-solid fa-calendar-check"></i>
                            Ambulatório
                        </button>
                    </div>

                    <div class="reception-actions">

                        <div class="guiche-field">
                            <label for="guiche">Guichê</label>

                            <select id="guiche">
                                <option value="Guichê 01">Guichê 01</option>
                                <option value="Guichê 02">Guichê 02</option>
                                <option value="Guichê 03">Guichê 03</option>
                                <option value="Guichê 04">Guichê 04</option>
                            </select>
                        </div>

                        <button
                            type="button"
                            class="refresh-button"
                            id="botaoAtualizar"
                        >
                            <i class="fa-solid fa-rotate"></i>
                            Atualizar
                        </button>

                    </div>

                </div>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Senha</th>
                                <th>Fila</th>
                                <th>Status</th>
                                <th>Emissão</th>
                                <th>Ações</th>
                            </tr>
                        </thead>

                        <tbody id="tabelaSenhas">
                            <tr>
                                <td colspan="5" class="empty-row">
                                    Carregando senhas...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </section>

        </section>

        <div
            class="recepcao-toast"
            id="toast"
        ></div>

<script>
const tabelaSenhas =
    document.getElementById('tabelaSenhas');

const totalAguardando =
    document.getElementById('totalAguardando');

const senhaAtual =
    document.getElementById('senhaAtual');

const ultimaAtualizacao =
    document.getElementById('ultimaAtualizacao');

const campoGuiche =
    document.getElementById('guiche');

const botaoAtualizar =
    document.getElementById('botaoAtualizar');

const toast =
    document.getElementById('toast');

let tipoAtual = 'URGENCIA';
let carregando = false;

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
    toast.className = `recepcao-toast show ${tipo}`;

    window.setTimeout(() => {
        toast.className = 'recepcao-toast';
    }, 3500);
}

function formatarStatus(status) {
    const nomes = {
        AGUARDANDO: 'Aguardando',
        CHAMADA_RECEPCAO: 'Chamada'
    };

    return nomes[status] ?? status;
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
                    Nenhuma senha aguardando atendimento.
                </td>
            </tr>
        `;

        totalAguardando.textContent = '0';

        return;
    }

    totalAguardando.textContent =
        String(senhas.length);

    const senhaChamada = senhas.find(
        (senha) =>
            senha.status === 'CHAMADA_RECEPCAO'
    );

    senhaAtual.textContent =
        senhaChamada?.numero ?? '---';

    tabelaSenhas.innerHTML = senhas
        .map((senha) => {
            const chamada =
                senha.status === 'CHAMADA_RECEPCAO';

            const textoBotao =
                chamada ? 'Rechamar' : 'Chamar';

            const iconeBotao =
                chamada
                    ? 'fa-volume-high'
                    : 'fa-bullhorn';

            const classeStatus =
                chamada
                    ? 'called'
                    : 'waiting';

            return `
                <tr class="${chamada ? 'current-row' : ''}">

                    <td>
                        <strong class="ticket-number">
                            ${escaparHtml(senha.numero)}
                        </strong>
                    </td>

                    <td>
                        <div class="queue-name">
                            ${escaparHtml(senha.fila)}
                        </div>

                        <small>
                            ${escaparHtml(senha.codigo_fila)}
                        </small>
                    </td>

                    <td>
                        <span class="status ${classeStatus}">
                            ${escaparHtml(
                                formatarStatus(senha.status)
                            )}
                        </span>
                    </td>

                    <td>
                        ${escaparHtml(
                            formatarData(
                                senha.emitida_em ??
                                senha.data_emissao
                            )
                        )}
                    </td>

                    <td>
                        <div class="action-buttons">

                            <button
                                type="button"
                                class="call-button"
                                onclick="chamarSenha(
                                    ${Number(senha.id)}
                                )"
                            >
                                <i class="fa-solid ${iconeBotao}"></i>
                                ${textoBotao}
                            </button>

                            <button
                                type="button"
                                class="service-button"
                                ${chamada ? '' : 'disabled'}
                                onclick="iniciarAtendimento(
                                    ${Number(senha.id)}
                                )"
                            >
                                <i class="fa-solid fa-arrow-right"></i>
                                Atender
                            </button>

                        </div>
                    </td>

                </tr>
            `;
        })
        .join('');
}

async function carregarSenhas() {
    if (carregando) {
        return;
    }

    carregando = true;
    botaoAtualizar.disabled = true;

    try {
        const resposta = await fetch(
            `/safe-health/modules/atendimento/api/listar-senhas-recepcao.php?tipo=${encodeURIComponent(tipoAtual)}`,
            {
                headers: {
                    Accept: 'application/json'
                },
                cache: 'no-store'
            }
        );

        const resultado = await resposta.json();

        if (!resposta.ok || !resultado.sucesso) {
            throw new Error(
                resultado.mensagem ||
                'Erro ao carregar as senhas.'
            );
        }

        renderizarTabela(
            resultado.dados.senhas
        );

        ultimaAtualizacao.textContent =
            resultado.dados.atualizado_em
                .split(' ')[1];

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
    } finally {
        carregando = false;
        botaoAtualizar.disabled = false;
    }
}

async function chamarSenha(senhaId) {
    const guiche = campoGuiche.value;

    try {
        const resposta = await fetch(
            '/safe-health/modules/atendimento/api/chamar-senha-recepcao.php',
            {
                method: 'POST',

                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json'
                },

                body: JSON.stringify({
                    senha_id: senhaId,
                    guiche
                })
            }
        );

        const resultado = await resposta.json();

        if (!resposta.ok || !resultado.sucesso) {
            throw new Error(
                resultado.mensagem ||
                'Erro ao chamar a senha.'
            );
        }

        senhaAtual.textContent =
            resultado.dados.numero;

        mostrarMensagem(
            `${resultado.dados.numero} chamada para ${resultado.dados.guiche}.`
        );

        await carregarSenhas();

    } catch (erro) {
        console.error(erro);

        mostrarMensagem(
            erro.message,
            'error'
        );
    }
}
function iniciarAtendimento(senhaId) {
    const id = Number(senhaId);

    if (!Number.isInteger(id) || id <= 0) {
        console.error('ID da senha inválido:', senhaId);
        return;
    }

    window.location.href =
        `/safe-health/modules/atendimento/atender.php?senha_id=${id}`;
}

document
    .querySelectorAll('.tab')
    .forEach((botao) => {
        botao.addEventListener(
            'click',
            () => {
                document
                    .querySelectorAll('.tab')
                    .forEach((item) => {
                        item.classList.remove('active');
                    });

                botao.classList.add('active');

                tipoAtual =
                    botao.dataset.tipo;

                senhaAtual.textContent = '---';

                carregarSenhas();
            }
        );
    });

botaoAtualizar.addEventListener(
    'click',
    carregarSenhas
);

carregarSenhas();

window.setInterval(
    carregarSenhas,
    5000
);
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>