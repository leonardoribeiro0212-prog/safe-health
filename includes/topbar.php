<?php
$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';
$emailUsuario = $_SESSION['usuario_email'] ?? 'E-mail não informado';
$tituloPagina = $tituloPagina ?? 'Safe Health';
?>

<header class="topbar">
    <div class="topbar-left">
        <button
            type="button"
            class="sidebar-toggle"
            id="sidebarToggle"
            aria-label="Abrir ou fechar menu"
        >
            <i class="fa-solid fa-bars"></i>
        </button>

        <div>
            <h1>
                <?= htmlspecialchars($tituloPagina) ?>
            </h1>

            <p>Safe Health</p>
        </div>
    </div>

    <div class="topbar-user">
        <div class="user-avatar">
            <?= htmlspecialchars(
                strtoupper(mb_substr($nomeUsuario, 0, 1))
            ) ?>
        </div>

        <div class="user-info">
            <strong>
                <?= htmlspecialchars($nomeUsuario) ?>
            </strong>

            <span>
                <?= htmlspecialchars($emailUsuario) ?>
            </span>
        </div>

        <a
            href="/safe-health/logout.php"
            class="logout-button"
            title="Sair"
        >
            <i class="fa-solid fa-right-from-bracket"></i>
        </a>
    </div>
</header>