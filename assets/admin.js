document.addEventListener('DOMContentLoaded', function () {
    initColorPicker();
    initPhotoUpload();
    initSortable();
    initCharts();
    initPeriodSelector();
    initImportButton();
    initBioCounter();
    initTemplateCards();
});

var dailyChart = null;
var linksChart = null;

function initColorPicker() {
    var el = document.querySelector('.bl-color-picker');
    if (!el || typeof jQuery === 'undefined') return;
    jQuery(el).wpColorPicker();
}

function initPhotoUpload() {
    var uploadBtn = document.getElementById('bl-photo-upload-btn');
    var removeBtn = document.getElementById('bl-photo-remove-btn');
    var preview = document.getElementById('bl-photo-preview');
    var input = document.getElementById('bl-photo-url');

    if (!uploadBtn || typeof wp === 'undefined' || typeof wp.media === 'undefined') return;

    uploadBtn.addEventListener('click', function (e) {
        e.preventDefault();
        var frame = wp.media({
            title: 'Choisir une photo de profil',
            button: { text: 'Utiliser cette image' },
            multiple: false,
            library: { type: 'image' }
        });

        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            var url = attachment.sizes && attachment.sizes.thumbnail
                ? attachment.sizes.thumbnail.url
                : attachment.url;
            input.value = url;
            preview.src = url;
            preview.style.display = '';
            if (removeBtn) removeBtn.style.display = '';
        });

        frame.open();
    });

    if (removeBtn) {
        removeBtn.addEventListener('click', function () {
            input.value = '';
            preview.src = '';
            preview.style.display = 'none';
            removeBtn.style.display = 'none';
        });
    }
}

function initSortable() {
    var tbody = document.getElementById('bl-sortable-links');
    if (!tbody || typeof Sortable === 'undefined') return;

    Sortable.create(tbody, {
        handle: '.bl-drag-handle',
        animation: 150,
        ghostClass: 'sortable-ghost',
        onEnd: function () {
            var rows = tbody.querySelectorAll('tr[data-id]');
            var order = [];
            rows.forEach(function (row) {
                order.push(row.getAttribute('data-id'));
            });

            var params = new URLSearchParams();
            params.append('action', 'biolinks_reorder');
            params.append('nonce', biolinksAdmin.nonce);
            order.forEach(function (id) {
                params.append('order[]', id);
            });

            fetch(biolinksAdmin.ajax_url, {
                method: 'POST',
                body: params,
            });
        }
    });
}

function initCharts() {
    var dailyCtx = document.getElementById('bl-chart-daily');
    var linksCtx = document.getElementById('bl-chart-links');

    if (!dailyCtx || !linksCtx || typeof blChartData === 'undefined' || typeof Chart === 'undefined') return;

    dailyChart = new Chart(dailyCtx.getContext('2d'), {
        type: 'line',
        data: {
            labels: blChartData.daily.labels,
            datasets: [{
                label: 'Clics',
                data: blChartData.daily.values,
                borderColor: '#0a7286',
                backgroundColor: 'rgba(10, 114, 134, 0.1)',
                fill: true,
                tension: 0.3,
                pointRadius: 3,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 } }
            }
        }
    });

    linksChart = new Chart(linksCtx.getContext('2d'), {
        type: 'bar',
        data: {
            labels: blChartData.links.labels,
            datasets: [{
                label: 'Clics',
                data: blChartData.links.values,
                backgroundColor: '#0a7286',
                borderRadius: 4,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, ticks: { stepSize: 1 } }
            }
        }
    });
}

function initPeriodSelector() {
    var select = document.getElementById('bl-period-select');
    if (!select) return;

    select.addEventListener('change', function () {
        var days = this.value;
        var params = new URLSearchParams();
        params.append('action', 'biolinks_stats');
        params.append('nonce', biolinksAdmin.nonce);
        params.append('days', days);

        fetch(biolinksAdmin.ajax_url, {
            method: 'POST',
            body: params,
        })
        .then(function (r) { return r.json(); })
        .then(function (response) {
            if (!response.success) return;

            dailyChart.data.labels = response.data.daily.labels;
            dailyChart.data.datasets[0].data = response.data.daily.values;
            dailyChart.update();

            linksChart.data.labels = response.data.links.labels;
            linksChart.data.datasets[0].data = response.data.links.values;
            linksChart.update();
        });
    });
}

function initImportButton() {
    var btn = document.getElementById('bl-import-btn');
    if (!btn) return;

    btn.addEventListener('click', function () {
        if (!confirm('Importer les liens depuis Click Tracker ?')) return;

        btn.disabled = true;
        btn.textContent = 'Import en cours...';

        var params = new URLSearchParams();
        params.append('action', 'biolinks_import');
        params.append('nonce', biolinksAdmin.nonce);

        fetch(biolinksAdmin.ajax_url, {
            method: 'POST',
            body: params,
        })
        .then(function (r) { return r.json(); })
        .then(function (response) {
            if (response.success) {
                window.location.href = biolinksAdmin.ajax_url.replace('admin-ajax.php', 'admin.php') + '?page=biolinks&tab=page&imported=' + response.data.imported;
            } else {
                btn.disabled = false;
                btn.textContent = 'Importer les liens';
                alert('Erreur lors de l\'import.');
            }
        });
    });
}

function initBioCounter() {
    var textarea = document.getElementById('bl-bio');
    var counter = document.getElementById('bl-bio-count');
    if (!textarea || !counter) return;

    textarea.addEventListener('input', function () {
        counter.textContent = this.value.length;
    });
}

function initTemplateCards() {
    var cards = document.querySelectorAll('.bl-template-card');
    cards.forEach(function (card) {
        card.addEventListener('click', function () {
            cards.forEach(function (c) { c.classList.remove('bl-template-active'); });
            card.classList.add('bl-template-active');
        });
    });
}
