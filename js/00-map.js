document.addEventListener('DOMContentLoaded', function () {
    // Center map on Italy
    const map = L.map('pa-map').setView([41.8719, 12.5674], 6);

    // OpenStreetMap tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    const markers = L.markerClusterGroup({
        chunkedLoading: true,
        maxClusterRadius: 50
    });

    fetch('/ajax/get_map_data')
        .then(res => res.json())
        .then(data => {
            const geoJsonLayer = L.geoJSON(data, {
                onEachFeature: function (feature, layer) {
                    const props = feature.properties;
                    const popupContent = `
                        <div class="p-1">
                            <span class="badge bg-primary mb-1">${props.tipologia}</span>
                            <h6 class="fw-bold mb-1">${props.denominazione}</h6>
                            <small class="text-muted d-block mb-2">IPA: ${props.codice_ipa}</small>
                            <a href="${props.url}" class="btn btn-sm btn-outline-primary w-100 rounded-pill">
                                Scheda Dettaglio <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    `;
                    layer.bindPopup(popupContent);
                }
            });

            markers.addLayer(geoJsonLayer);
            map.addLayer(markers);
        })
        .catch(err => console.error('Error loading map features:', err));
});
