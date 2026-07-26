<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissoes.php';

exigirPermissao(
    $pdo,
    'atendimento.totem_ambulatorio'
);

$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Totem de Ambulatório | Safe Health</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="/safe-health/assets/css/totem.css"
    >
</head>

<body class="totem-body">

<div class="totem-page">

    <header class="totem-header">

        <div class="totem-brand">

            <div class="totem-logo">
                SH
            </div>

            <div>
                <strong>Safe Health</strong>
                <span>Totem de Ambulatório</span>
            </div>

        </div>

        <a
            href="/safe-health/index.php"
            class="totem-exit"
            title="Voltar ao sistema"
        >
            <i class="fa-solid fa-arrow-left"></i>
        </a>

    </header>

    <main class="totem-main">

        <section class="totem-intro">

            <span class="totem-label">
                Atendimento ambulatorial
            </span>

            <h1>Como podemos ajudá-lo?</h1>

            <p>
                Selecione abaixo o serviço desejado
                para retirar sua senha de atendimento.
            </p>

        </section>

        <section class="totem-options">

            <button
                type="button"
                class="totem-option"
                data-fila="AGENDAMENTO"
            >
                <span class="totem-option-icon">
                    <i class="fa-solid fa-calendar-check"></i>
                </span>

                <strong>Agendamento</strong>

                <span>
                    Agendamento, alteração ou cancelamento
                    de consultas e procedimentos.
                </span>
            </button>

            <button
                type="button"
                class="totem-option"
                data-fila="CONSULTA"
            >
                <span class="totem-option-icon">
                    <i class="fa-solid fa-user-doctor"></i>
                </span>

                <strong>Consulta</strong>

                <span>
                    Atendimento para pacientes com
                    consulta ambulatorial agendada.
                </span>
            </button>

            <button
                type="button"
                class="totem-option"
                data-fila="EXAMES"
            >
                <span class="totem-option-icon">
                    <i class="fa-solid fa-microscope"></i>
                </span>

                <strong>Exames</strong>

                <span>
                    Atendimento para realização, entrega
                    ou orientação sobre exames.
                </span>
            </button>

        </section>

        <div
            id="mensagemErro"
            class="totem-message"
            aria-live="polite"
        ></div>

    </main>

    <footer class="totem-footer">
        Safe Health
        Operador: <?= htmlspecialchars(
            $nomeUsuario,
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </footer>

</div>

<div
    id="modalSenha"
    class="totem-modal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="tituloModal"
>

    <div class="totem-modal-card">

        <div class="modal-success-icon">
            <i class="fa-solid fa-check"></i>
        </div>

        <h2 id="tituloModal">
            Senha emitida
        </h2>

        <p>
            Aguarde sua senha ser chamada no painel.
        </p>

        <div class="senha-gerada">

            <small>Sua senha</small>

            <strong id="numeroSenha">
                ---
            </strong>

            <span id="nomeFila">
                Atendimento ambulatorial
            </span>

        </div>

        <p id="horarioSenha"></p>

        <div class="modal-actions">

            <button
                type="button"
                class="modal-button secondary"
                id="botaoNovaSenha"
            >
                Nova senha
            </button>

            <button
                type="button"
                class="modal-button primary"
                id="botaoImprimir"
            >
                <i class="fa-solid fa-print"></i>
                Imprimir
            </button>

        </div>

    </div>

</div>
<script>
const botoesFila = document.querySelectorAll('[data-fila]');

const modalSenha = document.getElementById('modalSenha');
const numeroSenhaElemento = document.getElementById('numeroSenha');
const nomeFila = document.getElementById('nomeFila');
const horarioSenha = document.getElementById('horarioSenha');
const mensagemErro = document.getElementById('mensagemErro');

const botaoNovaSenha =
    document.getElementById('botaoNovaSenha');

const botaoImprimir =
    document.getElementById('botaoImprimir');

let gerandoSenha = false;

function bloquearBotoes(bloquear) {
    botoesFila.forEach((botao) => {
        botao.disabled = bloquear;
    });
}

function mostrarSenhaGerada(dados) {
    const resultado = dados.dados || dados;

    numeroSenhaElemento.textContent =
        resultado.numero ||
        resultado.numero_senha ||
        resultado.senha ||
        '---';

    nomeFila.textContent =
        resultado.nome_fila ||
        resultado.fila ||
        'Atendimento ambulatorial';

    horarioSenha.textContent =
        resultado.emitida_em
            ? `Emitida em ${resultado.emitida_em}`
            : '';

    modalSenha.classList.add('visible');
}

async function gerarSenha(fila, prioridade = 'NORMAL') {
    if (gerandoSenha) {
        return;
    }

    gerandoSenha = true;
    bloquearBotoes(true);
    mensagemErro.textContent = '';

    try {
        const resposta = await fetch(
            '/safe-health/modules/atendimento/api/gerar-senha.php',
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json'
                },
                body: JSON.stringify({
                    fila: fila,
                    prioridade: prioridade,
                    origem: 'AMBULATORIO'
                })
            }
        );

        const textoResposta = await resposta.text();

        console.log('Resposta da API:', textoResposta);

        let dados;

        try {
            dados = JSON.parse(textoResposta);
        } catch (erroJson) {
            throw new Error(
                'A API retornou uma resposta inválida: ' +
                textoResposta
            );
        }

        if (!resposta.ok || !dados.sucesso) {
            console.error('Erro detalhado da API:', dados);

            throw new Error(
                dados.erro ||
                dados.mensagem ||
                'Não foi possível gerar a senha.'
            );
        }

        console.log('Senha gerada:', dados);

        mostrarSenhaGerada(dados);
    } catch (erro) {
        console.error('Erro ao gerar senha:', erro);

        mensagemErro.textContent =
            erro.message ||
            'Não foi possível gerar a senha. Tente novamente.';
    } finally {
        gerandoSenha = false;
        bloquearBotoes(false);
    }
}

botoesFila.forEach((botao) => {
    botao.addEventListener('click', () => {
        const codigoFila = botao.dataset.fila;

        if (!codigoFila) {
            return;
        }

        gerarSenha(codigoFila);
    });
});

botaoNovaSenha.addEventListener('click', () => {
    modalSenha.classList.remove('visible');

    numeroSenhaElemento.textContent = '---';
    nomeFila.textContent = 'Atendimento ambulatorial';
    horarioSenha.textContent = '';
    mensagemErro.textContent = '';
});

botaoImprimir.addEventListener('click', () => {
    window.print();
});
</script>

</body>
</html>