<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';

try {
    $pdo = getConnection();

    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = ROWS_PER_PAGE;
    $offset = ($page - 1) * $limit;

    $allowedSort = [
        'safra', 'especie', 'categoria', 'cultivar', 'municipio', 'uf',
        'status_registro', 'area', 'producao_bruta', 'producao_estimada',
        'data_plantio', 'data_colheita'
    ];
    $sortCol = in_array($_GET['sort'] ?? '', $allowedSort) ? $_GET['sort'] : 'id';
    $sortDir = ($_GET['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

    [$whereClause, $params] = buildFilters($_GET);

    // Count
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM dados_campo $whereClause");
    $countStmt->execute($params);
    $totalRows = (int)$countStmt->fetchColumn();
    $totalPages = max(1, ceil($totalRows / $limit));

    // Data
    $sql = "SELECT id, safra, especie, categoria, cultivar, municipio, uf,
                   status_registro, data_plantio, data_colheita,
                   area, producao_bruta, producao_estimada
            FROM dados_campo $whereClause
            ORDER BY $sortCol $sortDir
            LIMIT $limit OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        if ($row['area'] !== null) {
            $row['area_fmt'] = number_format((float)$row['area'], 2, ',', '.');
        }
        if ($row['producao_bruta'] !== null) {
            $row['producao_bruta_fmt'] = number_format((float)$row['producao_bruta'], 2, ',', '.');
        }
        if ($row['producao_estimada'] !== null) {
            $row['producao_estimada_fmt'] = number_format((float)$row['producao_estimada'], 2, ',', '.');
        }
        if ($row['data_plantio']) {
            $d = DateTime::createFromFormat('Y-m-d', $row['data_plantio']);
            $row['data_plantio_fmt'] = $d ? $d->format('d/m/Y') : $row['data_plantio'];
        }
        if ($row['data_colheita']) {
            $d = DateTime::createFromFormat('Y-m-d', $row['data_colheita']);
            $row['data_colheita_fmt'] = $d ? $d->format('d/m/Y') : $row['data_colheita'];
        }
    }
    unset($row);

    echo json_encode([
        'success'    => true,
        'data'       => $rows,
        'pagination' => [
            'page'       => $page,
            'totalPages' => $totalPages,
            'totalRows'  => $totalRows,
            'perPage'    => $limit,
        ],
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
