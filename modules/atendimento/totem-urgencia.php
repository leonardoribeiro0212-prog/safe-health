<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissoes.php';

exigirPermissao(
    $pdo,
    'atendimento.totem_urgencia'
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

    <title>Totem de Urgência | Safe Health</title>

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
            <div class="totem-logo">SH</div>

            <div>
                <strong>Safe Health</strong>
                <span>Totem de Urgência</span>
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
                Atendimento de urgência
            </span>

            <h1>Como podemos ajudá-lo?</h1>

            <p>
                Selecione abaixo o tipo de pronto-socorro
                para retirar sua senha de atendimento.
            </p>
        </section>

        <section class="totem-options">

            <button
                type="button"
                class="totem-option"
                data-fila="PS_ADULTO"
            >
                <span class="totem-option-icon">
                    <i class="fa-solid fa-hospital-user"></i>
                </span>

                <strong>Pronto-Socorro Adulto</strong>

                <span>
                    Atendimento de urgência destinado
                    a pacientes adultos.
                </span>
            </button>

            <button
                type="button"
                class="totem-option"
                data-fila="PS_INFANTIL"
            >
                <span class="totem-option-icon">
                    <i class="fa-solid fa-child-reaching"></i>
                </span>

                <strong>Pronto-Socorro Infantil</strong>

                <span>
                    Atendimento pediátrico de urgência
                    para crianças e adolescentes.
                </span>
            </button>

            <button
                type="button"
                class="totem-option"
                data-fila="PS_ORTOPEDIA"
            >
                <span class="totem-option-icon">
                    <i class="fa-solid fa-bone"></i>
                </span>

                <strong>Pronto-Socorro Ortopedia</strong>

                <span>
                    Atendimento para traumas, quedas,
                    dores e lesões ortopédicas.
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
        Operador: <?= htmlspecialchars($nomeUsuario) ?>
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

        <h2 id="tituloModal">Senha emitida</h2>

        <p>
            Aguarde sua senha ser chamada no painel.
        </p>

        <div class="senha-gerada">
            <small>Sua senha</small>

            <strong id="numeroSenha">---</strong>

            <span id="nomeFila">Atendimento</span>
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
const numeroSenha = document.getElementById('numeroSenha');
const nomeFila = document.getElementById('nomeFila');
const horarioSenha = document.getElementById('horarioSenha');
const mensagemErro = document.getElementById('mensagemErro');
const botaoNovaSenha = document.getElementById('botaoNovaSenha');
const botaoImprimir = document.getElementById('botaoImprimir');

let gerandoSenha = false;

function bloquearBotoes(bloquear) {
    botoesFila.forEach((botao) => {
        botao.disabled = bloquear;
    });
}

async function gerarSenha(codigoFila) {
    if (gerandoSenha || !codigoFila) {
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
                    fila: codigoFila,
                    prioridade: 'NORMAL',
                    origem: 'URGENCIA'
                })
            }
        );

        const textoResposta = await resposta.text();

        console.log('Resposta da API:', textoResposta);

        let resultado;

        try {
            resultado = JSON.parse(textoResposta);
        } catch (erroJson) {
            throw new Error(
                'A API retornou uma resposta inválida: ' +
                textoResposta
            );
        }

        if (!resposta.ok || !resultado.sucesso) {
            console.error(
                'Erro detalhado da API:',
                resultado
            );

            throw new Error(
                resultado.erro ||
                resultado.mensagem ||
                'Não foi possível gerar a senha.'
            );
        }

        const dados = resultado.dados || {};

        numeroSenha.textContent =
            dados.numero ||
            dados.numero_senha ||
            dados.senha ||
            '---';

        nomeFila.textContent =
            dados.fila ||
            dados.nome_fila ||
            'Atendimento de urgência';

        horarioSenha.textContent =
            dados.emitida_em
                ? `Emitida em ${dados.emitida_em}`
                : '';

        modalSenha.classList.add('visible');
    } catch (erro) {
        console.error('Erro ao gerar senha:', erro);

        mensagemErro.textContent =
            erro.message ||
            'Ocorreu um erro inesperado.';
    } finally {
        gerandoSenha = false;
        bloquearBotoes(false);
    }
}

botoesFila.forEach((botao) => {
    botao.addEventListener('click', () => {
        const codigoFila = botao.dataset.fila;

        gerarSenha(codigoFila);
    });
});

botaoNovaSenha.addEventListener('click', () => {
    modalSenha.classList.remove('visible');

    numeroSenha.textContent = '---';
    nomeFila.textContent = 'Atendimento';
    horarioSenha.textContent = '';
    mensagemErro.textContent = '';
});

botaoImprimir.addEventListener('click', () => {
    window.print();
});
</script>

</body>
</html>