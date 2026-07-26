<?php
session_start();

if (isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

$erro = $_GET['erro'] ?? '';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Safe Health | Login</title>

    <link rel="stylesheet" href="assets/css/login.css">
</head>

<body>
    <main class="login-container">

        <section class="login-banner">
            <div class="banner-content">

                <h1>Safe Health</h1>

                <p>
                    Gestão integrada para processos hospitalares,
                    atendimento e prontuário eletrônico.
                </p>
            </div>
        </section>

        <section class="login-area">
            <div class="login-card">

                <div class="logo">
                    <div class="logo-icon">+</div>

                    <div>
                        <strong>Safe Health</strong>
                        <span>Gestão Hospitalar</span>
                    </div>
                </div>

                <div class="login-title">
                    <h2>Acesso ao sistema</h2>
                    <p>Informe suas credenciais para continuar.</p>
                </div>

                <?php if ($erro === 'credenciais'): ?>
                    <div class="alerta erro">
                        Usuário ou senha inválidos.
                    </div>
                <?php endif; ?>

                <?php if ($erro === 'acesso'): ?>
                    <div class="alerta erro">
                        Faça login para acessar o sistema.
                    </div>
                <?php endif; ?>

                <form action="processa_login.php" method="POST">

                    <div class="form-group">
                        <label for="email">E-mail</label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="admin@safehealth.com"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="senha">Senha</label>

                        <div class="senha-container">
                            <input
                                type="password"
                                id="senha"
                                name="senha"
                                placeholder="Digite sua senha"
                                required
                            >

                            <button
                                type="button"
                                id="mostrarSenha"
                                class="mostrar-senha"
                            >
                                Mostrar
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-entrar">
                        Entrar no sistema
                    </button>
                </form>

                <footer class="login-footer">
                    <span>Trabalho de Conclusão de Curso</span>
                </footer>

            </div>
        </section>

    </main>

    <script src="assets/js/login.js"></script>
</body>
</html>