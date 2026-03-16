/**
 * DataSemente - JavaScript principal
 */

const App = {
    currentPage: 1,
    currentSort: 'id',
    currentDir: 'asc',
    filters: {},

    init() {
        this.loadFilters();
        this.bindEvents();
        this.search();
    },

    bindEvents() {
        document.getElementById('btn-search').addEventListener('click', () => {
            this.currentPage = 1;
            this.search();
        });

        document.getElementById('btn-clear').addEventListener('click', () => {
            this.clearFilters();
        });

        document.getElementById('btn-export').addEventListener('click', () => {
            this.exportCSV();
        });

        // Busca ao pressionar Enter em qualquer campo
        document.querySelectorAll('.filter-group input, .filter-group select').forEach(el => {
            el.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    this.currentPage = 1;
                    this.search();
                }
            });
        });

        // Ordenação ao clicar no cabeçalho
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
                this.search();
            });
        });
    },

    getFilterValues() {
        return {
            safra: document.getElementById('filter-safra').value,
            cultura: document.getElementById('filter-cultura').value,
            estado: document.getElementById('filter-estado').value,
            cultivar: document.getElementById('filter-cultivar').value,
            municipio: document.getElementById('filter-municipio').value,
            busca: document.getElementById('filter-busca').value,
        };
    },

    buildQueryString(includePageSort = true) {
        const filters = this.getFilterValues();
        const params = new URLSearchParams();

        Object.entries(filters).forEach(([key, val]) => {
            if (val) params.set(key, val);
        });

        if (includePageSort) {
            params.set('page', this.currentPage);
            params.set('sort', this.currentSort);
            params.set('dir', this.currentDir);
        }

        return params.toString();
    },

    async loadFilters() {
        try {
            const res = await fetch('api/filters.php');
            const data = await res.json();

            if (!data.success) return;

            this.populateSelect('filter-safra', data.filters.safras);
            this.populateSelect('filter-cultura', data.filters.culturas);
            this.populateSelect('filter-estado', data.filters.estados);

            // Atualiza estatísticas
            if (data.stats) {
                document.getElementById('stat-registros').textContent =
                    Number(data.stats.total_registros).toLocaleString('pt-BR');
                document.getElementById('stat-culturas').textContent =
                    Number(data.stats.total_culturas).toLocaleString('pt-BR');
                document.getElementById('stat-estados').textContent =
                    Number(data.stats.total_estados).toLocaleString('pt-BR');
                document.getElementById('stat-municipios').textContent =
                    Number(data.stats.total_municipios).toLocaleString('pt-BR');
            }
        } catch (err) {
            console.error('Erro ao carregar filtros:', err);
        }
    },

    populateSelect(id, options) {
        const select = document.getElementById(id);
        const current = select.value;
        // Mantém a primeira opção (placeholder)
        while (select.options.length > 1) select.remove(1);

        options.forEach(opt => {
            const option = document.createElement('option');
            option.value = opt;
            option.textContent = opt;
            select.appendChild(option);
        });

        if (current) select.value = current;
    },

    async search() {
        const tbody = document.getElementById('results-body');
        const loading = document.getElementById('loading');
        const tableWrapper = document.getElementById('table-wrapper');

        tbody.innerHTML = '';
        loading.style.display = 'block';
        tableWrapper.style.display = 'none';

        try {
            const qs = this.buildQueryString();
            const res = await fetch('api/search.php?' + qs);
            const data = await res.json();

            loading.style.display = 'none';

            if (!data.success) {
                tbody.innerHTML = `<tr><td colspan="11" style="text-align:center;padding:20px;color:#c62828;">${data.error}</td></tr>`;
                tableWrapper.style.display = 'block';
                return;
            }

            // Atualiza info de resultados
            document.getElementById('results-count').textContent =
                `${data.pagination.totalRows.toLocaleString('pt-BR')} registro(s) encontrado(s)`;
            document.getElementById('results-page').textContent =
                `Página ${data.pagination.page} de ${data.pagination.totalPages}`;

            if (data.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="11" style="text-align:center;padding:30px;color:#666;">Nenhum registro encontrado com os filtros aplicados.</td></tr>';
            } else {
                data.data.forEach(row => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${this.esc(row.safra)}</td>
                        <td>${this.esc(row.cultura)}</td>
                        <td>${this.esc(row.cultivar)}</td>
                        <td>${this.esc(row.estado)}</td>
                        <td>${this.esc(row.municipio)}</td>
                        <td>${this.esc(row.regiao)}</td>
                        <td class="num">${row.area_plantada_fmt || '-'}</td>
                        <td class="num">${row.produtividade_fmt || '-'}</td>
                        <td class="num">${row.producao_fmt || '-'}</td>
                        <td>${row.data_plantio_fmt || '-'}</td>
                        <td>${row.data_colheita_fmt || '-'}</td>
                    `;
                    tbody.appendChild(tr);
                });
            }

            tableWrapper.style.display = 'block';
            this.renderPagination(data.pagination);

        } catch (err) {
            loading.style.display = 'none';
            tableWrapper.style.display = 'block';
            tbody.innerHTML = `<tr><td colspan="11" style="text-align:center;padding:20px;color:#c62828;">Erro de conexão. Tente novamente.</td></tr>`;
            console.error('Erro na busca:', err);
        }
    },

    renderPagination(pag) {
        const container = document.getElementById('pagination');
        container.innerHTML = '';

        if (pag.totalPages <= 1) return;

        // Botão anterior
        const prevBtn = document.createElement('button');
        prevBtn.textContent = 'Anterior';
        prevBtn.disabled = pag.page <= 1;
        prevBtn.addEventListener('click', () => {
            this.currentPage = pag.page - 1;
            this.search();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
        container.appendChild(prevBtn);

        // Números das páginas
        const range = this.getPageRange(pag.page, pag.totalPages);
        range.forEach(p => {
            if (p === '...') {
                const span = document.createElement('span');
                span.textContent = '...';
                span.style.padding = '8px 4px';
                container.appendChild(span);
            } else {
                const btn = document.createElement('button');
                btn.textContent = p;
                if (p === pag.page) btn.classList.add('active');
                btn.addEventListener('click', () => {
                    this.currentPage = p;
                    this.search();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });
                container.appendChild(btn);
            }
        });

        // Botão próximo
        const nextBtn = document.createElement('button');
        nextBtn.textContent = 'Próximo';
        nextBtn.disabled = pag.page >= pag.totalPages;
        nextBtn.addEventListener('click', () => {
            this.currentPage = pag.page + 1;
            this.search();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
        container.appendChild(nextBtn);
    },

    getPageRange(current, total) {
        if (total <= 7) {
            return Array.from({ length: total }, (_, i) => i + 1);
        }

        const pages = [];
        pages.push(1);

        if (current > 3) pages.push('...');

        for (let i = Math.max(2, current - 1); i <= Math.min(total - 1, current + 1); i++) {
            pages.push(i);
        }

        if (current < total - 2) pages.push('...');

        pages.push(total);
        return pages;
    },

    clearFilters() {
        document.getElementById('filter-safra').value = '';
        document.getElementById('filter-cultura').value = '';
        document.getElementById('filter-estado').value = '';
        document.getElementById('filter-cultivar').value = '';
        document.getElementById('filter-municipio').value = '';
        document.getElementById('filter-busca').value = '';
        this.currentPage = 1;
        this.currentSort = 'id';
        this.currentDir = 'asc';
        this.search();
    },

    exportCSV() {
        const qs = this.buildQueryString(false);
        window.location.href = 'api/export.php?' + qs;
    },

    updateSortIndicators() {
        document.querySelectorAll('th[data-sort]').forEach(th => {
            th.classList.remove('sorted');
            const icon = th.querySelector('.sort-icon');
            if (icon) icon.textContent = '\u2195';
        });

        const activeTh = document.querySelector(`th[data-sort="${this.currentSort}"]`);
        if (activeTh) {
            activeTh.classList.add('sorted');
            const icon = activeTh.querySelector('.sort-icon');
            if (icon) icon.textContent = this.currentDir === 'asc' ? '\u2191' : '\u2193';
        }
    },

    esc(val) {
        if (val === null || val === undefined) return '-';
        const div = document.createElement('div');
        div.textContent = val;
        return div.innerHTML;
    }
};

document.addEventListener('DOMContentLoaded', () => App.init());
