<?php
/**
 * API de busca com paginação
 * Retorna dados em JSON para requisições AJAX
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';

try {
    $pdo = getConnection();

    // Parâmetros de paginação
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = ROWS_PER_PAGE;
    $offset = ($page - 1) * $limit;

    // Parâmetros de ordenação
    $allowedSortCols = [
        'safra', 'cultura', 'cultivar', 'estado', 'municipio',
        'regiao', 'area_plantada', 'produtividade', 'producao',
        'data_plantio', 'data_colheita'
    ];
    $sortCol = in_array($_GET['sort'] ?? '', $allowedSortCols) ? $_GET['sort'] : 'id';
    $sortDir = ($_GET['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

    // Construção dos filtros
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

    // Conta total de resultados
    $countSql = "SELECT COUNT(*) FROM dados_campo $whereClause";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalRows = (int)$countStmt->fetchColumn();
    $totalPages = max(1, ceil($totalRows / $limit));

    // Busca os dados
    $sql = "SELECT id, safra, cultura, cultivar, estado, municipio, regiao,
                   area_plantada, produtividade, producao, data_plantio, data_colheita
            FROM dados_campo
            $whereClause
            ORDER BY $sortCol $sortDir
            LIMIT $limit OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Formata números para exibição BR
    foreach ($rows as &$row) {
        if ($row['area_plantada'] !== null) {
            $row['area_plantada_fmt'] = number_format((float)$row['area_plantada'], 2, ',', '.');
        }
        if ($row['produtividade'] !== null) {
            $row['produtividade_fmt'] = number_format((float)$row['produtividade'], 2, ',', '.');
        }
        if ($row['producao'] !== null) {
            $row['producao_fmt'] = number_format((float)$row['producao'], 2, ',', '.');
        }
        if ($row['data_plantio'] !== null) {
            $d = DateTime::createFromFormat('Y-m-d', $row['data_plantio']);
            $row['data_plantio_fmt'] = $d ? $d->format('d/m/Y') : $row['data_plantio'];
        }
        if ($row['data_colheita'] !== null) {
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
    echo json_encode([
        'success' => false,
        'error'   => 'Erro ao consultar dados: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
