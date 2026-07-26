const sidebar = document.getElementById("sidebar");
const sidebarToggle = document.getElementById("sidebarToggle");

if (sidebar && sidebarToggle) {
    sidebarToggle.addEventListener("click", () => {
        document.body.classList.toggle("sidebar-collapsed");
    });
}

document.querySelectorAll(".menu-group-button").forEach((button) => {
    button.addEventListener("click", () => {
        const group = button.closest(".menu-group");

        group.classList.toggle("open");
    });
});