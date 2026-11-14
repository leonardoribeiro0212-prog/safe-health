<?php
session_start();

require_once __DIR__ . '/../../config/database.php';

if (file_exists(__DIR__ . '/../../includes/auth.php')) {
    require_once __DIR__ . '/../../includes/auth.php';
}

$nomeUsuario = $_SESSION['usuario_nome']
    ?? $_SESSION['nome']
    ?? 'Usuário';

$usuarioId = (int) ($_SESSION['usuario_id'] ?? 0);

$iniciais = '';
foreach (preg_split('/\s+/', trim($nomeUsuario)) as $parte) {
    if ($parte !== '') {
        $iniciais .= mb_strtoupper(mb_substr($parte, 0, 1));
    }
    if (mb_strlen($iniciais) >= 2) {
        break;
    }
}
if ($iniciais === '') {
    $iniciais = 'SH';
}

/*
 * Tenta identificar o médico correspondente ao usuário logado.
 * Se ainda não houver vínculo usuario_id -> medicos, a fila mostra
 * os atendimentos abertos e permite filtrar por médico.
 */
$medicoLogado = null;

if ($usuarioId > 0) {
    $stmtMedico = $pdo->prepare(
        "SELECT
            m.id,
            m.nome,
            m.crm,
            m.uf_crm,
            e.nome AS especialidade
         FROM medicos m
         INNER JOIN especialidades e
            ON e.id = m.especialidade_id
         WHERE m.usuario_id = :usuario_id
           AND m.ativo = 1
         LIMIT 1"
    );
    $stmtMedico->execute([':usuario_id' => $usuarioId]);
    $medicoLogado = $stmtMedico->fetch() ?: null;
}

$filtro = trim((string) ($_GET['q'] ?? ''));
$abaFila = strtolower(trim((string) ($_GET['aba'] ?? 'urgencia')));
$abasPermitidas = ['urgencia', 'ambulatorio', 'internacao', 'finalizados'];
if (!in_array($abaFila, $abasPermitidas, true)) {
    $abaFila = 'urgencia';
}
$medicoFiltro = isset($_GET['medico_id'])
    ? (int) $_GET['medico_id']
    : 0;

if ($medicoLogado) {
    $medicoFiltro = (int) $medicoLogado['id'];
}

$medicos = $pdo->query(
    "SELECT
        m.id,
        m.nome,
        m.crm,
        m.uf_crm,
        e.nome AS especialidade
     FROM medicos m
     INNER JOIN especialidades e
        ON e.id = m.especialidade_id
     WHERE m.ativo = 1
     ORDER BY m.nome"
)->fetchAll();

$sql = "
    SELECT
        a.id,
        a.numero_atendimento,
        a.paciente_id,
        a.origem,
        a.tipo_atendimento,
        a.convenio,
        a.especialidade,
        a.profissional_id,
        a.profissional_responsavel,
        a.status,
        a.data_entrada,
        p.nome AS paciente_nome,
        p.cpf,
        p.data_nascimento,
        p.sexo
    FROM atendimentos a
    INNER JOIN pacientes p
        ON p.id = a.paciente_id
    WHERE a.status IN ('AGUARDANDO_MEDICO', 'EM_ATENDIMENTO', 'FINALIZADO')
";

$params = [];

if ($medicoFiltro > 0) {
    $sql .= " AND a.profissional_id = :medico_id";
    $params[':medico_id'] = $medicoFiltro;
}

if ($filtro !== '') {
    $sql .= "
        AND (
            p.nome LIKE :filtro
            OR a.numero_atendimento LIKE :filtro
            OR p.cpf LIKE :filtro
        )
    ";
    $params[':filtro'] = '%' . $filtro . '%';
}

$sql .= "
    ORDER BY
        a.data_entrada ASC,
        a.id ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$atendimentos = $stmt->fetchAll();

/*
 * Separação visual da fila por origem do atendimento.
 * A classificação usa origem e tipo_atendimento já existentes no cadastro.
 */
$filas = [
    'urgencia' => [],
    'ambulatorio' => [],
    'internacao' => [],
    'finalizados' => []
];

foreach ($atendimentos as $itemFila) {
    $statusFila = strtoupper(trim((string) ($itemFila['status'] ?? '')));
    $tipoFila = strtoupper(trim((string) ($itemFila['tipo_atendimento'] ?? '')));

    if ($statusFila === 'FINALIZADO') {
        $filas['finalizados'][] = $itemFila;
        continue;
    }

    if ($tipoFila === 'URGENCIA') {
        $filas['urgencia'][] = $itemFila;
    } elseif ($tipoFila === 'AMBULATORIO') {
        $filas['ambulatorio'][] = $itemFila;
    } elseif ($tipoFila === 'INTERNACAO') {
        $filas['internacao'][] = $itemFila;
    }
}

$atendimentosExibidos = $filas[$abaFila];

function calcularIdade(?string $dataNascimento): string
{
    if (!$dataNascimento) {
        return '-';
    }

    try {
        $nascimento = new DateTime($dataNascimento);
        $hoje = new DateTime();
        return $nascimento->diff($hoje)->y . ' anos';
    } catch (Throwable $e) {
        return '-';
    }
}

function formatarCpf(?string $cpf): string
{
    $cpf = preg_replace('/\D/', '', (string) $cpf);

    if (strlen($cpf) !== 11) {
        return $cpf ?: '-';
    }

    return substr($cpf, 0, 3) . '.'
        . substr($cpf, 3, 3) . '.'
        . substr($cpf, 6, 3) . '-'
        . substr($cpf, 9, 2);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Atendimentos | Prontuário Eletrônico</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="/safe-health/assets/css/prontuario-atendimentos.css?v=1"
    >
<style>
.queue-tabs{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:12px;
    margin:0 0 18px;
}
.queue-tab{
    display:flex;
    align-items:center;
    gap:10px;
    padding:15px 18px;
    border:1px solid #d7e3e9;
    border-radius:14px;
    background:#fff;
    color:#55707d;
    text-decoration:none;
    font-weight:800;
    transition:.18s ease;
}
.queue-tab i{color:#1681a5;font-size:16px}
.queue-tab span{flex:1}
.queue-tab strong{
    display:grid;
    place-items:center;
    min-width:30px;
    height:30px;
    padding:0 8px;
    border-radius:999px;
    background:#eef6f9;
    color:#126e8c;
    font-size:12px;
}
.queue-tab:hover{
    border-color:#9dc6d5;
    transform:translateY(-1px);
}
.queue-tab.active{
    background:#123a52;
    border-color:#123a52;
    color:#fff;
    box-shadow:0 10px 24px rgba(18,58,82,.12);
}
.queue-tab.active i{color:#fff}
.queue-tab.active strong{background:rgba(255,255,255,.16);color:#fff}
@media(max-width:760px){
    .queue-tabs{grid-template-columns:1fr}
}
.status-finalizado{color:#526974!important}
.status-finalizado span{background:#526974!important}
</style>
</head>

<body>

<header class="queue-header">
    <div class="brand">
        <div class="brand-icon">
            <i class="fa-solid fa-notes-medical"></i>
        </div>

        <div>
            <strong>Safe Health</strong>
            <span>Prontuário Eletrônico</span>
        </div>
    </div>

    <div class="header-actions">
        <a class="back-link" href="/safe-health/">
            <i class="fa-solid fa-arrow-left"></i>
            Voltar ao Safe Health
        </a>

        <div class="user">
            <div>
                <strong><?= htmlspecialchars($nomeUsuario) ?></strong>
                <span>
                    <?= $medicoLogado
                        ? htmlspecialchars(
                            'CRM ' . $medicoLogado['crm']
                            . '/' . $medicoLogado['uf_crm']
                        )
                        : 'Ambiente clínico'
                    ?>
                </span>
            </div>

            <span class="avatar">
                <?= htmlspecialchars($iniciais) ?>
            </span>
        </div>
    </div>
</header>

<main class="queue-main">

    <section class="page-heading">
        <div>
            <span class="eyebrow">Prontuário Eletrônico</span>
            <h1>Atendimentos em aberto</h1>
            <p>
                Selecione um atendimento para abrir o prontuário do paciente.
            </p>
        </div>

        <div class="count-card">
            <i class="fa-solid fa-hospital-user"></i>
            <div>
                <strong><?= count($atendimentosExibidos) ?></strong>
                <span>na fila</span>
            </div>
        </div>
    </section>

    <?php if ($medicoLogado): ?>
        <section class="doctor-banner">
            <i class="fa-solid fa-user-doctor"></i>

            <div>
                <span>Fila do profissional</span>
                <strong>
                    <?= htmlspecialchars($medicoLogado['nome']) ?>
                </strong>
                <small>
                    <?= htmlspecialchars($medicoLogado['especialidade']) ?>
                </small>
            </div>
        </section>
    <?php else: ?>
        <section class="notice-banner">
            <i class="fa-solid fa-circle-info"></i>
            <div>
                <strong>Usuário sem vínculo com cadastro médico</strong>
                <span>
                    Para contas médicas, vincule o usuário ao médico no cadastro.
                    Enquanto isso, é possível selecionar o profissional abaixo.
                </span>
            </div>
        </section>
    <?php endif; ?>

    <section class="filters-card">
        <form method="get" class="filters">
            <input type="hidden" name="aba" value="<?= htmlspecialchars($abaFila) ?>">
            <label class="search-field">
                <span>Paciente, CPF ou atendimento</span>
                <div>
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input
                        type="search"
                        name="q"
                        value="<?= htmlspecialchars($filtro) ?>"
                        placeholder="Pesquisar..."
                    >
                </div>
            </label>

            <?php if (!$medicoLogado): ?>
                <label>
                    <span>Profissional</span>
                    <select name="medico_id">
                        <option value="0">Todos os médicos</option>

                        <?php foreach ($medicos as $medico): ?>
                            <option
                                value="<?= (int) $medico['id'] ?>"
                                <?= $medicoFiltro === (int) $medico['id']
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                <?= htmlspecialchars(
                                    $medico['nome']
                                    . ' — '
                                    . $medico['especialidade']
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>

            <button type="submit">
                <i class="fa-solid fa-filter"></i>
                Filtrar
            </button>

            <a
                class="clear-button"
                href="/safe-health/modules/prontuario/atendimentos.php?aba=<?= urlencode($abaFila) ?>"
            >
                Limpar
            </a>
        </form>
    </section>

    <nav class="queue-tabs" aria-label="Filas de atendimento">
        <?php
        $rotulosAbas = [
            'urgencia' => ['Urgência', 'fa-truck-medical'],
            'ambulatorio' => ['Ambulatório', 'fa-calendar-check'],
            'internacao' => ['Internação', 'fa-bed-pulse'],
            'finalizados' => ['Finalizados', 'fa-circle-check']
        ];
        foreach ($rotulosAbas as $chaveAba => [$rotuloAba, $iconeAba]):
            $queryAba = ['aba' => $chaveAba];
            if ($filtro !== '') $queryAba['q'] = $filtro;
            if (!$medicoLogado && $medicoFiltro > 0) {
                $queryAba['medico_id'] = $medicoFiltro;
            }
        ?>
            <a
                class="queue-tab <?= $abaFila === $chaveAba ? 'active' : '' ?>"
                href="?<?= htmlspecialchars(http_build_query($queryAba)) ?>"
            >
                <i class="fa-solid <?= htmlspecialchars($iconeAba) ?>"></i>
                <span><?= htmlspecialchars($rotuloAba) ?></span>
                <strong><?= count($filas[$chaveAba]) ?></strong>
            </a>
        <?php endforeach; ?>
    </nav>

    <section class="queue-card">

        <div class="queue-card-header">
            <div>
                <span class="eyebrow">Fila médica</span>
                <h2>
                    <?= $abaFila === 'urgencia'
                        ? 'Urgência'
                        : ($abaFila === 'ambulatorio'
                            ? 'Ambulatório'
                            : ($abaFila === 'internacao'
                                ? 'Internação'
                                : 'Atendimentos finalizados'))
                    ?>
                </h2>
            </div>
        </div>

        <?php if (!$atendimentosExibidos): ?>

            <div class="empty-state">
                <i class="fa-solid fa-clipboard-check"></i>
                <h3>Nenhum atendimento encontrado</h3>
                <p>
                    Não existem atendimentos abertos para os filtros atuais.
                </p>
            </div>

        <?php else: ?>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Atendimento</th>
                            <th>Paciente</th>
                            <th>Idade</th>
                            <th>Especialidade</th>
                            <th>Entrada</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($atendimentosExibidos as $item): ?>
                            <tr>
                                <td>
                                    <strong class="attendance-number">
                                        <?= htmlspecialchars(
                                            $item['numero_atendimento']
                                        ) ?>
                                    </strong>
                                    <small>
                                        <?= htmlspecialchars(
                                            $item['tipo_atendimento'] ?: '-'
                                        ) ?>
                                    </small>
                                </td>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars(
                                            $item['paciente_nome']
                                        ) ?>
                                    </strong>
                                    <small>
                                        CPF <?= htmlspecialchars(
                                            formatarCpf($item['cpf'])
                                        ) ?>
                                    </small>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        calcularIdade(
                                            $item['data_nascimento']
                                        )
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $item['especialidade'] ?: '-'
                                    ) ?>
                                </td>

                                <td>
                                    <?= $item['data_entrada']
                                        ? date(
                                            'd/m/Y H:i',
                                            strtotime($item['data_entrada'])
                                        )
                                        : '-'
                                    ?>
                                </td>

                                <td>
                                    <?php if (($item['status'] ?? '') === 'FINALIZADO'): ?>
                                        <span class="status status-finalizado">
                                            <span></span>
                                            Finalizado
                                        </span>
                                    <?php else: ?>
                                        <span class="status">
                                            <span></span>
                                            Em aberto
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="action-cell">
                                    <a
                                        class="open-record"
                                        href="/safe-health/modules/prontuario/index.php?atendimento_id=<?= (int) $item['id'] ?>"
                                    >
                                        <?= ($item['status'] ?? '') === 'FINALIZADO'
                                            ? 'Visualizar Prontuário'
                                            : 'Abrir Prontuário'
                                        ?>
                                        <i class="fa-solid fa-arrow-right"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>

    </section>

</main>

</body>
</html>
