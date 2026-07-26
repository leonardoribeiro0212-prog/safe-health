<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$email = trim($_POST['email'] ?? '');
$senha = $_POST['senha'] ?? '';

$emailCorreto = 'admin@safehealth.com';
$senhaCorreta = '123456';

if ($email === $emailCorreto && $senha === $senhaCorreta) {
    $_SESSION['usuario_id'] = 1;
    $_SESSION['usuario_nome'] = 'Administrador';
    $_SESSION['usuario_perfil'] = 'Administrador';

    header('Location: index.php');
    exit;
}

header('Location: login.php?erro=credenciais');
exit;