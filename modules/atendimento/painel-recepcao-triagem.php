<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissoes.php';

exigirPermissao(
    $pdo,
    'atendimento.painel_urgencia'
);
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
        Painel de Recepção e Triagem | Safe Health
    </title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="/safe-health/assets/css/painel-atendimento.css"
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
                    Painel de Recepção e Triagem
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

        <section class="painel-calls">

            <article
                class="call-card recepcao"
                id="cardRecepcao"
            >

                <div class="call-card-header">

                    <i class="fa-solid fa-desktop"></i>

                    <span>Recepção</span>

                </div>

                <div class="call-content">

                    <span class="call-label">
                        Senha
                    </span>

                    <strong
                        class="call-number"
                        id="senhaRecepcao"
                    >
                        ---
                    </strong>

                    <span
                        class="call-queue"
                        id="filaRecepcao"
                    >
                        Aguardando chamada
                    </span>

                    <div
                        class="call-location"
                        id="localRecepcao"
                    >
                        Recepção
                    </div>

                </div>

            </article>

            <article
                class="call-card triagem"
                id="cardTriagem"
            >

                <div class="call-card-header">

                    <i class="fa-solid fa-stethoscope"></i>

                    <span>Triagem</span>

                </div>

                <div class="call-content">

                    <span class="call-label">
                        Senha
                    </span>

                    <strong
                        class="call-number"
                        id="senhaTriagem"
                    >
                        ---
                    </strong>

                    <span
                        class="call-queue"
                        id="filaTriagem"
                    >
                        Aguardando chamada
                    </span>

                    <div
                        class="call-location"
                        id="localTriagem"
                    >
                        Sala de triagem
                    </div>

                </div>

            </article>

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
const senhaRecepcao =
    document.getElementById('senhaRecepcao');

const filaRecepcao =
    document.getElementById('filaRecepcao');

const localRecepcao =
    document.getElementById('localRecepcao');

const senhaTriagem =
    document.getElementById('senhaTriagem');

const filaTriagem =
    document.getElementById('filaTriagem');

const localTriagem =
    document.getElementById('localTriagem');

const listaHistorico =
    document.getElementById('listaHistorico');

const ultimaAtualizacao =
    document.getElementById('ultimaAtualizacao');

const cardRecepcao =
    document.getElementById('cardRecepcao');

const cardTriagem =
    document.getElementById('cardTriagem');

let ultimaChamadaRecepcao = null;
let ultimaChamadaTriagem = null;

function escaparHtml(valor) {
    return String(valor ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function preencherChamada(
    dados,
    elementos,
    textoPadrao
) {
    if (!dados) {
        elementos.senha.textContent = '---';
        elementos.fila.textContent = 'Aguardando chamada';
        elementos.local.textContent = textoPadrao;

        return;
    }

    elementos.senha.textContent =
        dados.numero;

    elementos.fila.textContent =
        dados.fila;

    elementos.local.textContent =
        dados.local_chamada;
}

function destacarCard(card) {
    card.classList.remove('new-call');

    void card.offsetWidth;

    card.classList.add('new-call');
}

function atualizarHistorico(historico) {
    if (!Array.isArray(historico) ||
        historico.length === 0) {

        listaHistorico.innerHTML = `
            <div class="history-empty">
                Nenhuma chamada realizada hoje.
            </div>
        `;

        return;
    }

    listaHistorico.innerHTML = historico
        .map((item) => {
            const etapa =
                item.etapa === 'TRIAGEM'
                    ? 'Triagem'
                    : 'Recepção';

            return `
                <div class="history-item">

                    <strong>
                        ${escaparHtml(item.numero)}
                    </strong>

                    <span>
                        ${escaparHtml(item.fila)}
                    </span>

                    <span class="history-stage">
                        ${etapa}
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
            '/safe-health/modules/atendimento/api/buscar-painel-recepcao-triagem.php',
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

        const recepcao = resultado.dados.recepcao;
        const triagem = resultado.dados.triagem;

        preencherChamada(
            recepcao,
            {
                senha: senhaRecepcao,
                fila: filaRecepcao,
                local: localRecepcao
            },
            'Recepção'
        );

        preencherChamada(
            triagem,
            {
                senha: senhaTriagem,
                fila: filaTriagem,
                local: localTriagem
            },
            'Sala de triagem'
        );

        if (
            recepcao &&
            recepcao.chamada_id !== ultimaChamadaRecepcao
        ) {
            if (ultimaChamadaRecepcao !== null) {
                destacarCard(cardRecepcao);
            }

            ultimaChamadaRecepcao =
                recepcao.chamada_id;
        }

        if (
            triagem &&
            triagem.chamada_id !== ultimaChamadaTriagem
        ) {
            if (ultimaChamadaTriagem !== null) {
                destacarCard(cardTriagem);
            }

            ultimaChamadaTriagem =
                triagem.chamada_id;
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