<?php
declare(strict_types=1);

/**
 * Registra um evento de auditoria no Safe Health.
 *
 * Importante:
 * - Nunca envie senhas, tokens, cookies ou outros segredos em dados anteriores/novos.
 * - Para dados pessoais/sensíveis, registre apenas o necessário para rastreabilidade.
 */
function registrarAuditoria(
    PDO $pdo,
    string $acao,
    string $descricao,
    ?string $modulo = null,
    ?string $tela = null,
    ?string $entidade = null,
    int|string|null $registroId = null,
    mixed $dadosAnteriores = null,
    mixed $dadosNovos = null,
    ?int $usuarioId = null
): void {
    try {
        if ($usuarioId === null) {
            $usuarioId =
                isset($_SESSION['usuario_id'])
                    ? (int) $_SESSION['usuario_id']
                    : (
                        isset($_SESSION['id_usuario'])
                            ? (int) $_SESSION['id_usuario']
                            : null
                    );
        }

        $ip =
            $_SERVER['REMOTE_ADDR'] ??
            null;

        $metodoHttp =
            $_SERVER['REQUEST_METHOD'] ??
            null;

        $url =
            $_SERVER['REQUEST_URI'] ??
            null;

        $normalizar = static function (mixed $valor): ?string {
            if ($valor === null) {
                return null;
            }

            if (
                is_array($valor) ||
                is_object($valor)
            ) {
                return json_encode(
                    $valor,
                    JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES
                );
            }

            return (string) $valor;
        };

        $stmt = $pdo->prepare("
            INSERT INTO auditoria_sistema (
                usuario_id,
                acao,
                modulo,
                tela,
                entidade,
                registro_id,
                descricao,
                ip,
                metodo_http,
                url,
                dados_anteriores,
                dados_novos
            )
            VALUES (
                :usuario_id,
                :acao,
                :modulo,
                :tela,
                :entidade,
                :registro_id,
                :descricao,
                :ip,
                :metodo_http,
                :url,
                :dados_anteriores,
                :dados_novos
            )
        ");

        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':acao' => strtoupper(trim($acao)),
            ':modulo' => $modulo,
            ':tela' => $tela,
            ':entidade' => $entidade,
            ':registro_id' =>
                $registroId !== null
                    ? (string) $registroId
                    : null,
            ':descricao' => $descricao,
            ':ip' => $ip,
            ':metodo_http' => $metodoHttp,
            ':url' => $url,
            ':dados_anteriores' =>
                $normalizar($dadosAnteriores),
            ':dados_novos' =>
                $normalizar($dadosNovos)
        ]);

    } catch (Throwable $erro) {
        /*
         * A auditoria não deve derrubar a operação principal.
         * O erro fica no log do servidor.
         */
        error_log(
            'Falha ao registrar auditoria: ' .
            $erro->getMessage()
        );
    }
}
