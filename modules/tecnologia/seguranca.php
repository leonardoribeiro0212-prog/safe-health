<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissoes.php';
require_once __DIR__ . '/../../includes/seguranca.php';

$usuarioId=(int)$_SESSION['usuario_id'];
if (!shUsuarioTemAcao($pdo,$usuarioId,'SEGURANCA_VISUALIZAR')) {
    // Administrador antigo pode ainda não ter usuario_papel corretamente ligado.
    $stmt=$pdo->prepare("SELECT p.nome FROM usuario_papel up INNER JOIN papeis p ON p.id=up.papel_id WHERE up.usuario_id=:id");
    $stmt->execute([':id'=>$usuarioId]);
    $nomes=$stmt->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('Administrador',$nomes,true)) {
        http_response_code(403);
        exit('Acesso negado.');
    }
}

$hoje=$pdo->query("SELECT COUNT(*) FROM seguranca_tentativas_login WHERE criado_em>=CURDATE()")->fetchColumn();
$falhas24=$pdo->query("SELECT COUNT(*) FROM seguranca_tentativas_login WHERE sucesso=0 AND criado_em>=DATE_SUB(NOW(),INTERVAL 24 HOUR)")->fetchColumn();
$sessoes=$pdo->query("SELECT COUNT(*) FROM sessoes_usuario WHERE encerrada_em IS NULL")->fetchColumn();
$bloqueados=$pdo->query("SELECT COUNT(*) FROM usuarios WHERE bloqueado_ate IS NOT NULL AND bloqueado_ate>NOW()")->fetchColumn();
$trocas=$pdo->query("SELECT COUNT(*) FROM usuarios WHERE troca_senha_obrigatoria=1 AND ativo=1")->fetchColumn();

$eventos=$pdo->query("
    SELECT au.criado_em,COALESCE(u.nome,'Sistema') usuario,au.acao,au.descricao,au.ip
    FROM auditoria_sistema au
    LEFT JOIN usuarios u ON u.id=au.usuario_id
    WHERE au.acao IN ('LOGIN','LOGIN_FALHA','LOGIN_BLOQUEADO','LOGOUT','SESSAO_EXPIRADA','SESSAO_INVALIDADA','ACESSO_NEGADO','REAUTENTICACAO_FALHA','TROCA_SENHA')
    ORDER BY au.criado_em DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

$sessoesAtivas=$pdo->query("
    SELECT s.id,s.usuario_id,u.nome,u.email,s.ip,s.criada_em,s.ultima_atividade_em
    FROM sessoes_usuario s
    INNER JOIN usuarios u ON u.id=s.usuario_id
    WHERE s.encerrada_em IS NULL
    ORDER BY s.ultima_atividade_em DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

$tituloPagina='Segurança do Sistema';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<link rel="stylesheet" href="/safe-health/assets/css/security.css">
<div class="app-content">
<?php require_once __DIR__ . '/../../includes/topbar.php'; ?>
<main class="main-content sec-page">
<section class="sec-heading">
<div><span class="sec-kicker">Tecnologia • Segurança</span><h1>Segurança do Sistema</h1><p>Indicadores de autenticação, sessões e eventos de segurança do Safe Health.</p></div>
<a class="sec-outline" href="/safe-health/modules/tecnologia/historico-registros.php">Histórico de Registros</a>
</section>

<section class="sec-cards">
<article><i class="fa-solid fa-right-to-bracket"></i><span>Tentativas hoje</span><strong><?= number_format((int)$hoje,0,',','.') ?></strong></article>
<article><i class="fa-solid fa-triangle-exclamation"></i><span>Falhas nas últimas 24h</span><strong><?= number_format((int)$falhas24,0,',','.') ?></strong></article>
<article><i class="fa-solid fa-desktop"></i><span>Sessões ativas</span><strong><?= number_format((int)$sessoes,0,',','.') ?></strong></article>
<article><i class="fa-solid fa-user-lock"></i><span>Usuários bloqueados</span><strong><?= number_format((int)$bloqueados,0,',','.') ?></strong></article>
<article><i class="fa-solid fa-key"></i><span>Troca obrigatória</span><strong><?= number_format((int)$trocas,0,',','.') ?></strong></article>
</section>

<section class="sec-grid">
<article class="sec-panel"><header><h2>Sessões ativas</h2><p>Contas atualmente autenticadas.</p></header>
<div class="sec-table-wrap"><table><thead><tr><th>Usuário</th><th>IP</th><th>Início</th><th>Última atividade</th></tr></thead><tbody>
<?php if(!$sessoesAtivas): ?><tr><td colspan="4" class="sec-empty">Nenhuma sessão ativa.</td></tr><?php endif; ?>
<?php foreach($sessoesAtivas as $s): ?><tr><td><strong><?= htmlspecialchars($s['nome']) ?></strong><small><?= htmlspecialchars($s['email']) ?></small></td><td><?= htmlspecialchars($s['ip'] ?: '—') ?></td><td><?= htmlspecialchars(date('d/m/Y H:i',strtotime($s['criada_em']))) ?></td><td><?= htmlspecialchars(date('d/m/Y H:i',strtotime($s['ultima_atividade_em']))) ?></td></tr><?php endforeach; ?>
</tbody></table></div></article>

<article class="sec-panel"><header><h2>Eventos recentes</h2><p>Ocorrências relevantes para auditoria.</p></header>
<div class="sec-events">
<?php if(!$eventos): ?><div class="sec-empty">Nenhum evento recente.</div><?php endif; ?>
<?php foreach($eventos as $e): ?><div class="sec-event"><span class="sec-event-icon"><i class="fa-solid fa-shield-halved"></i></span><div><strong><?= htmlspecialchars($e['acao']) ?> • <?= htmlspecialchars($e['usuario']) ?></strong><p><?= htmlspecialchars($e['descricao']) ?></p><small><?= htmlspecialchars(date('d/m/Y H:i:s',strtotime($e['criado_em']))) ?><?= $e['ip'] ? ' • '.$e['ip'] : '' ?></small></div></div><?php endforeach; ?>
</div></article>
</section>

<section class="sec-panel sec-protections"><header><h2>Proteções configuradas</h2><p>Controles implantados nesta versão.</p></header>
<div class="sec-protection-grid">
<div><i class="fa-solid fa-ban"></i><strong>Bloqueio anti-força bruta</strong><span>5 falhas → bloqueio temporário.</span></div>
<div><i class="fa-solid fa-clock"></i><strong>Expiração por inatividade</strong><span>20 minutos sem atividade.</span></div>
<div><i class="fa-solid fa-fingerprint"></i><strong>Sessão única</strong><span>Novo login invalida a sessão anterior.</span></div>
<div><i class="fa-solid fa-lock"></i><strong>Política de senha</strong><span>Complexidade e histórico de senhas.</span></div>
<div><i class="fa-solid fa-user-check"></i><strong>Reautenticação</strong><span>Base pronta para ações clínicas críticas.</span></div>
<div><i class="fa-solid fa-list-check"></i><strong>Permissões por ação</strong><span>Permissões granulares além das telas.</span></div>
</div></section>
</main></div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
