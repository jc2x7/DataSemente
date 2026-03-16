<?php
/**
 * Exportação dos resultados filtrados em CSV
 */

require_once __DIR__ . '/../config.php';

try {
    $pdo = getConnection();

    [$whereClause, $params] = buildFilters($_GET);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM dados_campo $whereClause");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    if ($total > MAX_EXPORT_ROWS) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => "Limite excedido. Máx: " . number_format(MAX_EXPORT_ROWS, 0, ',', '.') . " linhas. Aplique mais filtros.",
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $filename = 'datasemente_' . date('Y-m-d_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    fputcsv($output, [
        'Safra', 'Espécie', 'Categoria', 'Cultivar', 'Município', 'UF',
        'Status', 'Data Plantio', 'Data Colheita',
        'Área (ha)', 'Produção Bruta (t)', 'Produção Estimada (t)'
    ], ';');

    $sql = "SELECT safra, especie, categoria, cultivar, municipio, uf,
                   status_registro, data_plantio, data_colheita,
                   area, producao_bruta, producao_estimada
            FROM dados_campo $whereClause
            ORDER BY safra DESC, especie, uf, municipio
            LIMIT " . MAX_EXPORT_ROWS;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    while ($row = $stmt->fetch()) {
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
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
