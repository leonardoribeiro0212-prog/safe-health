<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/seguranca.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$identificador = trim((string)($_POST['identificador'] ?? $_POST['email'] ?? ''));
$senha = (string)($_POST['senha'] ?? '');
$ip = shIp();

if ($identificador === '' || $senha === '') {
    header('Location: login.php?erro=credenciais');
    exit;
}

try {
    $maxTentativas = max(1,(int)shConfig($pdo,'MAX_TENTATIVAS_LOGIN','5'));
    $minBloqueio = max(1,(int)shConfig($pdo,'MINUTOS_BLOQUEIO_LOGIN','15'));

    $stmtRecentes = $pdo->prepare("
        SELECT COUNT(*)
        FROM seguranca_tentativas_login
        WHERE identificador=:identificador
          AND sucesso=0
          AND criado_em >= DATE_SUB(NOW(), INTERVAL :minutos MINUTE)
    ");
    $stmtRecentes->bindValue(':identificador',$identificador);
    $stmtRecentes->bindValue(':minutos',$minBloqueio,PDO::PARAM_INT);
    $stmtRecentes->execute();

    if ((int)$stmtRecentes->fetchColumn() >= $maxTentativas) {
        shRegistrarAuditoria($pdo,'LOGIN_BLOQUEADO','Tentativa de login bloqueada por excesso de falhas.',null,'Autenticação','Login',null,null,null,['identificador'=>$identificador,'ip'=>$ip]);
        header('Location: login.php?erro=bloqueado');
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT id,nome,nome_usuario,email,senha,perfil_id,ativo,
               tentativas_login,bloqueado_ate,troca_senha_obrigatoria
        FROM usuarios
        WHERE email=:identificador OR nome_usuario=:identificador
        LIMIT 1
    ");
    $stmt->execute([':identificador'=>$identificador]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    $falha = function(?int $uid, string $motivo) use ($pdo,$identificador,$ip): void {
        $stmtLog = $pdo->prepare("
            INSERT INTO seguranca_tentativas_login
            (usuario_id,identificador,ip,sucesso,motivo,criado_em)
            VALUES (:uid,:identificador,:ip,0,:motivo,NOW())
        ");
        $stmtLog->execute([
            ':uid'=>$uid, ':identificador'=>$identificador,
            ':ip'=>$ip, ':motivo'=>$motivo
        ]);
        shRegistrarAuditoria($pdo,'LOGIN_FALHA','Falha de autenticação: '.$motivo,$uid,'Autenticação','Login',null,null,null,['identificador'=>$identificador,'ip'=>$ip]);
    };

    if (!$usuario) {
        $falha(null,'USUARIO_NAO_ENCONTRADO');
        usleep(250000);
        header('Location: login.php?erro=credenciais');
        exit;
    }

    $uid=(int)$usuario['id'];

    if ((int)$usuario['ativo'] !== 1) {
        $falha($uid,'USUARIO_INATIVO');
        header('Location: login.php?erro=credenciais');
        exit;
    }

    if (!empty($usuario['bloqueado_ate']) && strtotime((string)$usuario['bloqueado_ate']) > time()) {
        $falha($uid,'USUARIO_BLOQUEADO');
        header('Location: login.php?erro=bloqueado');
        exit;
    }

    if (!password_verify($senha,(string)$usuario['senha'])) {
        $novasTentativas=(int)$usuario['tentativas_login']+1;
        $bloquear=$novasTentativas >= $maxTentativas;

        $stmtFalha=$pdo->prepare("
            UPDATE usuarios
            SET tentativas_login=:tentativas,
                bloqueado_ate=CASE WHEN :bloquear=1
                    THEN DATE_ADD(NOW(), INTERVAL :minutos MINUTE)
                    ELSE bloqueado_ate END
            WHERE id=:id
        ");
        $stmtFalha->bindValue(':tentativas',$novasTentativas,PDO::PARAM_INT);
        $stmtFalha->bindValue(':bloquear',$bloquear?1:0,PDO::PARAM_INT);
        $stmtFalha->bindValue(':minutos',$minBloqueio,PDO::PARAM_INT);
        $stmtFalha->bindValue(':id',$uid,PDO::PARAM_INT);
        $stmtFalha->execute();

        $falha($uid,$bloquear?'LIMITE_TENTATIVAS':'SENHA_INVALIDA');
        header('Location: login.php?erro='.($bloquear?'bloqueado':'credenciais'));
        exit;
    }

    $pdo->beginTransaction();

    $pdo->prepare("
        UPDATE usuarios
        SET tentativas_login=0,bloqueado_ate=NULL
        WHERE id=:id
    ")->execute([':id'=>$uid]);

    $maxSessoes=max(1,(int)shConfig($pdo,'MAX_SESSOES_SIMULTANEAS','1'));
    if ($maxSessoes === 1) {
        $pdo->prepare("
            UPDATE sessoes_usuario
            SET encerrada_em=NOW(),motivo_encerramento='NOVO_LOGIN'
            WHERE usuario_id=:id AND encerrada_em IS NULL
        ")->execute([':id'=>$uid]);
    }

    session_regenerate_id(true);
    $token=bin2hex(random_bytes(32));
    $tokenHash=hash('sha256',$token);

    $stmtSess=$pdo->prepare("
        INSERT INTO sessoes_usuario
        (usuario_id,token_hash,php_session_id,ip,user_agent,criada_em,ultima_atividade_em)
        VALUES (:uid,:token,:sid,:ip,:ua,NOW(),NOW())
    ");
    $stmtSess->execute([
        ':uid'=>$uid, ':token'=>$tokenHash,
        ':sid'=>session_id(), ':ip'=>$ip, ':ua'=>shUserAgent()
    ]);

    $pdo->prepare("
        INSERT INTO seguranca_tentativas_login
        (usuario_id,identificador,ip,sucesso,motivo,criado_em)
        VALUES (:uid,:identificador,:ip,1,'SUCESSO',NOW())
    ")->execute([
        ':uid'=>$uid, ':identificador'=>$identificador, ':ip'=>$ip
    ]);

    $pdo->commit();

    $_SESSION['usuario_id']=$uid;
    $_SESSION['usuario_nome']=$usuario['nome'];
    $_SESSION['usuario_email']=$usuario['email'];
    $_SESSION['usuario_perfil_id']=(int)$usuario['perfil_id'];
    $_SESSION['usuario_perfil']=$usuario['perfil_id'];
    $_SESSION['security_session_token']=$token;
    $_SESSION['ultima_atividade']=time();
    $_SESSION['troca_senha_obrigatoria']=(int)$usuario['troca_senha_obrigatoria']===1;

    shRegistrarAuditoria($pdo,'LOGIN','Login realizado com sucesso.',$uid,'Autenticação','Login','usuarios',$uid,null,['ip'=>$ip]);

    if ($_SESSION['troca_senha_obrigatoria']) {
        header('Location: trocar-senha.php?primeiro_acesso=1');
    } else {
        header('Location: index.php');
    }
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Erro no login seguro Safe Health: '.$e->getMessage());
    header('Location: login.php?erro=interno');
    exit;
}
