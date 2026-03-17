<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DataSemente - Painel Gerencial de Sementes</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- Loading overlay -->
<div id="loading-overlay" class="loading-overlay">
    <div class="spinner"></div>
    <div class="loading-text">Carregando dados...</div>
</div>

<div class="app-layout">

    <!-- ==================== SIDEBAR ==================== -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2C8 6 4 10 4 14a8 8 0 1016 0c0-4-4-8-8-12z"/><path d="M12 22v-8"/><path d="M8 18c0-2.5 1.8-4 4-4s4 1.5 4 4"/></svg>
            </div>
            <div>
                <div class="brand-name">DataSemente</div>
                <div class="brand-sub">Painel Gerencial</div>
            </div>
        </div>

        <div class="sidebar-scroll">
            <!-- Metrica do mapa -->
            <div class="sidebar-section">Metrica do mapa</div>
            <div class="metric-group">
                <button class="metric-btn active" data-metric="producao">
                    <svg class="icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 14V8h2v6H3zM7 14V5h2v9H7zM11 14V2h2v12h-2z"/></svg>
                    Producao (t)
                </button>
                <button class="metric-btn" data-metric="area">
                    <svg class="icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="2" width="12" height="12" rx="1.5"/><path d="M2 10l4-4 3 3 5-5"/></svg>
                    Area (ha)
                </button>
                <button class="metric-btn" data-metric="registros">
                    <svg class="icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="2" width="12" height="12" rx="2"/><path d="M5 6h6M5 8h6M5 10h4"/></svg>
                    Registros
                </button>
            </div>

            <!-- Filtros -->
            <div class="sidebar-section">Filtros</div>

            <!-- Safra -->
            <div class="filter-group">
                <div class="filter-label">Safra</div>
                <div class="multi-select" data-filter="safra">
                    <div class="multi-select-trigger" tabindex="0">
                        <span class="trigger-text">Todas as safras</span>
                        <svg class="trigger-chevron" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6l4 4 4-4"/></svg>
                    </div>
                    <div class="multi-select-dropdown">
                        <div class="dropdown-search"><input type="text" placeholder="Buscar safra..."></div>
                        <div class="dropdown-actions">
                            <button class="select-all">Todos</button>
                            <button class="select-none">Nenhum</button>
                        </div>
                        <div class="dropdown-options"></div>
                    </div>
                </div>
            </div>

            <!-- Especie -->
            <div class="filter-group">
                <div class="filter-label">Especie</div>
                <div class="multi-select" data-filter="especie">
                    <div class="multi-select-trigger" tabindex="0">
                        <span class="trigger-text">Todas as especies</span>
                        <svg class="trigger-chevron" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6l4 4 4-4"/></svg>
                    </div>
                    <div class="multi-select-dropdown">
                        <div class="dropdown-search"><input type="text" placeholder="Buscar especie..."></div>
                        <div class="dropdown-actions">
                            <button class="select-all">Todos</button>
                            <button class="select-none">Nenhum</button>
                        </div>
                        <div class="dropdown-options"></div>
                    </div>
                </div>
            </div>

            <!-- Cultivar -->
            <div class="filter-group">
                <div class="filter-label">Cultivar</div>
                <div class="multi-select" data-filter="cultivar">
                    <div class="multi-select-trigger" tabindex="0">
                        <span class="trigger-text">Todas as cultivares</span>
                        <svg class="trigger-chevron" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6l4 4 4-4"/></svg>
                    </div>
                    <div class="multi-select-dropdown">
                        <div class="dropdown-search"><input type="text" placeholder="Buscar cultivar..."></div>
                        <div class="dropdown-actions">
                            <button class="select-all">Todos</button>
                            <button class="select-none">Nenhum</button>
                        </div>
                        <div class="dropdown-options"></div>
                    </div>
                </div>
            </div>

            <!-- Categoria -->
            <div class="filter-group">
                <div class="filter-label">Categoria</div>
                <div class="multi-select" data-filter="categoria">
                    <div class="multi-select-trigger" tabindex="0">
                        <span class="trigger-text">Todas as categorias</span>
                        <svg class="trigger-chevron" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6l4 4 4-4"/></svg>
                    </div>
                    <div class="multi-select-dropdown">
                        <div class="dropdown-search"><input type="text" placeholder="Buscar categoria..."></div>
                        <div class="dropdown-actions">
                            <button class="select-all">Todos</button>
                            <button class="select-none">Nenhum</button>
                        </div>
                        <div class="dropdown-options"></div>
                    </div>
                </div>
            </div>

            <!-- Estado -->
            <div class="filter-group">
                <div class="filter-label">Estado (UF)</div>
                <div class="multi-select" data-filter="uf">
                    <div class="multi-select-trigger" tabindex="0">
                        <span class="trigger-text">Todos os estados</span>
                        <svg class="trigger-chevron" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6l4 4 4-4"/></svg>
                    </div>
                    <div class="multi-select-dropdown">
                        <div class="dropdown-search"><input type="text" placeholder="Buscar estado..."></div>
                        <div class="dropdown-actions">
                            <button class="select-all">Todos</button>
                            <button class="select-none">Nenhum</button>
                        </div>
                        <div class="dropdown-options"></div>
                    </div>
                </div>
            </div>

            <!-- Status -->
            <div class="filter-group">
                <div class="filter-label">Status</div>
                <div class="multi-select" data-filter="status">
                    <div class="multi-select-trigger" tabindex="0">
                        <span class="trigger-text">Todos os status</span>
                        <svg class="trigger-chevron" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6l4 4 4-4"/></svg>
                    </div>
                    <div class="multi-select-dropdown">
                        <div class="dropdown-search"><input type="text" placeholder="Buscar status..."></div>
                        <div class="dropdown-actions">
                            <button class="select-all">Todos</button>
                            <button class="select-none">Nenhum</button>
                        </div>
                        <div class="dropdown-options"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Acoes -->
        <div class="sidebar-actions">
            <button id="btn-apply" class="btn-apply">Aplicar Filtros</button>
            <button id="btn-clear" class="btn-clear">Limpar</button>
        </div>

        <!-- Footer -->
        <div class="sidebar-footer">
            <button id="btn-export-csv" class="btn-export">Exportar CSV</button>
        </div>
    </aside>

    <!-- ==================== MAIN ==================== -->
    <main class="main-content">

        <!-- Mobile hamburger -->
        <button class="hamburger" id="hamburger-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
        </button>

        <!-- Top bar -->
        <div class="top-bar">
            <div>
                <h1 class="page-title">Painel Gerencial de Sementes</h1>
                <p class="page-subtitle">Analise de campo, producao e desempenho por regiao</p>
            </div>
            <div class="top-bar-right">
                <div class="active-filters" id="active-filters"></div>
            </div>
        </div>

        <!-- KPI Row -->
        <div class="kpi-row">
            <div class="kpi-card">
                <div class="kpi-icon blue"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="14" height="14" rx="2"/><path d="M7 8h6M7 10h6M7 12h4"/></svg></div>
                <div class="kpi-info">
                    <div class="kpi-label">Registros</div>
                    <div class="kpi-value" id="kpi-registros">-</div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon green"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10 2C6 6 2 10 2 14a8 8 0 1016 0c0-4-4-8-8-12z"/></svg></div>
                <div class="kpi-info">
                    <div class="kpi-label">Especies</div>
                    <div class="kpi-value" id="kpi-especies">-</div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon purple"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10 2v16M6 4c0 3 4 4 4 8M14 4c0 3-4 4-4 8"/></svg></div>
                <div class="kpi-info">
                    <div class="kpi-label">Cultivares</div>
                    <div class="kpi-value" id="kpi-cultivares">-</div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon orange"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="2" width="16" height="16" rx="2"/><path d="M2 14l5-5 3 3 7-7"/></svg></div>
                <div class="kpi-info">
                    <div class="kpi-label">Area Total</div>
                    <div class="kpi-value" id="kpi-area">-</div>
                    <div class="kpi-unit">hectares</div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon red"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 16V10h3v6H4zM9 16V6h3v10H9zM14 16V2h3v14h-3z"/></svg></div>
                <div class="kpi-info">
                    <div class="kpi-label">Producao Estimada</div>
                    <div class="kpi-value" id="kpi-producao">-</div>
                    <div class="kpi-unit">toneladas</div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon cyan"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10 1.5a5.5 5.5 0 00-5.5 5.5c0 4 5.5 11 5.5 11s5.5-7 5.5-11A5.5 5.5 0 0010 1.5z"/><circle cx="10" cy="7" r="2"/></svg></div>
                <div class="kpi-info">
                    <div class="kpi-label">Municipios</div>
                    <div class="kpi-value" id="kpi-municipios">-</div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tab-bar">
            <button class="tab active" data-tab="visao-geral">Visao Geral</button>
            <button class="tab" data-tab="mapa">Mapa de Calor</button>
            <button class="tab" data-tab="dados">Dados Detalhados</button>
        </div>

        <!-- ===== TAB: VISAO GERAL ===== -->
        <div class="tab-content active" id="tab-visao-geral">
            <div class="cards-grid">
                <!-- Evolucao -->
                <div class="card span-2">
                    <div class="card-header">
                        <div class="card-title">Evolucao por Safra</div>
                        <div class="card-subtitle">Producao estimada ao longo das safras</div>
                    </div>
                    <div class="chart-box" style="height:350px;">
                        <canvas id="chart-evolucao"></canvas>
                    </div>
                </div>
                <!-- Top Estados -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Top 10 Estados</div>
                        <div class="card-subtitle">Producao estimada (t)</div>
                    </div>
                    <div class="chart-box" style="height:320px;">
                        <canvas id="chart-ranking-uf"></canvas>
                    </div>
                </div>
                <!-- Top Cultivares -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Top 10 Cultivares</div>
                        <div class="card-subtitle">Producao estimada (t)</div>
                    </div>
                    <div class="chart-box" style="height:320px;">
                        <canvas id="chart-ranking-cultivar"></canvas>
                    </div>
                </div>
                <!-- Top Municipios -->
                <div class="card span-2">
                    <div class="card-header">
                        <div class="card-title">Top 10 Municipios</div>
                        <div class="card-subtitle">Area plantada (ha)</div>
                    </div>
                    <div class="chart-box" style="height:300px;">
                        <canvas id="chart-ranking-municipio"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== TAB: MAPA ===== -->
        <div class="tab-content" id="tab-mapa">
            <div class="cards-grid">
                <div class="card span-2">
                    <div class="card-header">
                        <div class="card-title">Mapa de Calor por Municipio</div>
                        <span class="card-info" id="map-info">Carregando...</span>
                    </div>
                    <div class="map-wrapper">
                        <div id="map" style="height:520px;border-radius:8px;"></div>
                        <div class="map-legend" id="map-legend"></div>
                    </div>
                </div>
                <div class="card span-2">
                    <div class="card-header">
                        <div class="card-title">Dados por Municipio</div>
                        <span class="card-info" id="map-table-count">0 municipios</span>
                    </div>
                    <div class="table-scroll" style="max-height:400px;">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Municipio</th>
                                    <th>UF</th>
                                    <th>Area (ha)</th>
                                    <th>Producao (t)</th>
                                    <th>Registros</th>
                                    <th>Cultivares</th>
                                </tr>
                            </thead>
                            <tbody id="map-table-body"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== TAB: DADOS ===== -->
        <div class="tab-content" id="tab-dados">
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Registros Detalhados</div>
                        <div class="card-subtitle" id="table-results-info">Carregando...</div>
                    </div>
                    <div class="card-actions">
                        <input type="text" id="table-search" class="table-search" placeholder="Buscar nos resultados...">
                    </div>
                </div>
                <div class="table-scroll">
                    <table id="data-table">
                        <thead>
                            <tr>
                                <th data-sort="safra">Safra</th>
                                <th data-sort="especie">Especie</th>
                                <th data-sort="cultivar">Cultivar</th>
                                <th data-sort="categoria">Categoria</th>
                                <th data-sort="municipio">Municipio</th>
                                <th data-sort="uf">UF</th>
                                <th data-sort="status_registro">Status</th>
                                <th data-sort="area">Area (ha)</th>
                                <th data-sort="producao_estimada">Prod. Est.</th>
                                <th data-sort="producao_bruta">Prod. Bruta</th>
                                <th data-sort="data_plantio">Plantio</th>
                                <th data-sort="data_colheita">Colheita</th>
                            </tr>
                        </thead>
                        <tbody id="data-table-body"></tbody>
                    </table>
                </div>
                <div class="pagination" id="pagination"></div>
            </div>
        </div>

        <!-- Footer -->
        <div style="text-align:center; padding:24px 0 8px; font-size:0.78rem; color:#9ca3af;">
            Criado por <strong>Julio Lemos</strong>
        </div>

    </main>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
