<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/seguranca.php';

$usuarioId=(int)$_SESSION['usuario_id'];
$erro='';
$sucesso='';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $atual=(string)($_POST['senha_atual'] ?? '');
    $nova=(string)($_POST['nova_senha'] ?? '');
    $confirmar=(string)($_POST['confirmar_senha'] ?? '');

    $stmt=$pdo->prepare("SELECT senha FROM usuarios WHERE id=:id LIMIT 1");
    $stmt->execute([':id'=>$usuarioId]);
    $hashAtual=(string)$stmt->fetchColumn();

    if (!password_verify($atual,$hashAtual)) {
        $erro='A senha atual está incorreta.';
        shRegistrarAuditoria($pdo,'TROCA_SENHA_FALHA','Falha ao confirmar a senha atual.',$usuarioId,'Autenticação','Troca de senha');
    } elseif ($nova!==$confirmar) {
        $erro='A confirmação da nova senha não confere.';
    } else {
        $erros=shValidarSenha($pdo,$nova,$usuarioId);
        if ($erros) {
            $erro=implode(' ', $erros);
        } else {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("
                    INSERT INTO usuario_senhas_historico (usuario_id,senha_hash,criado_em)
                    VALUES (:id,:hash,NOW())
                ")->execute([':id'=>$usuarioId,':hash'=>$hashAtual]);

                $novoHash=password_hash($nova,PASSWORD_DEFAULT);
                $pdo->prepare("
                    UPDATE usuarios
                    SET senha=:senha,
                        troca_senha_obrigatoria=0,
                        senha_alterada_em=NOW()
                    WHERE id=:id
                ")->execute([':senha'=>$novoHash,':id'=>$usuarioId]);

                $pdo->commit();
                $_SESSION['troca_senha_obrigatoria']=false;
                shRegistrarAuditoria($pdo,'TROCA_SENHA','Senha alterada com sucesso.',$usuarioId,'Autenticação','Troca de senha','usuarios',$usuarioId);
                $sucesso='Senha alterada com sucesso.';
            } catch(Throwable $e) {
                if($pdo->inTransaction()) $pdo->rollBack();
                $erro='Não foi possível alterar a senha.';
                error_log($e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Trocar senha | Safe Health</title>
<link rel="stylesheet" href="/safe-health/assets/css/security.css">
</head>
<body class="sec-auth-body">
<main class="sec-password-card">
<div class="sec-shield">SH</div>
<span class="sec-kicker">Segurança da conta</span>
<h1><?= !empty($_GET['primeiro_acesso']) ? 'Crie sua nova senha' : 'Alterar senha' ?></h1>
<p><?= !empty($_GET['primeiro_acesso']) ? 'Por segurança, a senha temporária deve ser substituída antes de usar o Safe Health.' : 'Atualize sua senha de acesso.' ?></p>
<?php if($erro): ?><div class="sec-alert error"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
<?php if($sucesso): ?><div class="sec-alert success"><?= htmlspecialchars($sucesso) ?> <a href="/safe-health/">Continuar</a></div><?php endif; ?>
<form method="post">
<label>Senha atual<input type="password" name="senha_atual" required autocomplete="current-password"></label>
<label>Nova senha<input type="password" name="nova_senha" required autocomplete="new-password"></label>
<label>Confirmar nova senha<input type="password" name="confirmar_senha" required autocomplete="new-password"></label>
<div class="sec-policy">
<strong>A nova senha deve conter:</strong>
<span>10+ caracteres • maiúscula • minúscula • número • caractere especial</span>
</div>
<button type="submit">Salvar nova senha</button>
</form>
<?php if(empty($_SESSION['troca_senha_obrigatoria'])): ?><a class="sec-back" href="/safe-health/">Voltar ao sistema</a><?php endif; ?>
</main>
</body></html>
