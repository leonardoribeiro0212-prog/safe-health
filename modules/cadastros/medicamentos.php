<?php

declare(strict_types=1);

$tituloPagina = 'Cadastro de Medicamentos';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$frequenciasPrescricao = [];

try {
    $frequenciasPrescricao = $pdo->query("
        SELECT id, codigo, descricao
        FROM frequencias_prescricao
        WHERE ativo = 1
        ORDER BY ordem, id
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $erro) {
    error_log(
        'Erro ao carregar frequências da prescrição: '
        . $erro->getMessage()
    );
}

?>

<link
    rel="stylesheet"
    href="/safe-health/assets/css/cadastro-medicamentos.css?v=2"
>

<div class="app-content">

    <?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

    <main class="main-content">

        <section class="med-page">

            <div class="med-heading">

                <div>
                    <span class="med-eyebrow">
                        Cadastros
                    </span>

                    <h2>Cadastro de Medicamentos</h2>

                    <p>
                        Cadastre medicamentos ou pesquise um registro
                        existente para atualizar seus dados.
                    </p>
                </div>

                <div
                    id="modoCadastro"
                    class="med-mode-badge"
                >
                    Novo cadastro
                </div>

            </div>

            <section class="med-search-card">

                <div>
                    <h3>Pesquisar medicamento</h3>

                    <p>
                        Pesquise por nome, princípio ativo ou código.
                    </p>
                </div>

                <div class="med-search-row">

                    <div class="med-search-input">
                        <i class="fa-solid fa-magnifying-glass"></i>

                        <input
                            type="text"
                            id="pesquisaMedicamento"
                            placeholder="Digite nome, princípio ativo ou código"
                            autocomplete="off"
                        >
                    </div>

                    <button
                        type="button"
                        id="botaoPesquisarMedicamento"
                        class="med-search-button"
                    >
                        <i class="fa-solid fa-magnifying-glass"></i>
                        Pesquisar
                    </button>

                    <button
                        type="button"
                        id="botaoNovoMedicamento"
                        class="med-secondary-button"
                    >
                        <i class="fa-solid fa-plus"></i>
                        Novo medicamento
                    </button>

                </div>

                <div
                    id="resultadosPesquisa"
                    class="med-search-results"
                    hidden
                ></div>

            </section>

            <form id="formMedicamento">

                <input
                    type="hidden"
                    id="medicamentoId"
                    name="medicamento_id"
                    value=""
                >

                <section class="med-card">

                    <div class="med-section-title">

                        <div class="med-section-icon">
                            <i class="fa-solid fa-pills"></i>
                        </div>

                        <div>
                            <h3>Identificação do medicamento</h3>

                            <p>
                                Dados básicos utilizados na prescrição
                                e na farmácia.
                            </p>
                        </div>

                    </div>

                    <div class="med-form-grid">

                        <div class="med-field col-8">
                            <label for="nome">
                                Nome do medicamento
                                <span>*</span>
                            </label>

                            <input
                                type="text"
                                id="nome"
                                name="nome"
                                maxlength="150"
                                required
                            >
                        </div>

                        <div class="med-field col-4">
                            <label for="codigo">
                                Código
                            </label>

                            <input
                                type="text"
                                id="codigo"
                                name="codigo"
                                maxlength="60"
                                placeholder="Ex.: MED_0001"
                            >
                        </div>

                        <div class="med-field col-6">
                            <label for="principioAtivo">
                                Princípio ativo
                            </label>

                            <input
                                type="text"
                                id="principioAtivo"
                                name="principio_ativo"
                                maxlength="150"
                            >
                        </div>

                        <div class="med-field col-6">
                            <label for="fabricante">
                                Fabricante
                            </label>

                            <input
                                type="text"
                                id="fabricante"
                                name="fabricante"
                                maxlength="120"
                            >
                        </div>

                    </div>

                </section>

                <section class="med-card">

                    <div class="med-section-title">

                        <div class="med-section-icon">
                            <i class="fa-solid fa-prescription-bottle-medical"></i>
                        </div>

                        <div>
                            <h3>Apresentação e uso</h3>

                            <p>
                                Informações usadas pelo prontuário
                                e dispensação.
                            </p>
                        </div>

                    </div>

                    <div class="med-form-grid">

                        <div class="med-field col-4">
                            <label for="apresentacao">
                                Apresentação
                            </label>

                            <input
                                type="text"
                                id="apresentacao"
                                name="apresentacao"
                                maxlength="120"
                                placeholder="Ex.: Comprimido, ampola, frasco"
                            >
                        </div>

                        <div class="med-field col-4">
                            <label for="concentracao">
                                Concentração
                            </label>

                            <input
                                type="text"
                                id="concentracao"
                                name="concentracao"
                                maxlength="80"
                                placeholder="Ex.: 500 mg"
                            >
                        </div>

                        <div class="med-field col-4">
                            <label for="unidadeMedida">
                                Unidade de medida
                            </label>

                            <select
                                id="unidadeMedida"
                                name="unidade_medida"
                            >
                                <option value="">
                                    Selecione
                                </option>
                                <option value="UN">Unidade</option>
                                <option value="CP">Comprimido</option>
                                <option value="CAP">Cápsula</option>
                                <option value="AMP">Ampola</option>
                                <option value="FR">Frasco</option>
                                <option value="ML">mL</option>
                                <option value="MG">mg</option>
                                <option value="G">g</option>
                            </select>
                        </div>

                        <div class="med-field col-4">
                            <label for="viaAdministracao">
                                Via de administração
                            </label>

                            <select
                                id="viaAdministracao"
                                name="via_administracao"
                            >
                                <option value="">Selecione</option>
                                <option value="ORAL">Oral</option>
                                <option value="EV">Endovenosa</option>
                                <option value="IM">Intramuscular</option>
                                <option value="SC">Subcutânea</option>
                                <option value="SL">Sublingual</option>
                                <option value="TOPICA">Tópica</option>
                                <option value="INALATORIA">Inalatória</option>
                                <option value="RETAL">Retal</option>
                                <option value="OUTRA">Outra</option>
                            </select>
                        </div>

                        <div class="med-field col-4">
                            <label for="dosePadrao">
                                Dose padrão para prescrição
                            </label>

                            <input
                                type="text"
                                id="dosePadrao"
                                name="dose_padrao"
                                maxlength="80"
                                placeholder="Ex.: 1 g"
                            >
                        </div>

                        <div class="med-field col-4">
                            <label for="frequenciasPrescricao">
                                Frequências permitidas
                            </label>

                            <select
                                id="frequenciasPrescricao"
                                name="frequencias_prescricao[]"
                                multiple
                                size="7"
                            >
                                <?php foreach ($frequenciasPrescricao as $frequencia): ?>
                                    <option value="<?= (int) $frequencia['id'] ?>">
                                        <?= htmlspecialchars($frequencia['descricao']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <small>
                                Use Ctrl para selecionar mais de uma frequência.
                            </small>
                        </div>

                        <div class="med-field col-4">
                            <label for="quantidadePadrao">
                                Quantidade padrão
                            </label>

                            <input
                                type="number"
                                id="quantidadePadrao"
                                name="quantidade_padrao"
                                min="1"
                                step="1"
                                value="1"
                            >
                        </div>

                        <div class="med-field col-4">
                            <label for="valorUnitario">
                                Valor unitário
                            </label>

                            <input
                                type="number"
                                id="valorUnitario"
                                name="valor_unitario"
                                min="0"
                                step="0.01"
                                value="0.00"
                            >
                        </div>

                        <div class="med-field col-2">
                            <label for="controlado">
                                Controlado
                            </label>

                            <select
                                id="controlado"
                                name="controlado"
                            >
                                <option value="0">Não</option>
                                <option value="1">Sim</option>
                            </select>
                        </div>

                        <div class="med-field col-2">
                            <label for="ativo">
                                Status
                                <span>*</span>
                            </label>

                            <select
                                id="ativo"
                                name="ativo"
                                required
                            >
                                <option value="1">Ativo</option>
                                <option value="0">Inativo</option>
                            </select>
                        </div>

                    </div>

                </section>

                <div
                    id="mensagemMedicamento"
                    class="med-message"
                    hidden
                ></div>

                <div class="med-actions">

                    <button
                        type="button"
                        id="botaoLimpar"
                        class="med-secondary-button"
                    >
                        <i class="fa-solid fa-eraser"></i>
                        Limpar
                    </button>

                    <button
                        type="submit"
                        id="botaoSalvarMedicamento"
                        class="med-save-button"
                    >
                        <i class="fa-solid fa-floppy-disk"></i>

                        <span id="textoBotaoSalvar">
                            Salvar medicamento
                        </span>
                    </button>

                </div>

            </form>

        </section>

    </main>

</div>

<script src="/safe-health/assets/js/cadastro-medicamentos.js?v=3"></script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
