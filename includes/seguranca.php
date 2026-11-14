<?php
declare(strict_types=1);

function shIp(): ?string
{
    return $_SERVER['REMOTE_ADDR'] ?? null;
}

function shUserAgent(): ?string
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
    return $ua ? mb_substr($ua, 0, 500) : null;
}

function shConfig(PDO $pdo, string $chave, string $padrao): string
{
    try {
        $stmt = $pdo->prepare("SELECT valor FROM configuracoes_seguranca WHERE chave=:chave LIMIT 1");
        $stmt->execute([':chave'=>$chave]);
        $valor = $stmt->fetchColumn();
        return $valor !== false ? (string)$valor : $padrao;
    } catch (Throwable $e) {
        return $padrao;
    }
}

function shRegistrarAuditoria(
    PDO $pdo,
    string $acao,
    string $descricao,
    ?int $usuarioId = null,
    ?string $modulo = 'Segurança',
    ?string $tela = null,
    ?string $entidade = null,
    int|string|null $registroId = null,
    mixed $dadosAnteriores = null,
    mixed $dadosNovos = null
): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO auditoria_sistema
            (usuario_id,acao,modulo,tela,entidade,registro_id,descricao,ip,metodo_http,url,dados_anteriores,dados_novos,criado_em)
            VALUES
            (:usuario_id,:acao,:modulo,:tela,:entidade,:registro_id,:descricao,:ip,:metodo_http,:url,:dados_anteriores,:dados_novos,NOW())
        ");
        $json = static fn($v) => $v === null ? null : json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $stmt->execute([
            ':usuario_id'=>$usuarioId,
            ':acao'=>$acao,
            ':modulo'=>$modulo,
            ':tela'=>$tela,
            ':entidade'=>$entidade,
            ':registro_id'=>$registroId === null ? null : (string)$registroId,
            ':descricao'=>$descricao,
            ':ip'=>shIp(),
            ':metodo_http'=>$_SERVER['REQUEST_METHOD'] ?? null,
            ':url'=>$_SERVER['REQUEST_URI'] ?? null,
            ':dados_anteriores'=>$json($dadosAnteriores),
            ':dados_novos'=>$json($dadosNovos),
        ]);
    } catch (Throwable $e) {
        error_log('Safe Health auditoria segurança: '.$e->getMessage());
    }
}

function shValidarSenha(PDO $pdo, string $senha, ?int $usuarioId = null): array
{
    $min = (int) shConfig($pdo, 'SENHA_MIN_CARACTERES', '10');
    $erros = [];

    if (mb_strlen($senha) < $min) $erros[] = "A senha deve possuir no mínimo {$min} caracteres.";
    if (!preg_match('/[A-Z]/', $senha)) $erros[] = 'Inclua pelo menos uma letra maiúscula.';
    if (!preg_match('/[a-z]/', $senha)) $erros[] = 'Inclua pelo menos uma letra minúscula.';
    if (!preg_match('/\d/', $senha)) $erros[] = 'Inclua pelo menos um número.';
    if (!preg_match('/[^A-Za-z0-9]/', $senha)) $erros[] = 'Inclua pelo menos um caractere especial.';
    if (preg_match('/\s/', $senha)) $erros[] = 'A senha não pode conter espaços.';

    if ($usuarioId && !$erros) {
        $qtd = max(1, (int) shConfig($pdo, 'SENHA_HISTORICO_QTD', '5'));
        $stmt = $pdo->prepare("
            SELECT senha_hash FROM usuario_senhas_historico
            WHERE usuario_id=:usuario_id
            ORDER BY criado_em DESC,id DESC
            LIMIT {$qtd}
        ");
        $stmt->execute([':usuario_id'=>$usuarioId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $hash) {
            if (password_verify($senha, (string)$hash)) {
                $erros[] = "A nova senha não pode repetir uma das últimas {$qtd} senhas.";
                break;
            }
        }
        $stmt = $pdo->prepare("SELECT senha FROM usuarios WHERE id=:id");
        $stmt->execute([':id'=>$usuarioId]);
        $atual = $stmt->fetchColumn();
        if ($atual && password_verify($senha, (string)$atual)) {
            $erros[] = 'A nova senha deve ser diferente da senha atual.';
        }
    }
    return $erros;
}

function shUsuarioTemAcao(PDO $pdo, int $usuarioId, string $acao): bool
{
    try {
        $stmt = $pdo->prepare("
            SELECT 1
            FROM usuario_papel up
            INNER JOIN papel_acao_seguranca pa ON pa.papel_id=up.papel_id
            WHERE up.usuario_id=:usuario_id
              AND pa.acao_codigo=:acao
            LIMIT 1
        ");
        $stmt->execute([':usuario_id'=>$usuarioId, ':acao'=>$acao]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function shExigirAcao(PDO $pdo, string $acao): void
{
    $usuarioId = (int)($_SESSION['usuario_id'] ?? 0);
    if ($usuarioId <= 0 || !shUsuarioTemAcao($pdo, $usuarioId, $acao)) {
        shRegistrarAuditoria($pdo,'ACESSO_NEGADO',"Permissão negada para a ação {$acao}.",$usuarioId ?: null,'Segurança','Controle de acesso','acao_seguranca',$acao);
        http_response_code(403);
        exit('Acesso negado.');
    }
}

function shReautenticacaoValida(PDO $pdo): bool
{
    $ate = (int)($_SESSION['reauth_ate'] ?? 0);
    return $ate >= time();
}

function shExigirReautenticacao(PDO $pdo): void
{
    if (!shReautenticacaoValida($pdo)) {
        http_response_code(428);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'sucesso'=>false,
            'codigo'=>'REAUTENTICACAO_NECESSARIA',
            'mensagem'=>'Confirme sua senha para concluir esta ação.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
