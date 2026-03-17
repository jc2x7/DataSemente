<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DataSemente - Painel Gerencial</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<button class="hamburger" id="hamburger-btn">&#9776;</button>

<div class="app-layout">

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <h1>DataSemente</h1>
            <span>Painel Gerencial</span>
        </div>
        <nav class="sidebar-nav">
            <button class="nav-item active" data-panel="dashboard">
                <span class="nav-icon">&#9635;</span> Dashboard
            </button>
            <button class="nav-item" data-panel="mapa">
                <span class="nav-icon">&#9737;</span> Mapa de Calor
            </button>
            <button class="nav-item" data-panel="comparativo">
                <span class="nav-icon">&#8700;</span> Comparativo
            </button>
            <button class="nav-item" data-panel="dados">
                <span class="nav-icon">&#9783;</span> Dados
            </button>
        </nav>
        <div class="sidebar-footer">
            <button class="btn btn-accent btn-sm" id="btn-pdf" style="flex:1;">PDF</button>
            <button class="btn btn-ghost btn-sm" id="btn-export-csv" style="flex:1;">CSV</button>
        </div>
    </aside>

    <!-- MAIN -->
    <div class="main-content">

        <!-- TOP BAR -->
        <div class="topbar">
            <div class="topbar-title">
                <h2 id="panel-title">Dashboard</h2>
                <p>Dados de campo e produção de sementes do Brasil</p>
            </div>
        </div>

        <!-- KPIs -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-value" id="stat-registros">-</div>
                <div class="kpi-label">Registros</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value" id="stat-especies">-</div>
                <div class="kpi-label">Espécies</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value" id="stat-cultivares">-</div>
                <div class="kpi-label">Cultivares</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value" id="stat-estados">-</div>
                <div class="kpi-label">Estados</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value" id="stat-municipios">-</div>
                <div class="kpi-label">Municípios</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value" id="stat-area">-</div>
                <div class="kpi-label">Área Total (ha)</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value" id="stat-producao">-</div>
                <div class="kpi-label">Produção (t)</div>
            </div>
        </div>

        <!-- FILTERS -->
        <div class="filter-bar" id="filter-bar">
            <div class="filter-bar-header">
                <h3>Filtros</h3>
                <button class="filter-toggle" id="filter-toggle">Ocultar</button>
            </div>
            <div id="filter-body">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="filter-busca">Busca geral</label>
                        <input type="text" id="filter-busca" placeholder="Buscar...">
                    </div>
                    <div class="filter-group">
                        <label for="filter-safra">Safra(s)</label>
                        <select id="filter-safra" multiple></select>
                    </div>
                    <div class="filter-group">
                        <label for="filter-especie">Espécie</label>
                        <select id="filter-especie">
                            <option value="">Todas</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="filter-cultivar">Cultivar</label>
                        <select id="filter-cultivar">
                            <option value="">Todas (selecione espécie)</option>
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
                        <select id="filter-uf" multiple></select>
                    </div>
                    <div class="filter-group">
                        <label for="filter-municipio">Município</label>
                        <input type="text" id="filter-municipio" placeholder="Nome...">
                    </div>
                    <div class="filter-group">
                        <label for="filter-status">Status</label>
                        <select id="filter-status">
                            <option value="">Todos</option>
                        </select>
                    </div>
                </div>
                <div class="filter-actions">
                    <button class="btn btn-accent" id="btn-apply">Aplicar</button>
                    <button class="btn btn-ghost" id="btn-clear">Limpar</button>
                </div>
            </div>
        </div>

        <!-- ===== PANEL: DASHBOARD ===== -->
        <div class="panel active" id="panel-dashboard">
            <div class="dash-grid">
                <div class="widget span-2 geo-map-widget">
                    <div class="widget-header">
                        <div>
                            <div class="widget-title">Geolocalização de Plantio</div>
                            <div class="widget-subtitle" id="geo-map-subtitle">Carregando...</div>
                        </div>
                        <div class="metric-pills">
                            <button class="metric-pill active" data-geo-metric="area">Área (ha)</button>
                            <button class="metric-pill" data-geo-metric="producao">Produção (t)</button>
                            <button class="metric-pill" data-geo-metric="registros">Registros</button>
                        </div>
                    </div>
                    <div id="geo-map" style="height:520px;border-radius:8px;z-index:1;"></div>
                    <div class="geo-legend" id="geo-legend"></div>
                </div>
                <div class="widget span-2">
                    <div class="widget-header">
                        <div>
                            <div class="widget-title">Evolução por Safra</div>
                            <div class="widget-subtitle">Produção estimada ao longo das safras</div>
                        </div>
                    </div>
                    <div class="chart-container-lg">
                        <canvas id="chart-evolucao"></canvas>
                    </div>
                </div>
                <div class="widget">
                    <div class="widget-header">
                        <div>
                            <div class="widget-title">Top 10 Estados</div>
                            <div class="widget-subtitle">Produção estimada (t)</div>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="chart-ranking-uf"></canvas>
                    </div>
                </div>
                <div class="widget">
                    <div class="widget-header">
                        <div>
                            <div class="widget-title">Top 10 Cultivares</div>
                            <div class="widget-subtitle">Produção estimada (t)</div>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="chart-ranking-cultivar"></canvas>
                    </div>
                </div>
                <div class="widget span-2">
                    <div class="widget-header">
                        <div>
                            <div class="widget-title">Top 10 Municípios</div>
                            <div class="widget-subtitle">Área plantada (ha)</div>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="chart-ranking-municipio"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== PANEL: MAPA ===== -->
        <div class="panel" id="panel-mapa">
            <div class="dash-grid">
                <div class="widget span-2">
                    <div class="widget-header">
                        <div>
                            <div class="widget-title">Mapa de Calor do Brasil</div>
                            <div class="widget-subtitle">Clique em um estado para filtrar</div>
                        </div>
                        <div class="metric-pills">
                            <button class="metric-pill active" data-metric="total_producao">Produção</button>
                            <button class="metric-pill" data-metric="total_area">Área</button>
                            <button class="metric-pill" data-metric="registros">Registros</button>
                            <button class="metric-pill" data-metric="total_cultivares">Cultivares</button>
                        </div>
                    </div>
                    <div id="brazil-map-container"></div>
                </div>
                <div class="widget span-2">
                    <div class="widget-header">
                        <div>
                            <div class="widget-title">Detalhamento por Estado</div>
                        </div>
                    </div>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>UF</th><th>Registros</th><th>Área (ha)</th>
                                    <th>Prod. Estimada (t)</th><th>Prod. Bruta (t)</th>
                                    <th>Cultivares</th><th>Municípios</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-uf-detail"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== PANEL: COMPARATIVO ===== -->
        <div class="panel" id="panel-comparativo">
            <div class="widget">
                <div class="widget-header">
                    <div class="widget-title">Selecione o que comparar</div>
                </div>
                <div class="filters-grid" style="grid-template-columns: 1fr 1fr auto;">
                    <div class="filter-group">
                        <label for="compare-type">Tipo</label>
                        <select id="compare-type">
                            <option value="safra">Safra x Safra</option>
                            <option value="cultivar">Cultivar x Cultivar</option>
                            <option value="uf_evolucao">Evolução por Estado</option>
                            <option value="cultivar_evolucao">Evolução por Cultivar</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="compare-items">Itens (Ctrl+click)</label>
                        <select id="compare-items" multiple style="height:110px;"></select>
                    </div>
                    <div class="filter-group" style="display:flex;align-items:flex-end;">
                        <button class="btn btn-accent" id="btn-compare">Comparar</button>
                    </div>
                </div>
            </div>

            <div id="comparison-results" style="display:none;">
                <div class="comparison-grid" id="compare-cards"></div>
                <div class="widget">
                    <div class="widget-header">
                        <div class="widget-title">Gráfico Comparativo</div>
                    </div>
                    <div class="chart-container-lg">
                        <canvas id="chart-comparativo"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== PANEL: DADOS ===== -->
        <div class="panel" id="panel-dados">
            <div class="results-info">
                <span class="results-count" id="results-count">Carregando...</span>
                <span id="results-page"></span>
            </div>

            <div id="loading" class="loading">
                <div class="spinner"></div>
                <p>Carregando dados...</p>
            </div>

            <div class="widget" id="table-card" style="display:none;">
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

    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
<script src="assets/js/brazil-map.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
