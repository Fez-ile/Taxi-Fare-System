document.addEventListener("DOMContentLoaded", function () {
    const fareForm = document.getElementById("fareForm");
    const fromSelect = document.getElementById("from");
    const toSelect = document.getElementById("to");
    const message = document.getElementById("message");

    if (fareForm && fromSelect && toSelect && message) {
        fareForm.addEventListener("submit", function (event) {
            const fromValue = fromSelect.value.trim();
            const toValue = toSelect.value.trim();

            message.textContent = "";
            message.classList.remove("show");

            if (fromValue === "" || toValue === "") {
                event.preventDefault();
                message.textContent = "Please select both starting location and destination.";
                message.classList.add("show");
                return;
            }

            if (fromValue === toValue) {
                event.preventDefault();
                message.textContent = "Starting location and destination cannot be the same.";
                message.classList.add("show");
            }
        });
    }

    const deleteLinks = document.querySelectorAll(".delete-link");

    deleteLinks.forEach(function (link) {
        link.addEventListener("click", function (event) {
            const confirmed = confirm("Are you sure you want to delete this route?");
            if (!confirmed) {
                event.preventDefault();
            }
        });
    });
});