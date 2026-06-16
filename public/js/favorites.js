// /public/js/favorites.js
document.addEventListener("click", function (e) {
    const fav = e.target.closest(".favorite-toggle");
    if (!fav) return;

    e.preventDefault();
    e.stopPropagation();

    const menuUrl = fav.dataset.menuUrl;
    if (!menuUrl) return;

    const formData = new FormData();
    formData.append("menu_url", menuUrl);

    fetch("/api/toggle_favorite.php", {
        method: "POST",
        body: formData
    })
        .then(async (r) => {
            const text = await r.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (err) {
                console.error("Keine valide JSON-Antwort:", text);
                return;
            }

            if (!data.success) {
                console.error(data.message || "Fehler beim Speichern des Favoriten");
                return;
            }
            fav.textContent = data.favorite ? "★" : "☆";
        })
        .catch(err => console.error("Fetch-Fehler:", err));
});
