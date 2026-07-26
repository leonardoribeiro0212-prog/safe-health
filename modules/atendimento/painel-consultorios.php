<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Painel dos Consultórios | Safe Health
    </title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="/safe-health/assets/css/painel-consultorios.css"
    >
</head>

<body>

<div class="painel-page">

    <header class="painel-header">

        <div class="painel-brand">

            <div class="painel-logo">
                SH
            </div>

            <div>
                <strong>Safe Health</strong>

                <span>
                    Painel dos Consultórios
                </span>
            </div>

        </div>

        <div class="painel-clock">

            <strong id="relogio">
                --:--:--
            </strong>

            <span id="dataAtual">
                --/--/----
            </span>

        </div>

    </header>

    <main class="painel-main">

        <section
            class="current-call"
            id="cardChamada"
        >

            <div class="current-call-header">

                <i class="fa-solid fa-user-doctor"></i>

                <span>
                    Chamada para atendimento médico
                </span>

            </div>

            <div class="current-call-content">

                <span class="call-label">
                    Senha
                </span>

                <strong
                    class="call-number"
                    id="numeroSenha"
                >
                    ---
                </strong>

                <span
                    class="call-queue"
                    id="nomeFila"
                >
                    Aguardando chamada
                </span>

                <div
                    class="call-location"
                    id="localChamada"
                >
                    Consultório
                </div>

            </div>

        </section>

        <section class="history-panel">

            <div class="history-header">

                <div>
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    Últimas chamadas
                </div>

                <span id="ultimaAtualizacao">
                    Atualizando...
                </span>

            </div>

            <div
                class="history-list"
                id="listaHistorico"
            >

                <div class="history-empty">
                    Nenhuma chamada realizada hoje.
                </div>

            </div>

        </section>

    </main>

    <footer class="painel-footer">

        <span>
            Safe Health
        </span>

        <span>
            Aguarde sua senha ser chamada
        </span>

    </footer>

</div>

<script>
const numeroSenha =
    document.getElementById('numeroSenha');

const nomeFila =
    document.getElementById('nomeFila');

const localChamada =
    document.getElementById('localChamada');

const listaHistorico =
    document.getElementById('listaHistorico');

const ultimaAtualizacao =
    document.getElementById('ultimaAtualizacao');

const cardChamada =
    document.getElementById('cardChamada');

let ultimaChamadaId = null;

function escaparHtml(valor) {
    return String(valor ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function preencherChamada(dados) {
    if (!dados) {
        numeroSenha.textContent = '---';
        nomeFila.textContent = 'Aguardando chamada';
        localChamada.textContent = 'Consultório';

        return;
    }

    numeroSenha.textContent =
        dados.numero;

    nomeFila.textContent =
        dados.fila;

    localChamada.textContent =
        dados.local_chamada;
}

function destacarChamada() {
    cardChamada.classList.remove('new-call');

    void cardChamada.offsetWidth;

    cardChamada.classList.add('new-call');
}

function atualizarHistorico(historico) {
    if (
        !Array.isArray(historico) ||
        historico.length === 0
    ) {
        listaHistorico.innerHTML = `
            <div class="history-empty">
                Nenhuma chamada realizada hoje.
            </div>
        `;

        return;
    }

    listaHistorico.innerHTML = historico
        .map((item) => {
            return `
                <div class="history-item">

                    <strong>
                        ${escaparHtml(item.numero)}
                    </strong>

                    <span>
                        ${escaparHtml(item.fila)}
                    </span>

                    <span>
                        ${escaparHtml(item.local_chamada)}
                    </span>

                    <time>
                        ${escaparHtml(item.horario)}
                    </time>

                </div>
            `;
        })
        .join('');
}

async function atualizarPainel() {
    try {
        const resposta = await fetch(
            '/safe-health/modules/atendimento/api/buscar-painel-consultorios.php',
            {
                headers: {
                    'Accept': 'application/json'
                },
                cache: 'no-store'
            }
        );

        const resultado = await resposta.json();

        if (!resposta.ok || !resultado.sucesso) {
            throw new Error(
                resultado.mensagem ||
                'Erro ao atualizar o painel.'
            );
        }

        const chamada =
            resultado.dados.chamada_atual;

        preencherChamada(chamada);

        if (
            chamada &&
            chamada.chamada_id !== ultimaChamadaId
        ) {
            if (ultimaChamadaId !== null) {
                destacarChamada();
            }

            ultimaChamadaId =
                chamada.chamada_id;
        }

        atualizarHistorico(
            resultado.dados.historico
        );

        ultimaAtualizacao.textContent =
            'Atualizado em ' +
            resultado.dados.atualizado_em;

    } catch (erro) {
        console.error(erro);

        ultimaAtualizacao.textContent =
            'Falha ao atualizar o painel';
    }
}

function atualizarRelogio() {
    const agora = new Date();

    document.getElementById('relogio').textContent =
        agora.toLocaleTimeString('pt-BR');

    document.getElementById('dataAtual').textContent =
        agora.toLocaleDateString('pt-BR');
}

atualizarRelogio();
atualizarPainel();

setInterval(atualizarRelogio, 1000);
setInterval(atualizarPainel, 2000);
</script>

</body>
</html>