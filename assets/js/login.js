const botaoMostrarSenha = document.getElementById("mostrarSenha");
const campoSenha = document.getElementById("senha");

if (botaoMostrarSenha && campoSenha) {
    botaoMostrarSenha.addEventListener("click", function () {
        const senhaEstaVisivel = campoSenha.type === "text";

        campoSenha.type = senhaEstaVisivel ? "password" : "text";
        botaoMostrarSenha.textContent = senhaEstaVisivel
            ? "Mostrar"
            : "Ocultar";
    });
}