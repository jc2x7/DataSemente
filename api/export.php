<?php
/**
 * Exportação dos resultados filtrados em CSV
 */

require_once __DIR__ . '/../config.php';

try {
    $pdo = getConnection();

    // Construção dos filtros (mesma lógica do search.php)
    $where = [];
    $params = [];

    if (!empty($_GET['safra'])) {
        $where[] = 'safra = ?';
        $params[] = $_GET['safra'];
    }

    if (!empty($_GET['cultura'])) {
        $where[] = 'cultura = ?';
        $params[] = $_GET['cultura'];
    }

    if (!empty($_GET['cultivar'])) {
        $where[] = 'cultivar LIKE ?';
        $params[] = '%' . $_GET['cultivar'] . '%';
    }

    if (!empty($_GET['estado'])) {
        $where[] = 'estado = ?';
        $params[] = $_GET['estado'];
    }

    if (!empty($_GET['municipio'])) {
        $where[] = 'municipio LIKE ?';
        $params[] = '%' . $_GET['municipio'] . '%';
    }

    if (!empty($_GET['busca'])) {
        $where[] = '(cultura LIKE ? OR cultivar LIKE ? OR municipio LIKE ?)';
        $term = '%' . $_GET['busca'] . '%';
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Verifica total antes de exportar
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM dados_campo $whereClause");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    if ($total > MAX_EXPORT_ROWS) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => "Limite de exportação excedido. Máximo: " . number_format(MAX_EXPORT_ROWS, 0, ',', '.') . " linhas. Aplique mais filtros.",
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Headers para download do CSV
    $filename = 'datasemente_export_' . date('Y-m-d_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // BOM para Excel reconhecer UTF-8
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Cabeçalho
    fputcsv($output, [
        'Safra', 'Cultura', 'Cultivar', 'Estado', 'Município', 'Região',
        'Área Plantada (ha)', 'Produtividade (kg/ha)', 'Produção (t)',
        'Data Plantio', 'Data Colheita'
    ], ';');

    // Dados
    $sql = "SELECT safra, cultura, cultivar, estado, municipio, regiao,
                   area_plantada, produtividade, producao, data_plantio, data_colheita
            FROM dados_campo
            $whereClause
            ORDER BY safra DESC, cultura, estado, municipio
            LIMIT " . MAX_EXPORT_ROWS;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    while ($row = $stmt->fetch()) {
        // Formata para padrão BR
        if ($row['data_plantio']) {
            $d = DateTime::createFromFormat('Y-m-d', $row['data_plantio']);
            $row['data_plantio'] = $d ? $d->format('d/m/Y') : $row['data_plantio'];
        }
        if ($row['data_colheita']) {
            $d = DateTime::createFromFormat('Y-m-d', $row['data_colheita']);
            $row['data_colheita'] = $d ? $d->format('d/m/Y') : $row['data_colheita'];
        }

        fputcsv($output, array_values($row), ';');
    }

    fclose($output);

} catch (Exception $e) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Erro na exportação: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
