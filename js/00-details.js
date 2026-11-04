document.addEventListener('DOMContentLoaded', function () {
    const mapElement = document.getElementById('detail-map');

    // Se l'elemento mappa non è presente nella pagina, interrompi
    if (!mapElement) {
        return;
    }

    // Lettura dei dati dagli attributi HTML data-*
    const lat = parseFloat(mapElement.dataset.lat);
    const lng = parseFloat(mapElement.dataset.lng);
    const title = mapElement.dataset.title || 'Ente Pubblico';
    const address = mapElement.dataset.address || 'Sede Istituzionale';

    if (isNaN(lat) || isNaN(lng)) {
        console.error('Coordinate geografiche non valide per la mappa.');
        return;
    }

    const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');
        [...popoverTriggerList].map(popoverTriggerEl => new bootstrap.Popover(popoverTriggerEl, {
            sanitize: false // Permette le classi Bootstrap interne (text-danger, bi-*, ecc.)
        }));

    // Inizializzazione Mappa Leaflet
    const map = L.map('detail-map').setView([lat, lng], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    // Marker con Popup
    L.marker([lat, lng]).addTo(map)
        .bindPopup(`<b>${title}</b><br>${address}`)
        .openPopup();
});
