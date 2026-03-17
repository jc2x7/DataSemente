/**
 * DataSemente - Painel Gerencial
 * Single-page dashboard para gestao de campos de sementes
 */

// Chart.js defaults
Chart.defaults.color = '#6b7280';
Chart.defaults.borderColor = '#e5e7eb';
Chart.defaults.font.family = "'Inter', sans-serif";

const App = {
    // State
    charts: {},
    map: null,
    mapLayer: null,
    mapMetric: 'producao',
    mapData: null,
    filterSelections: {},
    filterOptions: {},
    tablePage: 1,
    tableSort: 'id',
    tableSortDir: 'asc',
    initialized: false,

    // ==================== INIT ====================
    async init() {
        this.bindMetricButtons();
        this.bindHamburger();
        this.bindTableSort();
        this.bindTableSearch();
        this.bindActions();
        this.initMultiSelects();

        await this.loadFilters();
        await this.applyFilters();

        this.initialized = true;
        document.getElementById('loading-overlay').classList.add('hidden');
    },

    // ==================== METRIC BUTTONS ====================
    bindMetricButtons() {
        document.querySelectorAll('.metric-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.metric-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                this.mapMetric = btn.dataset.metric;
                if (this.mapData) this.renderMapMarkers();
            });
        });
    },

    // ==================== HAMBURGER ====================
    bindHamburger() {
        document.getElementById('hamburger-btn').addEventListener('click', () => {
            document.getElementById('sidebar').classList.toggle('open');
        });
        // Close sidebar on main click (mobile)
        document.querySelector('.main-content').addEventListener('click', () => {
            document.getElementById('sidebar').classList.remove('open');
        });
    },

    // ==================== ACTIONS ====================
    bindActions() {
        document.getElementById('btn-apply').addEventListener('click', () => {
            document.getElementById('sidebar').classList.remove('open');
            this.applyFilters();
        });
        document.getElementById('btn-clear').addEventListener('click', () => this.clearFilters());
        document.getElementById('btn-export-csv').addEventListener('click', () => this.exportCSV());
    },

    // ==================== MULTI-SELECT COMPONENT ====================
    initMultiSelects() {
        document.querySelectorAll('.multi-select').forEach(ms => {
            const filterName = ms.dataset.filter;
            this.filterSelections[filterName] = [];

            const trigger = ms.querySelector('.multi-select-trigger');
            const dropdown = ms.querySelector('.multi-select-dropdown');
            const searchInput = ms.querySelector('.dropdown-search input');
            const selectAllBtn = ms.querySelector('.select-all');
            const selectNoneBtn = ms.querySelector('.select-none');

            // Toggle dropdown
            trigger.addEventListener('click', (e) => {
                e.stopPropagation();
                // Close others
                document.querySelectorAll('.multi-select.open').forEach(other => {
                    if (other !== ms) other.classList.remove('open');
                });
                ms.classList.toggle('open');
                if (ms.classList.contains('open')) {
                    searchInput.value = '';
                    searchInput.focus();
                    this.filterDropdownOptions(ms, '');
                }
            });

            // Search
            searchInput.addEventListener('input', () => {
                this.filterDropdownOptions(ms, searchInput.value);
            });
            searchInput.addEventListener('click', (e) => e.stopPropagation());

            // Select all / none
            selectAllBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                ms.querySelectorAll('.dropdown-option:not(.hidden) input[type="checkbox"]').forEach(cb => { cb.checked = true; });
                this.syncSelections(ms);
            });
            selectNoneBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                ms.querySelectorAll('.dropdown-option input[type="checkbox"]').forEach(cb => { cb.checked = false; });
                this.syncSelections(ms);
            });
        });

        // Close all on outside click
        document.addEventListener('click', () => {
            document.querySelectorAll('.multi-select.open').forEach(ms => ms.classList.remove('open'));
        });
    },

    populateMultiSelect(filterName, options) {
        const ms = document.querySelector(`.multi-select[data-filter="${filterName}"]`);
        if (!ms) return;
        const container = ms.querySelector('.dropdown-options');
        container.innerHTML = '';
        this.filterOptions[filterName] = options;
        this.filterSelections[filterName] = [];

        options.forEach((opt, idx) => {
            const div = document.createElement('div');
            div.className = 'dropdown-option';
            div.dataset.value = opt;
            div.innerHTML = `<input type="checkbox" id="ms-${filterName}-${idx}" value="${this.esc(opt)}"><label for="ms-${filterName}-${idx}">${this.esc(opt)}</label>`;

            const cb = div.querySelector('input');
            cb.addEventListener('change', (e) => {
                e.stopPropagation();
                this.syncSelections(ms);
            });
            div.addEventListener('click', (e) => {
                if (e.target.tagName !== 'INPUT') {
                    cb.checked = !cb.checked;
                    this.syncSelections(ms);
                }
                e.stopPropagation();
            });
            container.appendChild(div);
        });
    },

    filterDropdownOptions(ms, query) {
        const q = query.toLowerCase().trim();
        ms.querySelectorAll('.dropdown-option').forEach(opt => {
            const text = opt.dataset.value.toLowerCase();
            opt.classList.toggle('hidden', q !== '' && !text.includes(q));
        });
    },

    syncSelections(ms) {
        const filterName = ms.dataset.filter;
        const selected = [];
        ms.querySelectorAll('.dropdown-option input:checked').forEach(cb => {
            selected.push(cb.value);
        });
        this.filterSelections[filterName] = selected;

        const triggerText = ms.querySelector('.trigger-text');
        const defaultTexts = {
            safra: 'Todas as safras',
            especie: 'Todas as especies',
            cultivar: 'Todas as cultivares',
            categoria: 'Todas as categorias',
            uf: 'Todos os estados',
            status: 'Todos os status',
        };

        if (selected.length === 0) {
            triggerText.textContent = defaultTexts[filterName] || 'Todos';
            triggerText.classList.remove('has-selection');
        } else if (selected.length === 1) {
            triggerText.textContent = selected[0];
            triggerText.classList.add('has-selection');
        } else {
            triggerText.textContent = `${selected.length} selecionados`;
            triggerText.classList.add('has-selection');
        }
    },

    // ==================== LOAD FILTERS FROM API ====================
    async loadFilters() {
        try {
            const res = await fetch('api/filters.php');
            const data = await res.json();
            if (!data.success) return;

            this.populateMultiSelect('safra', data.filters.safras);
            this.populateMultiSelect('especie', data.filters.especies);
            this.populateMultiSelect('cultivar', data.filters.cultivares);
            this.populateMultiSelect('categoria', data.filters.categorias);
            this.populateMultiSelect('uf', data.filters.estados);
            this.populateMultiSelect('status', data.filters.statuses);
        } catch (err) {
            console.error('Erro ao carregar filtros:', err);
        }
    },

    // ==================== FILTER PARAMS ====================
    getFilterParams() {
        const params = new URLSearchParams();
        const sel = this.filterSelections;

        if (sel.safra && sel.safra.length) sel.safra.forEach(s => params.append('safra[]', s));
        if (sel.especie && sel.especie.length) sel.especie.forEach(s => params.append('especie[]', s));
        if (sel.cultivar && sel.cultivar.length) sel.cultivar.forEach(s => params.append('cultivar[]', s));
        if (sel.categoria && sel.categoria.length) sel.categoria.forEach(s => params.append('categoria[]', s));
        if (sel.uf && sel.uf.length) sel.uf.forEach(s => params.append('uf[]', s));
        if (sel.status && sel.status.length) sel.status.forEach(s => params.append('status[]', s));

        return params;
    },

    // ==================== APPLY FILTERS ====================
    async applyFilters() {
        this.tablePage = 1;
        this.updateActiveFilterTags();

        // Load all data in parallel
        await Promise.all([
            this.loadKPIs(),
            this.loadCharts(),
            this.loadMap(),
            this.loadTable(),
        ]);
    },

    clearFilters() {
        document.querySelectorAll('.multi-select').forEach(ms => {
            ms.querySelectorAll('.dropdown-option input:checked').forEach(cb => { cb.checked = false; });
            this.syncSelections(ms);
        });
        this.applyFilters();
    },

    updateActiveFilterTags() {
        const container = document.getElementById('active-filters');
        container.innerHTML = '';
        const labels = {
            safra: 'Safra', especie: 'Especie', cultivar: 'Cultivar',
            categoria: 'Categoria', uf: 'UF', status: 'Status'
        };

        Object.entries(this.filterSelections).forEach(([key, values]) => {
            if (!values || !values.length) return;
            const tag = document.createElement('span');
            tag.className = 'filter-tag';
            const display = values.length <= 2 ? values.join(', ') : `${values.length} ${labels[key]}`;
            tag.innerHTML = `${labels[key]}: ${this.esc(display)} <span class="filter-tag-remove" data-filter="${key}">&times;</span>`;
            container.appendChild(tag);
        });

        container.querySelectorAll('.filter-tag-remove').forEach(btn => {
            btn.addEventListener('click', () => {
                const filterName = btn.dataset.filter;
                const ms = document.querySelector(`.multi-select[data-filter="${filterName}"]`);
                if (ms) {
                    ms.querySelectorAll('.dropdown-option input:checked').forEach(cb => { cb.checked = false; });
                    this.syncSelections(ms);
                }
                this.applyFilters();
            });
        });
    },

    // ==================== KPIs ====================
    async loadKPIs() {
        try {
            const params = this.getFilterParams();

            // Fetch heatmap (by UF) + species count + cultivares count in parallel
            const p1 = new URLSearchParams(params); p1.set('type', 'heatmap');
            const p2 = new URLSearchParams(params); p2.set('type', 'ranking'); p2.set('by', 'especie'); p2.set('metric', 'producao_estimada'); p2.set('limit', '200');
            const p3 = new URLSearchParams(params); p3.set('type', 'cultivares');

            const [res1, res2, res3] = await Promise.all([
                fetch('api/stats.php?' + p1.toString()).then(r => r.json()),
                fetch('api/stats.php?' + p2.toString()).then(r => r.json()),
                fetch('api/stats.php?' + p3.toString()).then(r => r.json()),
            ]);

            let registros = 0, area = 0, producao = 0, totalMunicipios = 0;
            if (res1.success) {
                res1.data.forEach(row => {
                    registros += parseInt(row.registros);
                    area += parseFloat(row.total_area);
                    producao += parseFloat(row.total_producao);
                    totalMunicipios += parseInt(row.total_municipios || 0);
                });
            }

            const fmt = n => Number(n).toLocaleString('pt-BR');
            const fmtD = n => Number(n).toLocaleString('pt-BR', { maximumFractionDigits: 0 });

            document.getElementById('kpi-registros').textContent = fmt(registros);
            document.getElementById('kpi-especies').textContent = fmt(res2.success ? res2.data.length : 0);
            document.getElementById('kpi-cultivares').textContent = fmt(res3.success ? res3.data.length : 0);
            document.getElementById('kpi-area').textContent = fmtD(area);
            document.getElementById('kpi-producao').textContent = fmtD(producao);
            document.getElementById('kpi-municipios').textContent = fmt(totalMunicipios);
        } catch (err) { console.error('Erro KPIs:', err); }
    },

    // ==================== CHARTS ====================
    async loadCharts() {
        const params = this.getFilterParams();
        await Promise.all([
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

            const canvasId = 'chart-evolucao';
            if (this.charts[canvasId]) this.charts[canvasId].destroy();

            const colors = ['#2563eb', '#059669', '#d97706', '#dc2626', '#7c3aed', '#0891b2'];
            const datasets = [];
            let i = 0;
            Object.entries(data.series).forEach(([name, values]) => {
                const c = colors[i % colors.length];
                datasets.push({
                    label: name,
                    data: data.safras.map(s => values[s] || 0),
                    borderColor: c,
                    backgroundColor: c + '18',
                    fill: Object.keys(data.series).length === 1,
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 7,
                    borderWidth: 2.5,
                });
                i++;
            });

            this.charts[canvasId] = new Chart(document.getElementById(canvasId), {
                type: 'line',
                data: { labels: data.safras, datasets },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: {
                        legend: { display: datasets.length > 1, labels: { usePointStyle: true, padding: 16 } },
                        tooltip: {
                            callbacks: { label: (ctx) => `${ctx.dataset.label}: ${Number(ctx.parsed.y).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}` }
                        }
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { callback: v => Number(v).toLocaleString('pt-BR') } },
                        x: { grid: { display: false } }
                    },
                    interaction: { intersect: false, mode: 'index' },
                }
            });
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

            if (this.charts[canvasId]) this.charts[canvasId].destroy();

            const labels = data.data.map(d => d.label);
            const values = data.data.map(d => parseFloat(d.valor));
            const label = metric === 'area' ? 'Area (ha)' : 'Producao (t)';

            this.charts[canvasId] = new Chart(document.getElementById(canvasId), {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label,
                        data: values,
                        backgroundColor: 'rgba(37, 99, 235, 0.7)',
                        borderColor: '#2563eb',
                        borderWidth: 1,
                        borderRadius: 4,
                        hoverBackgroundColor: '#1d4ed8',
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: { label: (ctx) => `${label}: ${Number(ctx.parsed.x).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}` }
                        }
                    },
                    scales: {
                        x: { beginAtZero: true, ticks: { callback: v => Number(v).toLocaleString('pt-BR') } },
                        y: { grid: { display: false } }
                    }
                }
            });
        } catch (err) { console.error(err); }
    },

    // ==================== MAP ====================
    initMap() {
        if (this.map) return;
        const mapEl = document.getElementById('map');
        if (!mapEl) return;

        this.map = L.map('map', {
            center: [-14.5, -51.0],
            zoom: 4,
            minZoom: 3,
            maxZoom: 12,
            scrollWheelZoom: true,
        });

        L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
            subdomains: 'abcd',
            maxZoom: 19,
        }).addTo(this.map);

        this.mapLayer = L.layerGroup().addTo(this.map);
    },

    async loadMap() {
        this.initMap();
        if (!this.map) return;

        try {
            const params = this.getFilterParams();
            const res = await fetch('api/geo.php?' + params.toString());
            const data = await res.json();
            if (!data.success) return;

            this.mapData = data.data;

            const info = document.getElementById('map-info');
            if (info) {
                const fmt = n => Number(n).toLocaleString('pt-BR');
                info.textContent = `${fmt(data.totalMunicipios)} municipios | ${fmt(data.totalRegistros)} registros`;
            }

            this.renderMapMarkers();
            this.renderMapTable(data.data);
        } catch (err) { console.error('Erro mapa:', err); }
    },

    renderMapMarkers() {
        if (!this.mapLayer || !this.mapData) return;
        this.mapLayer.clearLayers();

        const items = this.mapData;
        if (!items.length) return;

        const metric = this.mapMetric;
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
                { closeButton: false }
            );

            circle.bindTooltip(item.municipio, {
                permanent: false, direction: 'top', offset: [0, -8]
            });

            this.mapLayer.addLayer(circle);
        });

        // Legend
        const legend = document.getElementById('map-legend');
        if (legend) {
            const metricLabels = { area: 'Area (ha)', producao: 'Producao (t)', registros: 'Registros' };
            const fmtShort = (n) => {
                if (n >= 1e6) return (n / 1e6).toFixed(1) + 'M';
                if (n >= 1e3) return (n / 1e3).toFixed(1) + 'K';
                return Math.round(n).toString();
            };
            legend.innerHTML = `
                <span class="map-legend-title">${metricLabels[metric]}</span>
                <span class="map-legend-label">${fmtShort(minVal)}</span>
                <div class="map-legend-gradient"></div>
                <span class="map-legend-label">${fmtShort(maxVal)}</span>
            `;
        }
    },

    renderMapTable(items) {
        const tbody = document.getElementById('map-table-body');
        const countEl = document.getElementById('map-table-count');
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

    // ==================== DATA TABLE ====================
    bindTableSort() {
        document.querySelectorAll('#data-table th[data-sort]').forEach(th => {
            th.addEventListener('click', () => {
                const col = th.dataset.sort;
                if (this.tableSort === col) {
                    this.tableSortDir = this.tableSortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    this.tableSort = col;
                    this.tableSortDir = 'asc';
                }
                this.updateSortIndicators();
                this.loadTable();
            });
        });
    },

    bindTableSearch() {
        const input = document.getElementById('table-search');
        let timeout;
        input.addEventListener('input', () => {
            clearTimeout(timeout);
            timeout = setTimeout(() => this.loadTable(), 400);
        });
    },

    updateSortIndicators() {
        document.querySelectorAll('#data-table th[data-sort]').forEach(th => {
            th.classList.remove('sorted');
            const arrow = th.querySelector('.sort-arrow');
            if (arrow) arrow.remove();
        });
        const active = document.querySelector(`#data-table th[data-sort="${this.tableSort}"]`);
        if (active) {
            active.classList.add('sorted');
            const arrow = document.createElement('span');
            arrow.className = 'sort-arrow';
            arrow.textContent = this.tableSortDir === 'asc' ? ' \u2191' : ' \u2193';
            active.appendChild(arrow);
        }
    },

    async loadTable() {
        const tbody = document.getElementById('data-table-body');
        const info = document.getElementById('table-results-info');
        tbody.innerHTML = '<tr><td colspan="12" style="text-align:center;padding:30px;color:#9ca3af;">Carregando...</td></tr>';

        try {
            const params = this.getFilterParams();
            params.set('page', this.tablePage);
            params.set('sort', this.tableSort);
            params.set('dir', this.tableSortDir);

            const searchTerm = document.getElementById('table-search').value.trim();
            if (searchTerm) params.set('busca', searchTerm);

            const res = await fetch('api/search.php?' + params.toString());
            const data = await res.json();

            if (!data.success) {
                tbody.innerHTML = `<tr><td colspan="12" style="text-align:center;padding:20px;color:#dc2626;">${data.error}</td></tr>`;
                return;
            }

            info.textContent = `${data.pagination.totalRows.toLocaleString('pt-BR')} registros | Pagina ${data.pagination.page} de ${data.pagination.totalPages}`;

            if (data.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="12" style="text-align:center;padding:30px;color:#9ca3af;">Nenhum registro encontrado</td></tr>';
            } else {
                tbody.innerHTML = '';
                data.data.forEach(row => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${this.esc(row.safra)}</td>
                        <td>${this.esc(row.especie)}</td>
                        <td>${this.esc(row.cultivar)}</td>
                        <td>${this.esc(row.categoria)}</td>
                        <td>${this.esc(row.municipio)}</td>
                        <td>${this.esc(row.uf)}</td>
                        <td>${this.esc(row.status_registro)}</td>
                        <td class="num">${row.area_fmt || '-'}</td>
                        <td class="num">${row.producao_estimada_fmt || '-'}</td>
                        <td class="num">${row.producao_bruta_fmt || '-'}</td>
                        <td>${row.data_plantio_fmt || '-'}</td>
                        <td>${row.data_colheita_fmt || '-'}</td>`;
                    tbody.appendChild(tr);
                });
            }

            this.renderPagination(data.pagination);
        } catch (err) {
            console.error(err);
            tbody.innerHTML = '<tr><td colspan="12" style="text-align:center;padding:20px;color:#dc2626;">Erro de conexao</td></tr>';
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
            if (!disabled) btn.addEventListener('click', () => {
                this.tablePage = page;
                this.loadTable();
            });
            c.appendChild(btn);
        };

        addBtn('Anterior', pag.page - 1, pag.page <= 1);

        const range = this.getPageRange(pag.page, pag.totalPages);
        range.forEach(p => {
            if (p === '...') {
                const s = document.createElement('span');
                s.className = 'page-dots';
                s.textContent = '...';
                c.appendChild(s);
            } else {
                addBtn(p, p, false, p === pag.page);
            }
        });

        addBtn('Proximo', pag.page + 1, pag.page >= pag.totalPages);
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

    // ==================== EXPORT ====================
    exportCSV() {
        window.location.href = 'api/export.php?' + this.getFilterParams().toString();
    },

    // ==================== UTILS ====================
    esc(val) {
        if (val === null || val === undefined) return '-';
        const d = document.createElement('div');
        d.textContent = val;
        return d.innerHTML;
    }
};

document.addEventListener('DOMContentLoaded', () => App.init());
