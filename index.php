<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DataSemente - Painel de Dados de Sementes do Brasil</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- Header -->
<header class="header">
    <div class="container">
        <div class="header-content">
            <div>
                <h1>DataSemente</h1>
                <p>Painel gerencial de dados de campo e produção de sementes</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-accent btn-sm" id="btn-pdf" title="Gerar relatório PDF">PDF</button>
                <button class="btn btn-sm" style="background:rgba(255,255,255,0.2);color:white;" id="btn-export-csv" title="Exportar dados CSV">CSV</button>
            </div>
        </div>
    </div>
</header>

<main class="container">

    <!-- Stats -->
    <div class="stats-bar">
        <div class="stat-card">
            <div class="stat-value" id="stat-registros">-</div>
            <div class="stat-label">Registros</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" id="stat-especies">-</div>
            <div class="stat-label">Espécies</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" id="stat-cultivares">-</div>
            <div class="stat-label">Cultivares</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" id="stat-estados">-</div>
            <div class="stat-label">Estados</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" id="stat-municipios">-</div>
            <div class="stat-label">Municípios</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" id="stat-area">-</div>
            <div class="stat-label">Área Total (ha)</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" id="stat-producao">-</div>
            <div class="stat-label">Produção (t)</div>
        </div>
    </div>

    <!-- Filters -->
    <section class="card">
        <div class="card-title">Filtros</div>
        <div class="filters-grid">
            <div class="filter-group">
                <label for="filter-busca">Busca geral</label>
                <input type="text" id="filter-busca" placeholder="Espécie, cultivar, município...">
            </div>
            <div class="filter-group">
                <label for="filter-safra">Safra(s)</label>
                <select id="filter-safra" multiple>
                </select>
            </div>
            <div class="filter-group">
                <label for="filter-especie">Espécie</label>
                <select id="filter-especie">
                    <option value="">Todas</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="filter-categoria">Categoria</label>
                <select id="filter-categoria">
                    <option value="">Todas</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="filter-uf">Estado(s)</label>
                <select id="filter-uf" multiple>
                </select>
            </div>
            <div class="filter-group">
                <label for="filter-cultivar">Cultivar</label>
                <input type="text" id="filter-cultivar" placeholder="Nome da cultivar...">
            </div>
            <div class="filter-group">
                <label for="filter-municipio">Município</label>
                <input type="text" id="filter-municipio" placeholder="Nome do município...">
            </div>
            <div class="filter-group">
                <label for="filter-status">Status</label>
                <select id="filter-status">
                    <option value="">Todos</option>
                </select>
            </div>
        </div>
        <div class="filters-actions">
            <button class="btn btn-primary" id="btn-apply">Aplicar Filtros</button>
            <button class="btn btn-secondary" id="btn-clear">Limpar</button>
        </div>
    </section>

    <!-- Tabs -->
    <nav class="nav-tabs">
        <button class="nav-tab active" data-tab="dashboard">Dashboard</button>
        <button class="nav-tab" data-tab="mapa">Mapa de Calor</button>
        <button class="nav-tab" data-tab="comparativo">Comparativo</button>
        <button class="nav-tab" data-tab="dados">Dados</button>
    </nav>

    <!-- ===== TAB: DASHBOARD ===== -->
    <div class="tab-panel active" id="panel-dashboard">
        <div class="dashboard-grid">
            <div class="card">
                <div class="card-title">Evolução por Safra</div>
                <div class="chart-container">
                    <canvas id="chart-evolucao"></canvas>
                </div>
            </div>
            <div class="card">
                <div class="card-title">Top 10 Estados - Produção</div>
                <div class="chart-container">
                    <canvas id="chart-ranking-uf"></canvas>
                </div>
            </div>
            <div class="card">
                <div class="card-title">Top 10 Cultivares - Produção</div>
                <div class="chart-container">
                    <canvas id="chart-ranking-cultivar"></canvas>
                </div>
            </div>
            <div class="card">
                <div class="card-title">Top 10 Municípios - Área Plantada</div>
                <div class="chart-container">
                    <canvas id="chart-ranking-municipio"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== TAB: MAPA ===== -->
    <div class="tab-panel" id="panel-mapa">
        <div class="card">
            <div class="card-title">Mapa de Calor - Brasil</div>
            <div class="map-controls">
                <button class="btn btn-sm active" data-metric="total_producao">Produção</button>
                <button class="btn btn-sm" data-metric="total_area">Área</button>
                <button class="btn btn-sm" data-metric="registros">Registros</button>
                <button class="btn btn-sm" data-metric="total_cultivares">Cultivares</button>
            </div>
            <div id="brazil-map-container"></div>
        </div>
        <div class="card">
            <div class="card-title">Detalhamento por Estado</div>
            <div class="table-wrapper">
                <table id="table-uf-detail">
                    <thead>
                        <tr>
                            <th>UF</th>
                            <th>Registros</th>
                            <th>Área (ha)</th>
                            <th>Prod. Estimada (t)</th>
                            <th>Prod. Bruta (t)</th>
                            <th>Cultivares</th>
                            <th>Municípios</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-uf-detail"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ===== TAB: COMPARATIVO ===== -->
    <div class="tab-panel" id="panel-comparativo">
        <div class="card">
            <div class="card-title">Comparar</div>
            <div class="filters-grid" style="grid-template-columns: 1fr 1fr 1fr;">
                <div class="filter-group">
                    <label for="compare-type">Comparar por</label>
                    <select id="compare-type">
                        <option value="safra">Safra x Safra</option>
                        <option value="cultivar">Cultivar x Cultivar</option>
                        <option value="uf_evolucao">Evolução por Estado</option>
                        <option value="cultivar_evolucao">Evolução por Cultivar</option>
                    </select>
                </div>
                <div class="filter-group" id="compare-select-group">
                    <label for="compare-items">Selecione (Ctrl+click para múltiplos)</label>
                    <select id="compare-items" multiple style="height:120px;"></select>
                </div>
                <div class="filter-group" style="display:flex;align-items:flex-end;">
                    <button class="btn btn-primary" id="btn-compare">Comparar</button>
                </div>
            </div>
        </div>

        <div id="comparison-results" style="display:none;">
            <div class="comparison-grid" id="compare-cards"></div>
            <div class="card" style="margin-top:20px;">
                <div class="card-title">Gráfico Comparativo</div>
                <div class="chart-container">
                    <canvas id="chart-comparativo"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== TAB: DADOS ===== -->
    <div class="tab-panel" id="panel-dados">
        <div class="results-info">
            <span class="results-count" id="results-count">Carregando...</span>
            <span id="results-page"></span>
        </div>

        <div id="loading" class="loading">
            <div class="spinner"></div>
            <p>Carregando dados...</p>
        </div>

        <div class="card" id="table-card" style="display:none;">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th data-sort="safra">Safra <span class="sort-icon">&#8597;</span></th>
                            <th data-sort="especie">Espécie <span class="sort-icon">&#8597;</span></th>
                            <th data-sort="categoria">Cat. <span class="sort-icon">&#8597;</span></th>
                            <th data-sort="cultivar">Cultivar <span class="sort-icon">&#8597;</span></th>
                            <th data-sort="municipio">Município <span class="sort-icon">&#8597;</span></th>
                            <th data-sort="uf">UF <span class="sort-icon">&#8597;</span></th>
                            <th data-sort="status_registro">Status <span class="sort-icon">&#8597;</span></th>
                            <th data-sort="area">Área (ha) <span class="sort-icon">&#8597;</span></th>
                            <th data-sort="producao_bruta">Prod. Bruta <span class="sort-icon">&#8597;</span></th>
                            <th data-sort="producao_estimada">Prod. Est. <span class="sort-icon">&#8597;</span></th>
                            <th data-sort="data_plantio">Plantio <span class="sort-icon">&#8597;</span></th>
                            <th data-sort="data_colheita">Colheita <span class="sort-icon">&#8597;</span></th>
                        </tr>
                    </thead>
                    <tbody id="results-body"></tbody>
                </table>
            </div>
        </div>

        <div class="pagination" id="pagination"></div>
    </div>

</main>

<footer class="footer">
    DataSemente &copy; <?= date('Y') ?> - Painel gerencial de dados de campo e produção de sementes do Brasil
</footer>

<!-- PDF hidden container -->
<div id="pdf-content" style="display:none;"></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="assets/js/brazil-map.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
