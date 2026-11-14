<?php
declare(strict_types=1);

$tituloPagina = 'Cadastro de Médicos';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../config/database.php';

$especialidades = $pdo->query("
    SELECT id, nome
    FROM especialidades
    WHERE ativa = 1
    ORDER BY nome
")->fetchAll(PDO::FETCH_ASSOC);

$usuarios = [];
try {
    $usuarios = $pdo->query("
        SELECT
            id,
            nome,
            nome_usuario,
            email
        FROM usuarios
        WHERE ativo = 1
        ORDER BY nome
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $erro) {
    error_log('Erro ao carregar usuários: ' . $erro->getMessage());
}
?>
<link rel="stylesheet" href="/safe-health/assets/css/cadastro-medicos.css?v=1">

<div class="app-content">
<?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

<main class="main-content">
<section class="doctor-page">

<div class="doctor-heading">
    <div>
        <span class="doctor-eyebrow">Cadastros</span>
        <h2>Cadastro de Médicos</h2>
        <p>Cadastre novos médicos ou pesquise um profissional para atualizar seus dados.</p>
    </div>
    <div id="modoCadastro" class="doctor-mode-badge">Novo cadastro</div>
</div>

<section class="doctor-search-card">
    <h3>Pesquisar médico</h3>
    <p>Pesquise por nome, CPF ou CRM.</p>

    <div class="doctor-search-row">
        <div class="doctor-search-input">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="pesquisaMedico" placeholder="Digite nome, CPF ou CRM" autocomplete="off">
        </div>

        <button type="button" id="botaoPesquisarMedico" class="doctor-search-button">
            <i class="fa-solid fa-magnifying-glass"></i> Pesquisar
        </button>

        <button type="button" id="botaoNovoMedico" class="doctor-secondary-button">
            <i class="fa-solid fa-user-doctor"></i> Novo médico
        </button>
    </div>

    <div id="resultadosPesquisa" class="doctor-search-results" hidden></div>
</section>

<form id="formMedico">
<input type="hidden" id="medicoId" name="medico_id">

<section class="doctor-card">
    <div class="doctor-section-title">
        <div class="doctor-section-icon"><i class="fa-solid fa-user-doctor"></i></div>
        <div>
            <h3>Identificação profissional</h3>
            <p>Dados principais e registro profissional.</p>
        </div>
    </div>

    <div class="doctor-form-grid">
        <div class="doctor-field col-8">
            <label for="nome">Nome completo <span>*</span></label>
            <input type="text" id="nome" name="nome" maxlength="150" required>
        </div>

        <div class="doctor-field col-4">
            <label for="cpf">CPF <span>*</span></label>
            <input type="text" id="cpf" name="cpf" maxlength="14" required>
        </div>

        <div class="doctor-field col-4">
            <label for="crm">CRM <span>*</span></label>
            <input type="text" id="crm" name="crm" maxlength="30" required>
        </div>

        <div class="doctor-field col-2">
            <label for="ufCrm">UF CRM <span>*</span></label>
            <select id="ufCrm" name="uf_crm" required>
                <option value="">UF</option>
                <?php foreach (['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $uf): ?>
                    <option value="<?= $uf ?>"><?= $uf ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="doctor-field col-6">
            <label for="especialidade">Especialidade principal <span>*</span></label>
            <select id="especialidade" name="especialidade_id" required>
                <option value="">Selecione</option>
                <?php foreach ($especialidades as $especialidade): ?>
                    <option value="<?= (int) $especialidade['id'] ?>">
                        <?= htmlspecialchars((string) $especialidade['nome'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</section>

<section class="doctor-card">
    <div class="doctor-section-title">
        <div class="doctor-section-icon"><i class="fa-solid fa-address-book"></i></div>
        <div>
            <h3>Contato e acesso</h3>
            <p>Dados de contato e vínculo com o usuário do ERP.</p>
        </div>
    </div>

    <div class="doctor-form-grid">
        <div class="doctor-field col-6">
            <label for="telefone">Telefone</label>
            <input type="text" id="telefone" name="telefone" maxlength="20">
        </div>

        <div class="doctor-field col-6">
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" maxlength="150">
        </div>

        <div class="doctor-field col-8">
            <label for="usuarioId">Usuário do sistema vinculado</label>
            <select id="usuarioId" name="usuario_id">
                <option value="">Nenhum usuário vinculado</option>
                <?php foreach ($usuarios as $usuario): ?>
                    <option value="<?= (int) $usuario['id'] ?>">
                        <?= htmlspecialchars(
                            (string) $usuario['nome']
                            . (!empty($usuario['nome_usuario']) ? ' (@' . $usuario['nome_usuario'] . ')' : '')
                            . (!empty($usuario['email']) ? ' — ' . $usuario['email'] : ''),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="doctor-field col-4">
            <label for="ativo">Status <span>*</span></label>
            <select id="ativo" name="ativo" required>
                <option value="1">Ativo</option>
                <option value="0">Inativo</option>
            </select>
        </div>
    </div>
</section>

<div id="mensagemMedico" class="doctor-message" hidden></div>

<div class="doctor-actions">
    <button type="button" id="botaoLimpar" class="doctor-secondary-button">
        <i class="fa-solid fa-eraser"></i> Limpar
    </button>

    <button type="submit" id="botaoSalvarMedico" class="doctor-save-button">
        <i class="fa-solid fa-floppy-disk"></i>
        <span id="textoBotaoSalvar">Salvar médico</span>
    </button>
</div>
</form>
</section>
</main>
</div>

<script src="/safe-health/assets/js/cadastro-medicos.js?v=1"></script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
