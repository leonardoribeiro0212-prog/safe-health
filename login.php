<?php
session_start();
if (isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}
$erro=$_GET['erro'] ?? '';
$mensagens=[
    'credenciais'=>'Usuário/e-mail ou senha inválidos.',
    'acesso'=>'Faça login para acessar o sistema.',
    'bloqueado'=>'Acesso temporariamente bloqueado após várias tentativas inválidas. Aguarde alguns minutos.',
    'expirada'=>'Sua sessão expirou por inatividade. Entre novamente.',
    'sessao'=>'Sua sessão foi encerrada porque houve um novo acesso à conta.',
    'interno'=>'Não foi possível concluir o login. Tente novamente.'
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Safe Health | Login Seguro</title>
<link rel="stylesheet" href="assets/css/login.css">
<style>
.security-note{display:flex;gap:9px;align-items:flex-start;padding:10px 12px;background:#eef8f7;border:1px solid #cfe9e5;border-radius:9px;color:#315f59;font-size:12px;margin:12px 0}
.security-note strong{display:block;margin-bottom:2px}
</style>
</head>
<body>
<main class="login-container">
<section class="login-banner"><div class="banner-content"><h1>Safe Health</h1><p>Gestão integrada para processos hospitalares, atendimento e prontuário eletrônico.</p></div></section>
<section class="login-area">
<div class="login-card">
<div class="logo"><div class="logo-icon">+</div><div><strong>Safe Health</strong><span>Gestão Hospitalar</span></div></div>
<div class="login-title"><h2>Acesso ao sistema</h2><p>Informe suas credenciais para continuar.</p></div>
<?php if(isset($mensagens[$erro])): ?><div class="alerta erro"><?= htmlspecialchars($mensagens[$erro]) ?></div><?php endif; ?>
<div class="security-note"><span>🔒</span><div><strong>Acesso protegido</strong>Contas podem ser bloqueadas temporariamente após tentativas inválidas.</div></div>
<form action="processa_login.php" method="POST" autocomplete="on">
<div class="form-group">
<label for="identificador">Usuário ou e-mail</label>
<input type="text" id="identificador" name="identificador" placeholder="admin ou admin@safehealth.com" autocomplete="username" required>
</div>
<div class="form-group">
<label for="senha">Senha</label>
<div class="senha-container">
<input type="password" id="senha" name="senha" placeholder="Digite sua senha" autocomplete="current-password" required>
<button type="button" id="mostrarSenha" class="mostrar-senha">Mostrar</button>
</div>
</div>
<button type="submit" class="btn-entrar">Entrar no sistema</button>
</form>
<footer class="login-footer"><span>Trabalho de Conclusão de Curso</span></footer>
</div>
</section>
</main>
<script src="assets/js/login.js"></script>
</body>
</html>
