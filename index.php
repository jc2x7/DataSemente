<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DataSemente - Dados de Campo de Sementes do Brasil</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- Header -->
<header class="header">
    <div class="container">
        <h1>DataSemente</h1>
        <p>Consulta de dados de campo e produção de sementes do Brasil</p>
    </div>
</header>

<main class="container">

    <!-- Estatísticas -->
    <div class="stats-bar">
        <div class="stat-card">
            <div class="stat-value" id="stat-registros">-</div>
            <div class="stat-label">Registros</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" id="stat-culturas">-</div>
            <div class="stat-label">Culturas</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" id="stat-estados">-</div>
            <div class="stat-label">Estados</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" id="stat-municipios">-</div>
            <div class="stat-label">Municípios</div>
        </div>
    </div>

    <!-- Filtros -->
    <section class="filters-card">
        <h2>Filtros</h2>
        <div class="filters-grid">
            <div class="filter-group">
                <label for="filter-busca">Busca geral</label>
                <input type="text" id="filter-busca" placeholder="Cultura, cultivar ou município...">
            </div>
            <div class="filter-group">
                <label for="filter-safra">Safra</label>
                <select id="filter-safra">
                    <option value="">Todas</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="filter-cultura">Cultura</label>
                <select id="filter-cultura">
                    <option value="">Todas</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="filter-estado">Estado</label>
                <select id="filter-estado">
                    <option value="">Todos</option>
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
        </div>
        <div class="filters-actions">
            <button class="btn btn-primary" id="btn-search">Buscar</button>
            <button class="btn btn-secondary" id="btn-clear">Limpar Filtros</button>
            <button class="btn btn-export" id="btn-export">Exportar CSV</button>
        </div>
    </section>

    <!-- Info dos resultados -->
    <div class="results-info">
        <span class="results-count" id="results-count">Carregando...</span>
        <span id="results-page"></span>
    </div>

    <!-- Loading -->
    <div id="loading" class="loading">
        <div class="spinner"></div>
        <p>Carregando dados...</p>
    </div>

    <!-- Tabela de resultados -->
    <div class="table-wrapper" id="table-wrapper" style="display:none;">
        <table>
            <thead>
                <tr>
                    <th data-sort="safra">Safra <span class="sort-icon">&#8597;</span></th>
                    <th data-sort="cultura">Cultura <span class="sort-icon">&#8597;</span></th>
                    <th data-sort="cultivar">Cultivar <span class="sort-icon">&#8597;</span></th>
                    <th data-sort="estado">UF <span class="sort-icon">&#8597;</span></th>
                    <th data-sort="municipio">Município <span class="sort-icon">&#8597;</span></th>
                    <th data-sort="regiao">Região <span class="sort-icon">&#8597;</span></th>
                    <th data-sort="area_plantada">Área (ha) <span class="sort-icon">&#8597;</span></th>
                    <th data-sort="produtividade">Produtiv. (kg/ha) <span class="sort-icon">&#8597;</span></th>
                    <th data-sort="producao">Produção (t) <span class="sort-icon">&#8597;</span></th>
                    <th data-sort="data_plantio">Plantio <span class="sort-icon">&#8597;</span></th>
                    <th data-sort="data_colheita">Colheita <span class="sort-icon">&#8597;</span></th>
                </tr>
            </thead>
            <tbody id="results-body">
            </tbody>
        </table>
    </div>

    <!-- Paginação -->
    <div class="pagination" id="pagination"></div>

</main>

<!-- Footer -->
<footer class="footer">
    <p>DataSemente &copy; <?= date('Y') ?> - Dados de campo e produção de sementes do Brasil</p>
</footer>

<script src="assets/js/app.js"></script>
</body>
</html>
