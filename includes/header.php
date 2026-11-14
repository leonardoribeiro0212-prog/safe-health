<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/permissoes.php';

$tituloPagina = $tituloPagina ?? 'Safe Health';
$codigoTela = $codigoTela ?? null;

if ($codigoTela !== null) {
    exigirPermissao($pdo, $codigoTela);
}

$telasUsuario = buscarMenuUsuario(
    $pdo,
    (int) $_SESSION['usuario_id']
);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= htmlspecialchars($tituloPagina) ?> | Safe Health
    </title>

    <link
        rel="stylesheet"
        href="/safe-health/assets/css/app.css?v=5"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >
</head>

<body>
    <div class="app">