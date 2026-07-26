<?php

require_once __DIR__ . '/../config/database.php';

/**
 * Verifica se o usuário possui acesso a uma tela.
 */
function usuarioTemPermissao(PDO $pdo, int $usuarioId, string $codigoTela): bool
{
    $sql = "
        SELECT COUNT(*)
        FROM usuario_papel up
        INNER JOIN papel_tela pt
            ON pt.papel_id = up.papel_id
        INNER JOIN telas t
            ON t.id = pt.tela_id
        WHERE up.usuario_id = :usuario_id
          AND t.codigo = :codigo_tela
          AND t.ativa = 1
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':usuario_id' => $usuarioId,
        ':codigo_tela' => $codigoTela
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

/**
 * Impede a abertura da página caso o usuário não tenha permissão.
 */
function exigirPermissao(PDO $pdo, string $codigoTela): void
{
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: /safe-health/login.php');
        exit;
    }

    if (!usuarioTemPermissao($pdo, (int) $_SESSION['usuario_id'], $codigoTela)) {
        header('Location: /safe-health/acesso-negado.php');
        exit;
    }
}

/**
 * Retorna as telas liberadas para o menu do usuário.
 */
function buscarMenuUsuario(PDO $pdo, int $usuarioId): array
{
    $sql = "
        SELECT DISTINCT
            t.id,
            t.nome,
            t.codigo,
            t.modulo,
            t.grupo_menu,
            t.url,
            t.icone,
            t.ordem,
            t.abrir_nova_aba
        FROM telas t
        INNER JOIN papel_tela pt
            ON pt.tela_id = t.id
        INNER JOIN usuario_papel up
            ON up.papel_id = pt.papel_id
        WHERE up.usuario_id = :usuario_id
          AND t.ativa = 1
        ORDER BY t.ordem ASC, t.nome ASC
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':usuario_id' => $usuarioId
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}