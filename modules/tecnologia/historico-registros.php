<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissoes.php';

$usuarioId = (int) ($_SESSION['usuario_id'] ?? 0);
if ($usuarioId <= 0) {
    header('Location: /safe-health/login.php');
    exit;
}

function hrDataValida(?string $data): bool
{
    if (!$data) return false;
    $d = DateTime::createFromFormat('Y-m-d', $data);
    return $d && $d->format('Y-m-d') === $data;
}

function hrH(?string $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function hrResumoJson(?string $json): string
{
    if (!$json) return '—';
    $dec = json_decode($json, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return json_encode($dec, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    return $json;
}

$hoje = new DateTimeImmutable('today');
$dataFim = hrDataValida($_GET['data_fim'] ?? null)
    ? new DateTimeImmutable((string) $_GET['data_fim'])
    : $hoje;
$dataInicio = hrDataValida($_GET['data_inicio'] ?? null)
    ? new DateTimeImmutable((string) $_GET['data_inicio'])
    : $dataFim->modify('-30 days');

if ($dataInicio > $dataFim) {
    [$dataInicio, $dataFim] = [$dataFim, $dataInicio];
}

$fUsuario = max(0, (int) ($_GET['usuario_id'] ?? 0));
$fModulo = trim((string) ($_GET['modulo'] ?? ''));
$fAcao = trim((string) ($_GET['acao'] ?? ''));
$fBusca = trim((string) ($_GET['q'] ?? ''));

$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina = 20;
$offset = ($pagina - 1) * $porPagina;

$where = ['au.criado_em BETWEEN :inicio AND :fim'];
$params = [
    ':inicio' => $dataInicio->format('Y-m-d') . ' 00:00:00',
    ':fim' => $dataFim->format('Y-m-d') . ' 23:59:59',
];

if ($fUsuario > 0) {
    $where[] = 'au.usuario_id = :usuario_id';
    $params[':usuario_id'] = $fUsuario;
}
if ($fModulo !== '') {
    $where[] = 'au.modulo = :modulo';
    $params[':modulo'] = $fModulo;
}
if ($fAcao !== '') {
    $where[] = 'au.acao = :acao';
    $params[':acao'] = $fAcao;
}
if ($fBusca !== '') {
    $where[] = '(au.descricao LIKE :busca OR au.tela LIKE :busca OR au.entidade LIKE :busca OR au.registro_id LIKE :busca OR au.ip LIKE :busca OR u.nome LIKE :busca OR u.email LIKE :busca)';
    $params[':busca'] = '%' . $fBusca . '%';
}

$whereSql = implode(' AND ', $where);

$usuarios = $pdo->query("
    SELECT id, nome, email
    FROM usuarios
    WHERE ativo = 1
    ORDER BY nome
")->fetchAll(PDO::FETCH_ASSOC);

$modulos = $pdo->query("
    SELECT DISTINCT modulo
    FROM auditoria_sistema
    WHERE modulo IS NOT NULL AND modulo <> ''
    ORDER BY modulo
")->fetchAll(PDO::FETCH_COLUMN);

$acoes = $pdo->query("
    SELECT DISTINCT acao
    FROM auditoria_sistema
    WHERE acao IS NOT NULL AND acao <> ''
    ORDER BY acao
")->fetchAll(PDO::FETCH_COLUMN);

$stmtTotal = $pdo->prepare("
    SELECT COUNT(*)
    FROM auditoria_sistema au
    LEFT JOIN usuarios u ON u.id = au.usuario_id
    WHERE {$whereSql}
");
$stmtTotal->execute($params);
$totalRegistros = (int) $stmtTotal->fetchColumn();
$totalPaginas = max(1, (int) ceil($totalRegistros / $porPagina));
if ($pagina > $totalPaginas) {
    $pagina = $totalPaginas;
    $offset = ($pagina - 1) * $porPagina;
}

$stmtLogs = $pdo->prepare("
    SELECT
        au.id,
        au.usuario_id,
        au.acao,
        au.modulo,
        au.tela,
        au.entidade,
        au.registro_id,
        au.descricao,
        au.ip,
        au.metodo_http,
        au.url,
        au.dados_anteriores,
        au.dados_novos,
        au.criado_em,
        COALESCE(u.nome, 'Sistema') AS usuario_nome,
        u.email AS usuario_email
    FROM auditoria_sistema au
    LEFT JOIN usuarios u ON u.id = au.usuario_id
    WHERE {$whereSql}
    ORDER BY au.criado_em DESC, au.id DESC
    LIMIT {$porPagina} OFFSET {$offset}
");
$stmtLogs->execute($params);
$logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

$paramsPeriodo = [
    ':inicio' => $dataInicio->format('Y-m-d') . ' 00:00:00',
    ':fim' => $dataFim->format('Y-m-d') . ' 23:59:59',
];

$stmtResumo = $pdo->prepare("
    SELECT
        COUNT(*) AS registros,
        COUNT(DISTINCT usuario_id) AS usuarios,
        SUM(CASE WHEN UPPER(COALESCE(modulo,'')) LIKE '%PRONTU%' THEN 1 ELSE 0 END) AS prontuario,
        SUM(
            CASE
                WHEN UPPER(COALESCE(acao,'')) LIKE '%NEGAD%'
                  OR UPPER(COALESCE(acao,'')) LIKE '%ACESSO_NEG%'
                  OR UPPER(COALESCE(descricao,'')) LIKE '%ACESSO NEGADO%'
                THEN 1 ELSE 0
            END
        ) AS negados
    FROM auditoria_sistema
    WHERE criado_em BETWEEN :inicio AND :fim
");
$stmtResumo->execute($paramsPeriodo);
$resumo = $stmtResumo->fetch(PDO::FETCH_ASSOC) ?: [];

$stmtHoje = $pdo->query("
    SELECT COUNT(*)
    FROM auditoria_sistema
    WHERE criado_em >= CURDATE()
      AND criado_em < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
");
$registrosHoje = (int) $stmtHoje->fetchColumn();

function hrQueryString(array $alteracoes = []): string
{
    $base = $_GET;
    unset($base['pagina']);
    foreach ($alteracoes as $k => $v) {
        if ($v === null) unset($base[$k]);
        else $base[$k] = $v;
    }
    return http_build_query($base);
}

$tituloPagina = 'Histórico de Registros';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<link rel="stylesheet" href="/safe-health/assets/css/historico-registros.css">

<div class="app-content">
    <?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

    <main class="main-content hr-page">
        <section class="hr-heading">
            <div>
                <span class="hr-kicker">Tecnologia • Auditoria</span>
                <h1>Histórico de Registros de Atividades</h1>
                <p>Rastreabilidade das movimentações e operações realizadas no Safe Health.</p>
            </div>
            <button type="button" class="hr-export" onclick="window.print()">
                <i class="fa-solid fa-file-arrow-down"></i>
                Exportar / Imprimir
            </button>
        </section>

        <section class="hr-cards">
            <article>
                <div class="hr-icon"><i class="fa-solid fa-calendar-day"></i></div>
                <div><span>Registros hoje</span><strong><?= number_format($registrosHoje, 0, ',', '.') ?></strong></div>
            </article>
            <article>
                <div class="hr-icon"><i class="fa-solid fa-list-check"></i></div>
                <div><span>Registros no período</span><strong><?= number_format((int)($resumo['registros'] ?? 0), 0, ',', '.') ?></strong></div>
            </article>
            <article>
                <div class="hr-icon"><i class="fa-solid fa-users-gear"></i></div>
                <div><span>Usuários com atividade</span><strong><?= number_format((int)($resumo['usuarios'] ?? 0), 0, ',', '.') ?></strong></div>
            </article>
            <article>
                <div class="hr-icon"><i class="fa-solid fa-file-shield"></i></div>
                <div><span>Ações no Prontuário</span><strong><?= number_format((int)($resumo['prontuario'] ?? 0), 0, ',', '.') ?></strong></div>
            </article>
            <article class="<?= ((int)($resumo['negados'] ?? 0)) > 0 ? 'hr-alert-card' : '' ?>">
                <div class="hr-icon"><i class="fa-solid fa-shield-halved"></i></div>
                <div><span>Acessos negados</span><strong><?= number_format((int)($resumo['negados'] ?? 0), 0, ',', '.') ?></strong></div>
            </article>
        </section>

        <form class="hr-filters" method="get">
            <label><span>Data inicial</span><input type="date" name="data_inicio" value="<?= hrH($dataInicio->format('Y-m-d')) ?>"></label>
            <label><span>Data final</span><input type="date" name="data_fim" value="<?= hrH($dataFim->format('Y-m-d')) ?>"></label>
            <label>
                <span>Usuário</span>
                <select name="usuario_id">
                    <option value="0">Todos</option>
                    <?php foreach ($usuarios as $usuario): ?>
                        <option value="<?= (int)$usuario['id'] ?>" <?= $fUsuario === (int)$usuario['id'] ? 'selected' : '' ?>>
                            <?= hrH($usuario['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Módulo</span>
                <select name="modulo">
                    <option value="">Todos</option>
                    <?php foreach ($modulos as $modulo): ?>
                        <option value="<?= hrH((string)$modulo) ?>" <?= $fModulo === (string)$modulo ? 'selected' : '' ?>><?= hrH((string)$modulo) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Ação</span>
                <select name="acao">
                    <option value="">Todas</option>
                    <?php foreach ($acoes as $acao): ?>
                        <option value="<?= hrH((string)$acao) ?>" <?= $fAcao === (string)$acao ? 'selected' : '' ?>><?= hrH((string)$acao) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="hr-search">
                <span>Pesquisar</span>
                <input type="search" name="q" value="<?= hrH($fBusca) ?>" placeholder="Descrição, tela, registro, IP ou usuário">
            </label>
            <div class="hr-actions">
                <button type="submit"><i class="fa-solid fa-magnifying-glass"></i> Pesquisar</button>
                <a href="/safe-health/modules/tecnologia/historico-registros.php">Limpar</a>
            </div>
        </form>

        <section class="hr-panel">
            <header class="hr-panel-header">
                <div>
                    <h2>Registro de Atividades</h2>
                    <p><?= number_format($totalRegistros, 0, ',', '.') ?> registro(s) encontrado(s)</p>
                </div>
                <div class="hr-periodo">
                    <?= hrH($dataInicio->format('d/m/Y')) ?> — <?= hrH($dataFim->format('d/m/Y')) ?>
                </div>
            </header>

            <div class="hr-table-wrap">
                <table class="hr-table">
                    <thead>
                        <tr>
                            <th>Data/Hora</th>
                            <th>Usuário</th>
                            <th>Ação realizada</th>
                            <th>Módulo</th>
                            <th>Tela</th>
                            <th>Descrição do Log</th>
                            <th>IP</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$logs): ?>
                        <tr><td colspan="8" class="hr-empty">Nenhum registro encontrado para os filtros informados.</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <?php
                            $acaoClasse = strtolower(preg_replace('/[^a-z0-9]+/i', '-', (string)$log['acao']));
                            $detalhes = [
                                'id' => (int)$log['id'],
                                'data' => date('d/m/Y H:i:s', strtotime((string)$log['criado_em'])),
                                'usuario' => $log['usuario_nome'],
                                'email' => $log['usuario_email'] ?: '—',
                                'acao' => $log['acao'],
                                'modulo' => $log['modulo'] ?: '—',
                                'tela' => $log['tela'] ?: '—',
                                'entidade' => $log['entidade'] ?: '—',
                                'registro_id' => $log['registro_id'] ?: '—',
                                'descricao' => $log['descricao'],
                                'ip' => $log['ip'] ?: '—',
                                'metodo_http' => $log['metodo_http'] ?: '—',
                                'url' => $log['url'] ?: '—',
                                'dados_anteriores' => hrResumoJson($log['dados_anteriores']),
                                'dados_novos' => hrResumoJson($log['dados_novos']),
                            ];
                            ?>
                            <tr>
                                <td class="hr-nowrap"><?= hrH(date('d/m/Y H:i:s', strtotime((string)$log['criado_em']))) ?></td>
                                <td>
                                    <strong class="hr-user"><?= hrH($log['usuario_nome']) ?></strong>
                                    <small><?= hrH($log['usuario_email'] ?: 'Sistema') ?></small>
                                </td>
                                <td><span class="hr-badge hr-acao-<?= hrH($acaoClasse) ?>"><?= hrH($log['acao']) ?></span></td>
                                <td><?= hrH($log['modulo'] ?: '—') ?></td>
                                <td><?= hrH($log['tela'] ?: '—') ?></td>
                                <td class="hr-desc"><?= hrH($log['descricao']) ?></td>
                                <td class="hr-nowrap"><?= hrH($log['ip'] ?: '—') ?></td>
                                <td>
                                    <button type="button" class="hr-detail-btn" data-log='<?= hrH(json_encode($detalhes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>' title="Ver detalhes">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPaginas > 1): ?>
                <nav class="hr-pagination" aria-label="Paginação">
                    <?php if ($pagina > 1): ?>
                        <a href="?<?= hrH(hrQueryString(['pagina' => $pagina - 1])) ?>"><i class="fa-solid fa-chevron-left"></i></a>
                    <?php endif; ?>

                    <?php
                    $inicioPag = max(1, $pagina - 2);
                    $fimPag = min($totalPaginas, $pagina + 2);
                    for ($p = $inicioPag; $p <= $fimPag; $p++):
                    ?>
                        <a class="<?= $p === $pagina ? 'active' : '' ?>" href="?<?= hrH(hrQueryString(['pagina' => $p])) ?>"><?= $p ?></a>
                    <?php endfor; ?>

                    <?php if ($pagina < $totalPaginas): ?>
                        <a href="?<?= hrH(hrQueryString(['pagina' => $pagina + 1])) ?>"><i class="fa-solid fa-chevron-right"></i></a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        </section>
    </main>
</div>

<div class="hr-modal" id="hrModal" aria-hidden="true">
    <div class="hr-modal-backdrop" data-close-modal></div>
    <section class="hr-modal-card" role="dialog" aria-modal="true" aria-labelledby="hrModalTitle">
        <header>
            <div>
                <span class="hr-kicker">Auditoria</span>
                <h2 id="hrModalTitle">Detalhes do Registro</h2>
            </div>
            <button type="button" class="hr-modal-close" data-close-modal><i class="fa-solid fa-xmark"></i></button>
        </header>
        <div class="hr-modal-body" id="hrModalBody"></div>
    </section>
</div>

<script src="/safe-health/assets/js/historico-registros.js"></script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
