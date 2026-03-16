/**
 * DataSemente - App principal
 * Dashboard gerencial com gráficos, mapa de calor, comparações e PDF
 */

const App = {
    currentPage: 1,
    currentSort: 'id',
    currentDir: 'asc',
    charts: {},
    heatmapData: null,
    filterOptions: null,

    init() {
        this.bindTabs();
        this.bindFilters();
        this.bindSort();
        this.bindMap();
        this.bindComparison();
        this.bindPDF();
        this.loadFilters().then(() => {
            this.applyFilters();
        });
    },

    // ===== TABS =====
    bindTabs() {
        document.querySelectorAll('.nav-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
                tab.classList.add('active');
                document.getElementById('panel-' + tab.dataset.tab).classList.add('active');

                if (tab.dataset.tab === 'dados') this.searchTable();
                if (tab.dataset.tab === 'mapa') this.loadHeatmap();
            });
        });
    },

    // ===== FILTERS =====
    bindFilters() {
        document.getElementById('btn-apply').addEventListener('click', () => this.applyFilters());
        document.getElementById('btn-clear').addEventListener('click', () => this.clearFilters());
        document.getElementById('btn-export-csv').addEventListener('click', () => this.exportCSV());

        // Enter nas inputs
        document.querySelectorAll('.filter-group input').forEach(el => {
            el.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') this.applyFilters();
            });
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

    populateSelect(id, options, isMultiple = false) {
        const select = document.getElementById(id);
        if (isMultiple) {
            select.innerHTML = '';
        } else {
            const first = select.options[0];
            select.innerHTML = '';
            if (first) select.appendChild(first);
        }
        options.forEach(opt => {
            const option = document.createElement('option');
            option.value = opt;
            option.textContent = opt;
            select.appendChild(option);
        });
    },

    updateStats(stats) {
        const fmt = (n) => Number(n).toLocaleString('pt-BR');
        const fmtDec = (n) => Number(n).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('stat-registros').textContent = fmt(stats.total_registros);
        document.getElementById('stat-especies').textContent = fmt(stats.total_especies);
        document.getElementById('stat-cultivares').textContent = fmt(stats.total_cultivares);
        document.getElementById('stat-estados').textContent = fmt(stats.total_estados);
        document.getElementById('stat-municipios').textContent = fmt(stats.total_municipios);
        document.getElementById('stat-area').textContent = fmtDec(stats.total_area);
        document.getElementById('stat-producao').textContent = fmtDec(stats.total_producao);
    },

    getFilterParams() {
        const params = new URLSearchParams();

        const safras = Array.from(document.getElementById('filter-safra').selectedOptions).map(o => o.value);
        safras.forEach(s => params.append('safra[]', s));

        const especie = document.getElementById('filter-especie').value;
        if (especie) params.set('especie', especie);

        const categoria = document.getElementById('filter-categoria').value;
        if (categoria) params.set('categoria', categoria);

        const ufs = Array.from(document.getElementById('filter-uf').selectedOptions).map(o => o.value);
        ufs.forEach(u => params.append('uf[]', u));

        const cultivar = document.getElementById('filter-cultivar').value;
        if (cultivar) params.set('cultivar', cultivar);

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

        const activeTab = document.querySelector('.nav-tab.active');
        if (activeTab && activeTab.dataset.tab === 'dados') {
            this.searchTable();
        }
    },

    clearFilters() {
        document.getElementById('filter-busca').value = '';
        document.getElementById('filter-especie').value = '';
        document.getElementById('filter-categoria').value = '';
        document.getElementById('filter-cultivar').value = '';
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
        } catch (err) {
            console.error('Erro no gráfico de evolução:', err);
        }
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
            const metricLabel = metric === 'area' ? 'Área (ha)' : 'Produção (t)';

            this.renderBarChart(canvasId, labels, values, metricLabel);
        } catch (err) {
            console.error('Erro no ranking:', err);
        }
    },

    // ===== CHARTS =====
    renderLineChart(canvasId, labels, seriesData, label) {
        if (this.charts[canvasId]) this.charts[canvasId].destroy();

        const colors = ['#2e7d32', '#1565c0', '#c62828', '#f57f17', '#6a1b9a', '#00838f'];
        const datasets = [];
        let i = 0;

        Object.entries(seriesData).forEach(([name, values]) => {
            const color = colors[i % colors.length];
            datasets.push({
                label: name,
                data: labels.map(l => values[l] || 0),
                borderColor: color,
                backgroundColor: color + '20',
                fill: Object.keys(seriesData).length === 1,
                tension: 0.3,
                pointRadius: 4,
                pointHoverRadius: 6,
            });
            i++;
        });

        this.charts[canvasId] = new Chart(document.getElementById(canvasId), {
            type: 'line',
            data: { labels, datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: datasets.length > 1, position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => `${ctx.dataset.label}: ${Number(ctx.parsed.y).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (v) => Number(v).toLocaleString('pt-BR')
                        }
                    }
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
                    backgroundColor: '#2e7d32cc',
                    borderColor: '#1b5e20',
                    borderWidth: 1,
                    borderRadius: 4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => `${label}: ${Number(ctx.parsed.x).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { callback: (v) => Number(v).toLocaleString('pt-BR') }
                    }
                }
            }
        });
    },

    // ===== HEATMAP =====
    bindMap() {
        document.querySelectorAll('.map-controls .btn-sm').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.map-controls .btn-sm').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                if (this.heatmapData) {
                    BrazilMap.render('brazil-map-container', this.heatmapData, btn.dataset.metric);
                }
            });
        });

        BrazilMap.onStateClick = (uf) => {
            // Seleciona o estado no filtro
            const select = document.getElementById('filter-uf');
            Array.from(select.options).forEach(o => { o.selected = o.value === uf; });
            this.applyFilters();
        };
    },

    async loadHeatmap() {
        try {
            const params = this.getFilterParams();
            params.set('type', 'heatmap');
            const res = await fetch('api/stats.php?' + params.toString());
            const data = await res.json();
            if (!data.success) return;

            this.heatmapData = data.data;

            const activeMetric = document.querySelector('.map-controls .btn-sm.active');
            const metric = activeMetric ? activeMetric.dataset.metric : 'total_producao';
            BrazilMap.render('brazil-map-container', data.data, metric);

            // Preenche tabela de detalhamento
            const tbody = document.getElementById('tbody-uf-detail');
            tbody.innerHTML = '';
            const fmt = (n) => Number(n).toLocaleString('pt-BR', { minimumFractionDigits: 2 });

            data.data.forEach(row => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong>${this.esc(row.uf)}</strong></td>
                    <td class="num">${Number(row.registros).toLocaleString('pt-BR')}</td>
                    <td class="num">${fmt(row.total_area)}</td>
                    <td class="num">${fmt(row.total_producao)}</td>
                    <td class="num">${fmt(row.total_producao_bruta)}</td>
                    <td class="num">${Number(row.total_cultivares).toLocaleString('pt-BR')}</td>
                    <td class="num">${Number(row.total_municipios).toLocaleString('pt-BR')}</td>
                `;
                tbody.appendChild(tr);
            });
        } catch (err) {
            console.error('Erro no heatmap:', err);
        }
    },

    // ===== COMPARISON =====
    bindComparison() {
        const typeSelect = document.getElementById('compare-type');
        typeSelect.addEventListener('change', () => this.updateCompareOptions());
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
        else if (type === 'cultivar' || type === 'cultivar_evolucao') {
            this.loadCultivares();
            return;
        }
        else if (type === 'uf_evolucao') options = this.filterOptions.estados;

        options.forEach(opt => {
            const o = document.createElement('option');
            o.value = opt;
            o.textContent = opt;
            select.appendChild(o);
        });
    },

    async loadCultivares() {
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
                o.value = cv;
                o.textContent = cv;
                select.appendChild(o);
            });
        } catch (err) {
            console.error('Erro ao carregar cultivares:', err);
        }
    },

    async runComparison() {
        const type = document.getElementById('compare-type').value;
        const selected = Array.from(document.getElementById('compare-items').selectedOptions).map(o => o.value);

        if (selected.length < 2) {
            alert('Selecione pelo menos 2 itens para comparar (Ctrl+click).');
            return;
        }

        const container = document.getElementById('comparison-results');

        if (type === 'safra' || type === 'cultivar') {
            await this.runCardComparison(type, selected);
        } else if (type === 'uf_evolucao') {
            await this.runEvolutionComparison('uf', selected);
        } else if (type === 'cultivar_evolucao') {
            await this.runEvolutionComparison('cultivar', selected);
        }

        container.style.display = 'block';
    },

    async runCardComparison(type, items) {
        try {
            const params = new URLSearchParams();
            params.set('type', 'comparativo');
            params.set('compare', type);
            items.forEach(item => params.append(type + '[]', item));

            // Add current filters
            const filterParams = this.getFilterParams();
            for (const [key, value] of filterParams.entries()) {
                if (!key.startsWith(type)) params.append(key, value);
            }

            const res = await fetch('api/stats.php?' + params.toString());
            const data = await res.json();
            if (!data.success) { alert(data.error); return; }

            const cardsContainer = document.getElementById('compare-cards');
            cardsContainer.innerHTML = '';

            const fmt = (n) => Number(n).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
            const fmtInt = (n) => Number(n).toLocaleString('pt-BR');

            const colors = ['#2e7d32', '#1565c0', '#c62828', '#f57f17', '#6a1b9a'];

            data.data.forEach((item, idx) => {
                const card = document.createElement('div');
                card.className = 'compare-card';
                card.style.borderLeftColor = colors[idx % colors.length];
                card.innerHTML = `
                    <h3>${this.esc(item.label)}</h3>
                    <div class="metric-row"><span>Registros</span><span class="metric-value">${fmtInt(item.registros)}</span></div>
                    <div class="metric-row"><span>Área Total (ha)</span><span class="metric-value">${fmt(item.total_area)}</span></div>
                    <div class="metric-row"><span>Prod. Estimada (t)</span><span class="metric-value">${fmt(item.total_producao)}</span></div>
                    <div class="metric-row"><span>Prod. Bruta (t)</span><span class="metric-value">${fmt(item.total_producao_bruta)}</span></div>
                    <div class="metric-row"><span>Cultivares</span><span class="metric-value">${fmtInt(item.total_cultivares)}</span></div>
                    <div class="metric-row"><span>Municípios</span><span class="metric-value">${fmtInt(item.total_municipios)}</span></div>
                    <div class="metric-row"><span>Estados</span><span class="metric-value">${fmtInt(item.total_estados)}</span></div>
                `;
                cardsContainer.appendChild(card);
            });

            // Gráfico comparativo
            const labels = data.data.map(d => d.label);
            const datasets = [
                { label: 'Área (ha)', data: data.data.map(d => parseFloat(d.total_area)), backgroundColor: '#2e7d32cc' },
                { label: 'Prod. Estimada (t)', data: data.data.map(d => parseFloat(d.total_producao)), backgroundColor: '#1565c0cc' },
                { label: 'Prod. Bruta (t)', data: data.data.map(d => parseFloat(d.total_producao_bruta)), backgroundColor: '#c62828cc' },
            ];

            if (this.charts['chart-comparativo']) this.charts['chart-comparativo'].destroy();
            this.charts['chart-comparativo'] = new Chart(document.getElementById('chart-comparativo'), {
                type: 'bar',
                data: { labels, datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: {
                            callbacks: { label: (ctx) => `${ctx.dataset.label}: ${Number(ctx.parsed.y).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}` }
                        }
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { callback: (v) => Number(v).toLocaleString('pt-BR') } }
                    }
                }
            });

        } catch (err) {
            console.error('Erro na comparação:', err);
        }
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

            document.getElementById('compare-cards').innerHTML =
                '<p style="color:#666;padding:10px;">Veja o gráfico abaixo para a evolução comparativa.</p>';

            this.renderLineChart('chart-comparativo', data.safras, data.series, 'Produção Estimada (t)');
        } catch (err) {
            console.error('Erro na evolução comparativa:', err);
        }
    },

    // ===== TABLE =====
    bindSort() {
        document.querySelectorAll('th[data-sort]').forEach(th => {
            th.addEventListener('click', () => {
                const col = th.dataset.sort;
                if (this.currentSort === col) {
                    this.currentDir = this.currentDir === 'asc' ? 'desc' : 'asc';
                } else {
                    this.currentSort = col;
                    this.currentDir = 'asc';
                }
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
                tbody.innerHTML = `<tr><td colspan="12" style="text-align:center;padding:20px;color:#c62828;">${data.error}</td></tr>`;
                tableCard.style.display = 'block';
                return;
            }

            document.getElementById('results-count').textContent =
                `${data.pagination.totalRows.toLocaleString('pt-BR')} registro(s)`;
            document.getElementById('results-page').textContent =
                `Página ${data.pagination.page} de ${data.pagination.totalPages}`;

            if (data.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="12" style="text-align:center;padding:30px;color:#666;">Nenhum registro encontrado.</td></tr>';
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
                        <td>${row.data_colheita_fmt || '-'}</td>
                    `;
                    tbody.appendChild(tr);
                });
            }

            tableCard.style.display = 'block';
            this.renderPagination(data.pagination);

        } catch (err) {
            loading.style.display = 'none';
            tableCard.style.display = 'block';
            tbody.innerHTML = '<tr><td colspan="12" style="text-align:center;padding:20px;color:#c62828;">Erro de conexão.</td></tr>';
        }
    },

    renderPagination(pag) {
        const container = document.getElementById('pagination');
        container.innerHTML = '';
        if (pag.totalPages <= 1) return;

        const addBtn = (text, page, disabled = false, active = false) => {
            const btn = document.createElement('button');
            btn.textContent = text;
            btn.disabled = disabled;
            if (active) btn.classList.add('active');
            if (!disabled) {
                btn.addEventListener('click', () => {
                    this.currentPage = page;
                    this.searchTable();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });
            }
            container.appendChild(btn);
        };

        addBtn('Anterior', pag.page - 1, pag.page <= 1);

        const range = this.getPageRange(pag.page, pag.totalPages);
        range.forEach(p => {
            if (p === '...') {
                const span = document.createElement('span');
                span.textContent = '...';
                span.style.padding = '8px 4px';
                container.appendChild(span);
            } else {
                addBtn(p, p, false, p === pag.page);
            }
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
        const params = this.getFilterParams();
        window.location.href = 'api/export.php?' + params.toString();
    },

    // ===== PDF =====
    bindPDF() {
        document.getElementById('btn-pdf').addEventListener('click', () => this.generatePDF());
    },

    async generatePDF() {
        const btn = document.getElementById('btn-pdf');
        btn.disabled = true;
        btn.textContent = 'Gerando...';

        try {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('p', 'mm', 'a4');
            const pageW = doc.internal.pageSize.getWidth();
            const margin = 15;
            let y = 20;

            // === CABEÇALHO ===
            doc.setFillColor(30, 94, 32);
            doc.rect(0, 0, pageW, 35, 'F');
            doc.setTextColor(255, 255, 255);
            doc.setFontSize(20);
            doc.setFont(undefined, 'bold');
            doc.text('DataSemente - Relatório Gerencial', margin, 18);
            doc.setFontSize(10);
            doc.setFont(undefined, 'normal');
            doc.text('Dados de campo e produção de sementes do Brasil', margin, 27);
            doc.text('Gerado em: ' + new Date().toLocaleDateString('pt-BR') + ' ' + new Date().toLocaleTimeString('pt-BR'), pageW - margin, 27, { align: 'right' });

            y = 45;
            doc.setTextColor(0, 0, 0);

            // === FILTROS APLICADOS ===
            const filtros = this.getActiveFiltersText();
            if (filtros) {
                doc.setFontSize(9);
                doc.setTextColor(100, 100, 100);
                doc.text('Filtros: ' + filtros, margin, y);
                y += 8;
            }

            // === RESUMO GERAL ===
            doc.setFontSize(14);
            doc.setTextColor(30, 94, 32);
            doc.setFont(undefined, 'bold');
            doc.text('Resumo Geral', margin, y);
            y += 2;
            doc.setDrawColor(30, 94, 32);
            doc.line(margin, y, pageW - margin, y);
            y += 8;

            doc.setFontSize(10);
            doc.setTextColor(0, 0, 0);
            doc.setFont(undefined, 'normal');

            const stats = [
                ['Registros', document.getElementById('stat-registros').textContent],
                ['Espécies', document.getElementById('stat-especies').textContent],
                ['Cultivares', document.getElementById('stat-cultivares').textContent],
                ['Estados', document.getElementById('stat-estados').textContent],
                ['Municípios', document.getElementById('stat-municipios').textContent],
                ['Área Total (ha)', document.getElementById('stat-area').textContent],
                ['Produção Total (t)', document.getElementById('stat-producao').textContent],
            ];

            const colW = (pageW - 2 * margin) / 4;
            stats.forEach((s, i) => {
                const col = i % 4;
                const row = Math.floor(i / 4);
                const x = margin + col * colW;
                const sy = y + row * 16;
                doc.setFontSize(8);
                doc.setTextColor(100, 100, 100);
                doc.text(s[0], x, sy);
                doc.setFontSize(13);
                doc.setTextColor(30, 94, 32);
                doc.setFont(undefined, 'bold');
                doc.text(s[1], x, sy + 6);
                doc.setFont(undefined, 'normal');
            });

            y += Math.ceil(stats.length / 4) * 16 + 10;

            // === RANKING POR ESTADO ===
            const params = this.getFilterParams();
            params.set('type', 'ranking');
            params.set('by', 'uf');
            params.set('metric', 'producao_estimada');
            params.set('limit', '15');

            const rankRes = await fetch('api/stats.php?' + params.toString());
            const rankData = await rankRes.json();

            if (rankData.success && rankData.data.length > 0) {
                doc.setFontSize(14);
                doc.setTextColor(30, 94, 32);
                doc.setFont(undefined, 'bold');
                doc.text('Ranking por Estado - Produção Estimada', margin, y);
                y += 2;
                doc.line(margin, y, pageW - margin, y);
                y += 4;

                doc.autoTable({
                    startY: y,
                    margin: { left: margin, right: margin },
                    head: [['#', 'UF', 'Produção Estimada (t)', 'Área (ha)', 'Registros']],
                    body: rankData.data.map((d, i) => [
                        i + 1,
                        d.label,
                        Number(d.valor).toLocaleString('pt-BR', { minimumFractionDigits: 2 }),
                        Number(d.total_area).toLocaleString('pt-BR', { minimumFractionDigits: 2 }),
                        Number(d.registros).toLocaleString('pt-BR'),
                    ]),
                    headStyles: { fillColor: [30, 94, 32], fontSize: 8 },
                    bodyStyles: { fontSize: 8 },
                    alternateRowStyles: { fillColor: [240, 247, 240] },
                });

                y = doc.lastAutoTable.finalY + 15;
            }

            // === RANKING POR CULTIVAR ===
            if (y > 240) { doc.addPage(); y = 20; }

            params.set('by', 'cultivar');
            const cvRes = await fetch('api/stats.php?' + params.toString());
            const cvData = await cvRes.json();

            if (cvData.success && cvData.data.length > 0) {
                doc.setFontSize(14);
                doc.setTextColor(30, 94, 32);
                doc.setFont(undefined, 'bold');
                doc.text('Top 15 Cultivares - Produção Estimada', margin, y);
                y += 2;
                doc.line(margin, y, pageW - margin, y);
                y += 4;

                doc.autoTable({
                    startY: y,
                    margin: { left: margin, right: margin },
                    head: [['#', 'Cultivar', 'Produção Estimada (t)', 'Área (ha)', 'Registros']],
                    body: cvData.data.map((d, i) => [
                        i + 1,
                        d.label,
                        Number(d.valor).toLocaleString('pt-BR', { minimumFractionDigits: 2 }),
                        Number(d.total_area).toLocaleString('pt-BR', { minimumFractionDigits: 2 }),
                        Number(d.registros).toLocaleString('pt-BR'),
                    ]),
                    headStyles: { fillColor: [21, 101, 192], fontSize: 8 },
                    bodyStyles: { fontSize: 8 },
                    alternateRowStyles: { fillColor: [232, 240, 254] },
                });

                y = doc.lastAutoTable.finalY + 15;
            }

            // === RANKING POR MUNICÍPIO ===
            if (y > 200) { doc.addPage(); y = 20; }

            params.set('by', 'municipio');
            params.set('metric', 'area');
            const munRes = await fetch('api/stats.php?' + params.toString());
            const munData = await munRes.json();

            if (munData.success && munData.data.length > 0) {
                doc.setFontSize(14);
                doc.setTextColor(30, 94, 32);
                doc.setFont(undefined, 'bold');
                doc.text('Top 15 Municípios - Área Plantada', margin, y);
                y += 2;
                doc.line(margin, y, pageW - margin, y);
                y += 4;

                doc.autoTable({
                    startY: y,
                    margin: { left: margin, right: margin },
                    head: [['#', 'Município', 'Área (ha)', 'Registros']],
                    body: munData.data.map((d, i) => [
                        i + 1,
                        d.label,
                        Number(d.valor).toLocaleString('pt-BR', { minimumFractionDigits: 2 }),
                        Number(d.registros).toLocaleString('pt-BR'),
                    ]),
                    headStyles: { fillColor: [198, 40, 40], fontSize: 8 },
                    bodyStyles: { fontSize: 8 },
                    alternateRowStyles: { fillColor: [254, 235, 235] },
                });
            }

            // === RODAPÉ EM TODAS AS PÁGINAS ===
            const totalPages = doc.internal.getNumberOfPages();
            for (let i = 1; i <= totalPages; i++) {
                doc.setPage(i);
                doc.setFontSize(8);
                doc.setTextColor(150, 150, 150);
                doc.text(
                    `DataSemente - Página ${i} de ${totalPages}`,
                    pageW / 2, doc.internal.pageSize.getHeight() - 10,
                    { align: 'center' }
                );
            }

            doc.save('datasemente_relatorio_' + new Date().toISOString().slice(0, 10) + '.pdf');

        } catch (err) {
            console.error('Erro ao gerar PDF:', err);
            alert('Erro ao gerar o PDF. Tente novamente.');
        }

        btn.disabled = false;
        btn.textContent = 'PDF';
    },

    getActiveFiltersText() {
        const parts = [];
        const safras = Array.from(document.getElementById('filter-safra').selectedOptions).map(o => o.value);
        if (safras.length) parts.push('Safra: ' + safras.join(', '));

        const especie = document.getElementById('filter-especie').value;
        if (especie) parts.push('Espécie: ' + especie);

        const ufs = Array.from(document.getElementById('filter-uf').selectedOptions).map(o => o.value);
        if (ufs.length) parts.push('UF: ' + ufs.join(', '));

        const cultivar = document.getElementById('filter-cultivar').value;
        if (cultivar) parts.push('Cultivar: ' + cultivar);

        const municipio = document.getElementById('filter-municipio').value;
        if (municipio) parts.push('Município: ' + municipio);

        return parts.join(' | ');
    },

    // ===== UTILS =====
    esc(val) {
        if (val === null || val === undefined) return '-';
        const div = document.createElement('div');
        div.textContent = val;
        return div.innerHTML;
    }
};

document.addEventListener('DOMContentLoaded', () => App.init());
