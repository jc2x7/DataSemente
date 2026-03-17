/**
 * DataSemente - App principal (Power BI style)
 */

// Chart.js dark theme defaults
Chart.defaults.color = '#8b8ca7';
Chart.defaults.borderColor = '#2d2d44';
Chart.defaults.font.family = "'Inter', sans-serif";

const App = {
    currentPage: 1,
    currentSort: 'id',
    currentDir: 'asc',
    charts: {},
    heatmapData: null,
    heatmapMap: null,
    heatmapLayer: null,
    heatmapMetric: 'producao',
    filterOptions: null,
    geoMap: null,
    geoLayer: null,
    geoData: null,
    geoMetric: 'area',

    init() {
        this.bindSidebar();
        this.bindFilters();
        this.bindSort();
        this.bindMap();
        this.bindGeoMap();
        this.bindComparison();
        this.bindPDF();
        this.bindFilterToggle();
        this.loadFilters().then(() => this.applyFilters());
    },

    // ===== SIDEBAR NAV =====
    bindSidebar() {
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', () => {
                document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
                document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
                item.classList.add('active');
                const panel = document.getElementById('panel-' + item.dataset.panel);
                if (panel) panel.classList.add('active');
                document.getElementById('panel-title').textContent = item.textContent.trim();

                if (item.dataset.panel === 'dados') this.searchTable();
                if (item.dataset.panel === 'mapa') {
                    this.loadHeatmap();
                    setTimeout(() => { if (this.heatmapMap) this.heatmapMap.invalidateSize(); }, 200);
                }

                // Mobile: close sidebar
                document.getElementById('sidebar').classList.remove('open');
            });
        });

        document.getElementById('hamburger-btn').addEventListener('click', () => {
            document.getElementById('sidebar').classList.toggle('open');
        });
    },

    bindFilterToggle() {
        const toggle = document.getElementById('filter-toggle');
        const body = document.getElementById('filter-body');
        toggle.addEventListener('click', () => {
            const hidden = body.style.display === 'none';
            body.style.display = hidden ? '' : 'none';
            toggle.textContent = hidden ? 'Ocultar' : 'Mostrar';
        });
    },

    // ===== FILTERS =====
    bindFilters() {
        document.getElementById('btn-apply').addEventListener('click', () => this.applyFilters());
        document.getElementById('btn-clear').addEventListener('click', () => this.clearFilters());
        document.getElementById('btn-export-csv').addEventListener('click', () => this.exportCSV());

        document.querySelectorAll('.filter-group input').forEach(el => {
            el.addEventListener('keypress', (e) => { if (e.key === 'Enter') this.applyFilters(); });
        });

        // Cultivar depends on especie
        document.getElementById('filter-especie').addEventListener('change', () => {
            this.loadCultivaresForEspecie();
        });
    },

    async loadFilters() {
        try {
            const res = await fetch('api/filters.php');
            const data = await res.json();
            if (!data.success) return;
            this.filterOptions = data.filters;

            this.populateSelect('filter-safra', data.filters.safras, true);
            this.populateSelect('filter-especie', data.filters.especies);
            this.populateSelect('filter-categoria', data.filters.categorias);
            this.populateSelect('filter-uf', data.filters.estados, true);
            this.populateSelect('filter-status', data.filters.statuses);
            this.updateStats(data.stats);
        } catch (err) {
            console.error('Erro ao carregar filtros:', err);
        }
    },

    async loadCultivaresForEspecie() {
        const especie = document.getElementById('filter-especie').value;
        const select = document.getElementById('filter-cultivar');
        select.innerHTML = '<option value="">Carregando...</option>';

        if (!especie) {
            select.innerHTML = '<option value="">Todas (selecione espécie)</option>';
            return;
        }

        try {
            const res = await fetch('api/stats.php?type=cultivares&especie=' + encodeURIComponent(especie));
            const data = await res.json();
            if (!data.success) return;

            select.innerHTML = '<option value="">Todas as cultivares</option>';
            data.data.forEach(cv => {
                const opt = document.createElement('option');
                opt.value = cv;
                opt.textContent = cv;
                select.appendChild(opt);
            });
        } catch (err) {
            select.innerHTML = '<option value="">Erro ao carregar</option>';
        }
    },

    populateSelect(id, options, isMultiple) {
        const select = document.getElementById(id);
        if (isMultiple) {
            select.innerHTML = '';
        } else {
            const first = select.options[0];
            select.innerHTML = '';
            if (first) select.appendChild(first);
        }
        options.forEach(opt => {
            const o = document.createElement('option');
            o.value = opt;
            o.textContent = opt;
            select.appendChild(o);
        });
    },

    updateStats(stats) {
        const fmt = (n) => Number(n).toLocaleString('pt-BR');
        const fmtD = (n) => Number(n).toLocaleString('pt-BR', { maximumFractionDigits: 0 });
        document.getElementById('stat-registros').textContent = fmt(stats.total_registros);
        document.getElementById('stat-especies').textContent = fmt(stats.total_especies);
        document.getElementById('stat-cultivares').textContent = fmt(stats.total_cultivares);
        document.getElementById('stat-estados').textContent = fmt(stats.total_estados);
        document.getElementById('stat-municipios').textContent = fmt(stats.total_municipios);
        document.getElementById('stat-area').textContent = fmtD(stats.total_area);
        document.getElementById('stat-producao').textContent = fmtD(stats.total_producao);
    },

    getFilterParams() {
        const params = new URLSearchParams();
        const safras = Array.from(document.getElementById('filter-safra').selectedOptions).map(o => o.value);
        safras.forEach(s => params.append('safra[]', s));
        const especie = document.getElementById('filter-especie').value;
        if (especie) params.set('especie', especie);
        const cultivar = document.getElementById('filter-cultivar').value;
        if (cultivar) params.set('cultivar', cultivar);
        const categoria = document.getElementById('filter-categoria').value;
        if (categoria) params.set('categoria', categoria);
        const ufs = Array.from(document.getElementById('filter-uf').selectedOptions).map(o => o.value);
        ufs.forEach(u => params.append('uf[]', u));
        const municipio = document.getElementById('filter-municipio').value;
        if (municipio) params.set('municipio', municipio);
        const status = document.getElementById('filter-status').value;
        if (status) params.set('status', status);
        const busca = document.getElementById('filter-busca').value;
        if (busca) params.set('busca', busca);
        return params;
    },

    applyFilters() {
        this.currentPage = 1;
        this.loadDashboard();
        this.loadHeatmap();
        const activePanel = document.querySelector('.nav-item.active');
        if (activePanel && activePanel.dataset.panel === 'dados') this.searchTable();
    },

    clearFilters() {
        document.getElementById('filter-busca').value = '';
        document.getElementById('filter-especie').value = '';
        document.getElementById('filter-cultivar').innerHTML = '<option value="">Todas (selecione espécie)</option>';
        document.getElementById('filter-categoria').value = '';
        document.getElementById('filter-municipio').value = '';
        document.getElementById('filter-status').value = '';
        document.getElementById('filter-safra').selectedIndex = -1;
        document.getElementById('filter-uf').selectedIndex = -1;
        this.applyFilters();
    },

    // ===== DASHBOARD =====
    async loadDashboard() {
        const params = this.getFilterParams();
        await Promise.all([
            this.loadGeoMap(),
            this.loadEvolutionChart(params),
            this.loadRankingChart('chart-ranking-uf', 'uf', 'producao_estimada', params),
            this.loadRankingChart('chart-ranking-cultivar', 'cultivar', 'producao_estimada', params),
            this.loadRankingChart('chart-ranking-municipio', 'municipio', 'area', params),
        ]);
    },

    async loadEvolutionChart(params) {
        try {
            const p = new URLSearchParams(params);
            p.set('type', 'evolucao');
            p.set('metric', 'producao_estimada');
            const res = await fetch('api/stats.php?' + p.toString());
            const data = await res.json();
            if (!data.success) return;
            this.renderLineChart('chart-evolucao', data.safras, data.series, 'Produção Estimada (t)');
        } catch (err) { console.error(err); }
    },

    async loadRankingChart(canvasId, by, metric, params) {
        try {
            const p = new URLSearchParams(params);
            p.set('type', 'ranking');
            p.set('by', by);
            p.set('metric', metric);
            p.set('limit', '10');
            const res = await fetch('api/stats.php?' + p.toString());
            const data = await res.json();
            if (!data.success) return;
            const labels = data.data.map(d => d.label);
            const values = data.data.map(d => parseFloat(d.valor));
            const label = metric === 'area' ? 'Área (ha)' : 'Produção (t)';
            this.renderBarChart(canvasId, labels, values, label);
        } catch (err) { console.error(err); }
    },

    // ===== CHARTS =====
    renderLineChart(canvasId, labels, seriesData, label) {
        if (this.charts[canvasId]) this.charts[canvasId].destroy();
        const colors = ['#6c5ce7', '#00b894', '#e17055', '#0984e3', '#fdcb6e', '#00cec9'];
        const datasets = [];
        let i = 0;
        Object.entries(seriesData).forEach(([name, values]) => {
            const c = colors[i % colors.length];
            datasets.push({
                label: name,
                data: labels.map(l => values[l] || 0),
                borderColor: c,
                backgroundColor: c + '18',
                fill: Object.keys(seriesData).length === 1,
                tension: 0.4,
                pointRadius: 4,
                pointHoverRadius: 7,
                borderWidth: 2.5,
            });
            i++;
        });
        this.charts[canvasId] = new Chart(document.getElementById(canvasId), {
            type: 'line',
            data: { labels, datasets },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { display: datasets.length > 1, labels: { usePointStyle: true, padding: 16 } },
                    tooltip: {
                        backgroundColor: '#1e1e2d',
                        borderColor: '#2d2d44',
                        borderWidth: 1,
                        titleColor: '#a29bfe',
                        bodyColor: '#e8e8f0',
                        callbacks: { label: (ctx) => `${ctx.dataset.label}: ${Number(ctx.parsed.y).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}` }
                    }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#1e1e2d' }, ticks: { callback: (v) => Number(v).toLocaleString('pt-BR') } },
                    x: { grid: { color: '#1e1e2d' } }
                },
                interaction: { intersect: false, mode: 'index' },
            }
        });
    },

    renderBarChart(canvasId, labels, values, label) {
        if (this.charts[canvasId]) this.charts[canvasId].destroy();
        this.charts[canvasId] = new Chart(document.getElementById(canvasId), {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label,
                    data: values,
                    backgroundColor: 'rgba(108, 92, 231, 0.7)',
                    borderColor: '#6c5ce7',
                    borderWidth: 1,
                    borderRadius: 6,
                    hoverBackgroundColor: '#a29bfe',
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1e1e2d',
                        borderColor: '#2d2d44',
                        borderWidth: 1,
                        titleColor: '#a29bfe',
                        bodyColor: '#e8e8f0',
                        callbacks: { label: (ctx) => `${label}: ${Number(ctx.parsed.x).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}` }
                    }
                },
                scales: {
                    x: { beginAtZero: true, grid: { color: '#1e1e2d' }, ticks: { callback: v => Number(v).toLocaleString('pt-BR') } },
                    y: { grid: { display: false } }
                }
            }
        });
    },

    // ===== HEATMAP (Leaflet por Município) =====
    bindMap() {
        document.querySelectorAll('[data-heatmap-metric]').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('[data-heatmap-metric]').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                this.heatmapMetric = btn.dataset.heatmapMetric;
                if (this.heatmapData) this.renderHeatmapMarkers();
            });
        });
    },

    initHeatmapMap() {
        if (this.heatmapMap) return;
        const mapEl = document.getElementById('heatmap-map');
        if (!mapEl) return;

        this.heatmapMap = L.map('heatmap-map', {
            center: [-14.5, -51.0],
            zoom: 4,
            minZoom: 3,
            maxZoom: 12,
            scrollWheelZoom: true,
        });

        L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
            subdomains: 'abcd',
            maxZoom: 19,
        }).addTo(this.heatmapMap);

        this.heatmapLayer = L.layerGroup().addTo(this.heatmapMap);
    },

    async loadHeatmap() {
        this.initHeatmapMap();
        if (!this.heatmapMap) return;

        try {
            const params = this.getFilterParams();
            const res = await fetch('api/geo.php?' + params.toString());
            const data = await res.json();
            if (!data.success) return;

            this.heatmapData = data.data;

            const subtitle = document.getElementById('heatmap-subtitle');
            if (subtitle) {
                const fmtN = n => Number(n).toLocaleString('pt-BR');
                subtitle.textContent = `${fmtN(data.totalMunicipios)} municipios · ${fmtN(data.totalRegistros)} registros`;
            }

            this.renderHeatmapMarkers();
            this.renderHeatmapTable(data.data);
        } catch (err) { console.error('Erro ao carregar heatmap:', err); }
    },

    renderHeatmapMarkers() {
        if (!this.heatmapLayer || !this.heatmapData) return;
        this.heatmapLayer.clearLayers();

        const items = this.heatmapData;
        if (!items.length) return;

        const metric = this.heatmapMetric;
        const values = items.map(d => d[metric]).filter(v => v > 0);
        if (!values.length) return;

        const maxVal = Math.max(...values);
        const minVal = Math.min(...values);

        const getColor = (val) => {
            if (maxVal === minVal) return '#3498db';
            const ratio = (val - minVal) / (maxVal - minVal);
            if (ratio < 0.2) return '#3498db';
            if (ratio < 0.4) return '#2ecc71';
            if (ratio < 0.6) return '#f1c40f';
            if (ratio < 0.8) return '#e67e22';
            return '#e74c3c';
        };

        const getRadius = (val) => {
            if (maxVal === minVal) return 8;
            const ratio = (val - minVal) / (maxVal - minVal);
            return 4 + ratio * 22;
        };

        const fmt = n => Number(n).toLocaleString('pt-BR', { maximumFractionDigits: 2 });

        items.forEach(item => {
            const val = item[metric] || 0;
            if (val <= 0) return;

            const circle = L.circleMarker([item.lat, item.lng], {
                radius: getRadius(val),
                fillColor: getColor(val),
                color: getColor(val),
                weight: 1,
                opacity: 0.8,
                fillOpacity: 0.55,
            });

            circle.bindPopup(
                `<div style="font-family:Inter,sans-serif;font-size:13px;line-height:1.7;">` +
                `<strong style="font-size:14px;">${item.municipio}</strong> - ${item.uf}<br>` +
                `Area: <strong>${fmt(item.area)} ha</strong><br>` +
                `Producao: <strong>${fmt(item.producao)} t</strong><br>` +
                `Registros: <strong>${fmt(item.registros)}</strong><br>` +
                `Cultivares: <strong>${item.cultivares}</strong>` +
                `</div>`,
                { closeButton: false, className: 'geo-popup' }
            );

            circle.bindTooltip(item.municipio, {
                permanent: false, direction: 'top', offset: [0, -8], className: 'geo-tooltip'
            });

            this.heatmapLayer.addLayer(circle);
        });

        // Legenda
        const legend = document.getElementById('heatmap-legend');
        if (legend) {
            const metricLabels = { area: 'Area (ha)', producao: 'Producao (t)', registros: 'Registros' };
            const fmtShort = (n) => {
                if (n >= 1e6) return (n / 1e6).toFixed(1) + 'M';
                if (n >= 1e3) return (n / 1e3).toFixed(1) + 'K';
                return Math.round(n).toString();
            };
            legend.innerHTML = `
                <span class="geo-legend-title">${metricLabels[metric]}</span>
                <span class="geo-legend-label">${fmtShort(minVal)}</span>
                <div class="geo-legend-gradient"></div>
                <span class="geo-legend-label">${fmtShort(maxVal)}</span>
            `;
        }
    },

    renderHeatmapTable(items) {
        const tbody = document.getElementById('tbody-heatmap-detail');
        const countEl = document.getElementById('heatmap-table-count');
        if (!tbody) return;
        tbody.innerHTML = '';

        const sorted = [...items].sort((a, b) => b.producao - a.producao);
        const fmt = n => Number(n).toLocaleString('pt-BR', { maximumFractionDigits: 2 });
        const fmtI = n => Number(n).toLocaleString('pt-BR');

        sorted.forEach((row, idx) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td>${idx + 1}</td><td>${this.esc(row.municipio)}</td><td>${this.esc(row.uf)}</td><td class="num">${fmt(row.area)}</td><td class="num">${fmt(row.producao)}</td><td class="num">${fmtI(row.registros)}</td><td class="num">${fmtI(row.cultivares)}</td>`;
            tbody.appendChild(tr);
        });

        if (countEl) countEl.textContent = `${fmtI(sorted.length)} municipios`;
    },

    // ===== GEO MAP (Leaflet) =====
    bindGeoMap() {
        document.querySelectorAll('[data-geo-metric]').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('[data-geo-metric]').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                this.geoMetric = btn.dataset.geoMetric;
                if (this.geoData) this.renderGeoMarkers();
            });
        });
    },

    initGeoMap() {
        if (this.geoMap) return;
        const mapEl = document.getElementById('geo-map');
        if (!mapEl) return;

        this.geoMap = L.map('geo-map', {
            center: [-14.5, -51.0],
            zoom: 4,
            minZoom: 3,
            maxZoom: 12,
            scrollWheelZoom: true,
        });

        L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
            subdomains: 'abcd',
            maxZoom: 19,
        }).addTo(this.geoMap);

        this.geoLayer = L.layerGroup().addTo(this.geoMap);
    },

    async loadGeoMap() {
        this.initGeoMap();
        if (!this.geoMap) return;

        try {
            const params = this.getFilterParams();
            const res = await fetch('api/geo.php?' + params.toString());
            const data = await res.json();
            if (!data.success) return;

            this.geoData = data;
            const subtitle = document.getElementById('geo-map-subtitle');
            if (subtitle) {
                const fmtN = n => Number(n).toLocaleString('pt-BR');
                subtitle.textContent = `${fmtN(data.totalMunicipios)} municípios · ${fmtN(data.totalRegistros)} registros`;
            }
            this.renderGeoMarkers();
        } catch (err) {
            console.error('Erro ao carregar geo:', err);
        }
    },

    renderGeoMarkers() {
        if (!this.geoLayer || !this.geoData) return;
        this.geoLayer.clearLayers();

        const items = this.geoData.data;
        if (!items.length) return;

        const metric = this.geoMetric;
        const values = items.map(d => d[metric]).filter(v => v > 0);
        if (!values.length) return;

        const maxVal = Math.max(...values);
        const minVal = Math.min(...values);

        const getColor = (val) => {
            if (maxVal === minVal) return '#3498db';
            const ratio = (val - minVal) / (maxVal - minVal);
            if (ratio < 0.2) return '#3498db';
            if (ratio < 0.4) return '#2ecc71';
            if (ratio < 0.6) return '#f1c40f';
            if (ratio < 0.8) return '#e67e22';
            return '#e74c3c';
        };

        const getRadius = (val) => {
            if (maxVal === minVal) return 8;
            const ratio = (val - minVal) / (maxVal - minVal);
            return 4 + ratio * 22;
        };

        const fmt = n => Number(n).toLocaleString('pt-BR', { maximumFractionDigits: 2 });

        items.forEach(item => {
            const val = item[metric] || 0;
            if (val <= 0) return;

            const circle = L.circleMarker([item.lat, item.lng], {
                radius: getRadius(val),
                fillColor: getColor(val),
                color: getColor(val),
                weight: 1,
                opacity: 0.8,
                fillOpacity: 0.55,
            });

            const metricLabels = { area: 'Área (ha)', producao: 'Produção (t)', registros: 'Registros' };
            circle.bindPopup(
                `<div style="font-family:Inter,sans-serif;font-size:13px;line-height:1.7;">` +
                `<strong style="font-size:14px;">${item.municipio}</strong> - ${item.uf}<br>` +
                `Área: <strong>${fmt(item.area)} ha</strong><br>` +
                `Produção: <strong>${fmt(item.producao)} t</strong><br>` +
                `Registros: <strong>${fmt(item.registros)}</strong><br>` +
                `Cultivares: <strong>${item.cultivares}</strong>` +
                `</div>`,
                { closeButton: false, className: 'geo-popup' }
            );

            circle.bindTooltip(item.municipio, {
                permanent: false,
                direction: 'top',
                offset: [0, -8],
                className: 'geo-tooltip'
            });

            this.geoLayer.addLayer(circle);
        });

        // Legenda
        const legend = document.getElementById('geo-legend');
        if (legend) {
            const metricLabels = { area: 'Área (ha)', producao: 'Produção (t)', registros: 'Registros' };
            const fmtShort = (n) => {
                if (n >= 1e6) return (n / 1e6).toFixed(1) + 'M';
                if (n >= 1e3) return (n / 1e3).toFixed(1) + 'K';
                return Math.round(n).toString();
            };
            legend.innerHTML = `
                <span class="geo-legend-title">${metricLabels[metric]}</span>
                <span class="geo-legend-label">${fmtShort(minVal)}</span>
                <div class="geo-legend-gradient"></div>
                <span class="geo-legend-label">${fmtShort(maxVal)}</span>
            `;
        }
    },

    // ===== COMPARISON =====
    bindComparison() {
        document.getElementById('compare-type').addEventListener('change', () => this.updateCompareOptions());
        this.updateCompareOptions();
        document.getElementById('btn-compare').addEventListener('click', () => this.runComparison());
    },

    updateCompareOptions() {
        const type = document.getElementById('compare-type').value;
        const select = document.getElementById('compare-items');
        select.innerHTML = '';
        if (!this.filterOptions) return;

        let options = [];
        if (type === 'safra') options = this.filterOptions.safras;
        else if (type === 'cultivar' || type === 'cultivar_evolucao') { this.loadCompareCultivares(); return; }
        else if (type === 'uf_evolucao') options = this.filterOptions.estados;

        options.forEach(opt => {
            const o = document.createElement('option');
            o.value = opt; o.textContent = opt;
            select.appendChild(o);
        });
    },

    async loadCompareCultivares() {
        try {
            const params = this.getFilterParams();
            params.set('type', 'cultivares');
            const res = await fetch('api/stats.php?' + params.toString());
            const data = await res.json();
            if (!data.success) return;
            const select = document.getElementById('compare-items');
            select.innerHTML = '';
            data.data.forEach(cv => {
                const o = document.createElement('option');
                o.value = cv; o.textContent = cv;
                select.appendChild(o);
            });
        } catch (err) { console.error(err); }
    },

    async runComparison() {
        const type = document.getElementById('compare-type').value;
        const selected = Array.from(document.getElementById('compare-items').selectedOptions).map(o => o.value);
        if (selected.length < 2) { alert('Selecione pelo menos 2 itens (Ctrl+click).'); return; }
        if (type === 'safra' || type === 'cultivar') await this.runCardComparison(type, selected);
        else if (type === 'uf_evolucao') await this.runEvolutionComparison('uf', selected);
        else if (type === 'cultivar_evolucao') await this.runEvolutionComparison('cultivar', selected);
        document.getElementById('comparison-results').style.display = 'block';
    },

    async runCardComparison(type, items) {
        try {
            const params = new URLSearchParams();
            params.set('type', 'comparativo');
            params.set('compare', type);
            items.forEach(item => params.append(type + '[]', item));
            const filterParams = this.getFilterParams();
            for (const [key, value] of filterParams.entries()) {
                if (!key.startsWith(type)) params.append(key, value);
            }

            const res = await fetch('api/stats.php?' + params.toString());
            const data = await res.json();
            if (!data.success) { alert(data.error); return; }

            const container = document.getElementById('compare-cards');
            container.innerHTML = '';
            const fmt = n => Number(n).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
            const fmtI = n => Number(n).toLocaleString('pt-BR');
            const cardColors = ['#6c5ce7', '#00b894', '#e17055', '#0984e3', '#fdcb6e'];

            data.data.forEach((item, idx) => {
                const card = document.createElement('div');
                card.className = 'compare-card';
                card.style.borderTopColor = cardColors[idx % cardColors.length];
                card.innerHTML = `
                    <h3>${this.esc(item.label)}</h3>
                    <div class="metric-row"><span>Registros</span><span class="metric-value">${fmtI(item.registros)}</span></div>
                    <div class="metric-row"><span>Área Total (ha)</span><span class="metric-value">${fmt(item.total_area)}</span></div>
                    <div class="metric-row"><span>Prod. Estimada (t)</span><span class="metric-value">${fmt(item.total_producao)}</span></div>
                    <div class="metric-row"><span>Prod. Bruta (t)</span><span class="metric-value">${fmt(item.total_producao_bruta)}</span></div>
                    <div class="metric-row"><span>Cultivares</span><span class="metric-value">${fmtI(item.total_cultivares)}</span></div>
                    <div class="metric-row"><span>Municípios</span><span class="metric-value">${fmtI(item.total_municipios)}</span></div>
                    <div class="metric-row"><span>Estados</span><span class="metric-value">${fmtI(item.total_estados)}</span></div>`;
                container.appendChild(card);
            });

            if (this.charts['chart-comparativo']) this.charts['chart-comparativo'].destroy();
            this.charts['chart-comparativo'] = new Chart(document.getElementById('chart-comparativo'), {
                type: 'bar',
                data: {
                    labels: data.data.map(d => d.label),
                    datasets: [
                        { label: 'Área (ha)', data: data.data.map(d => parseFloat(d.total_area)), backgroundColor: 'rgba(108,92,231,0.7)' },
                        { label: 'Prod. Estimada (t)', data: data.data.map(d => parseFloat(d.total_producao)), backgroundColor: 'rgba(0,184,148,0.7)' },
                        { label: 'Prod. Bruta (t)', data: data.data.map(d => parseFloat(d.total_producao_bruta)), backgroundColor: 'rgba(225,112,85,0.7)' },
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { tooltip: { backgroundColor: '#1e1e2d', borderColor: '#2d2d44', borderWidth: 1, titleColor: '#a29bfe', bodyColor: '#e8e8f0' } },
                    scales: {
                        y: { beginAtZero: true, grid: { color: '#1e1e2d' }, ticks: { callback: v => Number(v).toLocaleString('pt-BR') } },
                        x: { grid: { color: '#1e1e2d' } }
                    }
                }
            });
        } catch (err) { console.error(err); }
    },

    async runEvolutionComparison(groupBy, items) {
        try {
            const params = this.getFilterParams();
            params.set('type', 'evolucao');
            params.set('metric', 'producao_estimada');
            params.set('group', groupBy);
            items.forEach(item => params.append(groupBy + '[]', item));
            const res = await fetch('api/stats.php?' + params.toString());
            const data = await res.json();
            if (!data.success) return;
            document.getElementById('compare-cards').innerHTML = '';
            this.renderLineChart('chart-comparativo', data.safras, data.series, 'Produção Estimada (t)');
        } catch (err) { console.error(err); }
    },

    // ===== TABLE =====
    bindSort() {
        document.querySelectorAll('th[data-sort]').forEach(th => {
            th.addEventListener('click', () => {
                const col = th.dataset.sort;
                if (this.currentSort === col) this.currentDir = this.currentDir === 'asc' ? 'desc' : 'asc';
                else { this.currentSort = col; this.currentDir = 'asc'; }
                this.updateSortIndicators();
                this.searchTable();
            });
        });
    },

    updateSortIndicators() {
        document.querySelectorAll('th[data-sort]').forEach(th => {
            th.classList.remove('sorted');
            const icon = th.querySelector('.sort-icon');
            if (icon) icon.textContent = '\u2195';
        });
        const active = document.querySelector(`th[data-sort="${this.currentSort}"]`);
        if (active) {
            active.classList.add('sorted');
            const icon = active.querySelector('.sort-icon');
            if (icon) icon.textContent = this.currentDir === 'asc' ? '\u2191' : '\u2193';
        }
    },

    async searchTable() {
        const tbody = document.getElementById('results-body');
        const loading = document.getElementById('loading');
        const tableCard = document.getElementById('table-card');
        tbody.innerHTML = '';
        loading.style.display = 'block';
        tableCard.style.display = 'none';

        try {
            const params = this.getFilterParams();
            params.set('page', this.currentPage);
            params.set('sort', this.currentSort);
            params.set('dir', this.currentDir);
            const res = await fetch('api/search.php?' + params.toString());
            const data = await res.json();
            loading.style.display = 'none';

            if (!data.success) {
                tbody.innerHTML = `<tr><td colspan="12" style="text-align:center;padding:20px;color:#e17055;">${data.error}</td></tr>`;
                tableCard.style.display = 'block';
                return;
            }

            document.getElementById('results-count').textContent = `${data.pagination.totalRows.toLocaleString('pt-BR')} registro(s)`;
            document.getElementById('results-page').textContent = `Página ${data.pagination.page} de ${data.pagination.totalPages}`;

            if (data.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="12" style="text-align:center;padding:30px;color:#5a5b75;">Nenhum registro encontrado.</td></tr>';
            } else {
                data.data.forEach(row => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${this.esc(row.safra)}</td>
                        <td>${this.esc(row.especie)}</td>
                        <td>${this.esc(row.categoria)}</td>
                        <td>${this.esc(row.cultivar)}</td>
                        <td>${this.esc(row.municipio)}</td>
                        <td>${this.esc(row.uf)}</td>
                        <td>${this.esc(row.status_registro)}</td>
                        <td class="num">${row.area_fmt || '-'}</td>
                        <td class="num">${row.producao_bruta_fmt || '-'}</td>
                        <td class="num">${row.producao_estimada_fmt || '-'}</td>
                        <td>${row.data_plantio_fmt || '-'}</td>
                        <td>${row.data_colheita_fmt || '-'}</td>`;
                    tbody.appendChild(tr);
                });
            }
            tableCard.style.display = 'block';
            this.renderPagination(data.pagination);
        } catch (err) {
            loading.style.display = 'none';
            tableCard.style.display = 'block';
            tbody.innerHTML = '<tr><td colspan="12" style="text-align:center;padding:20px;color:#e17055;">Erro de conexão.</td></tr>';
        }
    },

    renderPagination(pag) {
        const c = document.getElementById('pagination');
        c.innerHTML = '';
        if (pag.totalPages <= 1) return;
        const addBtn = (text, page, disabled, active) => {
            const btn = document.createElement('button');
            btn.textContent = text;
            btn.disabled = disabled;
            if (active) btn.classList.add('active');
            if (!disabled) btn.addEventListener('click', () => { this.currentPage = page; this.searchTable(); window.scrollTo({ top: 0, behavior: 'smooth' }); });
            c.appendChild(btn);
        };
        addBtn('Anterior', pag.page - 1, pag.page <= 1);
        const range = this.getPageRange(pag.page, pag.totalPages);
        range.forEach(p => {
            if (p === '...') { const s = document.createElement('span'); s.textContent = '...'; s.style.padding = '8px 4px'; s.style.color = '#5a5b75'; c.appendChild(s); }
            else addBtn(p, p, false, p === pag.page);
        });
        addBtn('Próximo', pag.page + 1, pag.page >= pag.totalPages);
    },

    getPageRange(current, total) {
        if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
        const pages = [1];
        if (current > 3) pages.push('...');
        for (let i = Math.max(2, current - 1); i <= Math.min(total - 1, current + 1); i++) pages.push(i);
        if (current < total - 2) pages.push('...');
        pages.push(total);
        return pages;
    },

    // ===== EXPORT =====
    exportCSV() {
        window.location.href = 'api/export.php?' + this.getFilterParams().toString();
    },

    // ===== PDF =====
    bindPDF() {
        document.getElementById('btn-pdf').addEventListener('click', () => this.generatePDF());
    },

    async generatePDF() {
        const btn = document.getElementById('btn-pdf');
        btn.disabled = true; btn.textContent = 'Gerando...';

        try {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('p', 'mm', 'a4');
            const W = doc.internal.pageSize.getWidth();
            const M = 15;
            let y = 20;

            // Header
            doc.setFillColor(30, 30, 45);
            doc.rect(0, 0, W, 32, 'F');
            doc.setFillColor(108, 92, 231);
            doc.rect(0, 32, W, 3, 'F');
            doc.setTextColor(162, 155, 254);
            doc.setFontSize(18);
            doc.setFont(undefined, 'bold');
            doc.text('DataSemente', M, 16);
            doc.setFontSize(9);
            doc.setTextColor(139, 140, 167);
            doc.text('Relatório Gerencial - Dados de Campo e Produção', M, 24);
            doc.text(new Date().toLocaleDateString('pt-BR') + ' ' + new Date().toLocaleTimeString('pt-BR'), W - M, 24, { align: 'right' });

            y = 44;
            doc.setTextColor(0, 0, 0);

            // Stats
            doc.setFontSize(12);
            doc.setTextColor(108, 92, 231);
            doc.setFont(undefined, 'bold');
            doc.text('Resumo Geral', M, y);
            y += 8;

            const stats = [
                ['Registros', document.getElementById('stat-registros').textContent],
                ['Espécies', document.getElementById('stat-especies').textContent],
                ['Cultivares', document.getElementById('stat-cultivares').textContent],
                ['Estados', document.getElementById('stat-estados').textContent],
                ['Municípios', document.getElementById('stat-municipios').textContent],
                ['Área Total (ha)', document.getElementById('stat-area').textContent],
                ['Produção (t)', document.getElementById('stat-producao').textContent],
            ];

            const colW = (W - 2 * M) / 4;
            stats.forEach((s, i) => {
                const col = i % 4, row = Math.floor(i / 4);
                const x = M + col * colW, sy = y + row * 14;
                doc.setFontSize(7); doc.setTextColor(120); doc.setFont(undefined, 'normal');
                doc.text(s[0], x, sy);
                doc.setFontSize(12); doc.setTextColor(30, 30, 45); doc.setFont(undefined, 'bold');
                doc.text(s[1], x, sy + 5);
            });
            y += Math.ceil(stats.length / 4) * 14 + 8;

            // Rankings
            const params = this.getFilterParams();
            for (const [title, by, metric, color] of [
                ['Ranking por Estado', 'uf', 'producao_estimada', [108, 92, 231]],
                ['Top 15 Cultivares', 'cultivar', 'producao_estimada', [0, 184, 148]],
                ['Top 15 Municípios', 'municipio', 'area', [225, 112, 85]],
            ]) {
                if (y > 220) { doc.addPage(); y = 20; }
                const p = new URLSearchParams(params);
                p.set('type', 'ranking'); p.set('by', by); p.set('metric', metric); p.set('limit', '15');
                const res = await fetch('api/stats.php?' + p.toString());
                const data = await res.json();
                if (!data.success || !data.data.length) continue;

                doc.setFontSize(12); doc.setTextColor(...color); doc.setFont(undefined, 'bold');
                doc.text(title, M, y); y += 4;

                doc.autoTable({
                    startY: y, margin: { left: M, right: M },
                    head: [['#', by === 'uf' ? 'UF' : by.charAt(0).toUpperCase() + by.slice(1), metric === 'area' ? 'Área (ha)' : 'Produção (t)', 'Registros']],
                    body: data.data.map((d, i) => [i + 1, d.label, Number(d.valor).toLocaleString('pt-BR', { minimumFractionDigits: 2 }), Number(d.registros).toLocaleString('pt-BR')]),
                    headStyles: { fillColor: color, fontSize: 7.5, cellPadding: 3 },
                    bodyStyles: { fontSize: 7.5, cellPadding: 2.5 },
                    alternateRowStyles: { fillColor: [245, 245, 250] },
                });
                y = doc.lastAutoTable.finalY + 12;
            }

            // Footer
            const totalPages = doc.internal.getNumberOfPages();
            for (let i = 1; i <= totalPages; i++) {
                doc.setPage(i);
                doc.setFontSize(7); doc.setTextColor(150);
                doc.text(`DataSemente - Página ${i} de ${totalPages}`, W / 2, doc.internal.pageSize.getHeight() - 8, { align: 'center' });
            }
            doc.save('datasemente_' + new Date().toISOString().slice(0, 10) + '.pdf');
        } catch (err) {
            console.error(err);
            alert('Erro ao gerar PDF.');
        }
        btn.disabled = false; btn.textContent = 'PDF';
    },

    esc(val) {
        if (val === null || val === undefined) return '-';
        const d = document.createElement('div');
        d.textContent = val;
        return d.innerHTML;
    }
};

document.addEventListener('DOMContentLoaded', () => App.init());
