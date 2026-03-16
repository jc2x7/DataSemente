<?php
/**
 * API de estatísticas para dashboard e gráficos
 * Endpoints via ?type=heatmap|ranking|evolucao|comparativo
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';

try {
    $pdo = getConnection();
    $type = $_GET['type'] ?? 'heatmap';

    [$whereClause, $params] = buildFilters($_GET);

    switch ($type) {

        // ========== MAPA DE CALOR: produção por UF ==========
        case 'heatmap':
            $sql = "SELECT uf,
                           COUNT(*) as registros,
                           COALESCE(SUM(area), 0) as total_area,
                           COALESCE(SUM(producao_estimada), 0) as total_producao,
                           COALESCE(SUM(producao_bruta), 0) as total_producao_bruta,
                           COUNT(DISTINCT cultivar) as total_cultivares,
                           COUNT(DISTINCT municipio) as total_municipios
                    FROM dados_campo
                    $whereClause
                    GROUP BY uf
                    ORDER BY total_producao DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
            break;

        // ========== RANKING: top estados, cultivares, municípios ==========
        case 'ranking':
            $by = $_GET['by'] ?? 'uf';
            $metric = $_GET['metric'] ?? 'producao_estimada';
            $limitRank = min(20, max(5, (int)($_GET['limit'] ?? 10)));

            $allowedBy = ['uf', 'cultivar', 'municipio', 'especie'];
            $allowedMetric = ['producao_estimada', 'producao_bruta', 'area'];
            if (!in_array($by, $allowedBy)) $by = 'uf';
            if (!in_array($metric, $allowedMetric)) $metric = 'producao_estimada';

            $sql = "SELECT $by as label,
                           COALESCE(SUM($metric), 0) as valor,
                           COUNT(*) as registros,
                           COALESCE(SUM(area), 0) as total_area
                    FROM dados_campo
                    $whereClause
                    GROUP BY $by
                    ORDER BY valor DESC
                    LIMIT $limitRank";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
            break;

        // ========== EVOLUÇÃO: dados ao longo das safras ==========
        case 'evolucao':
            $metric = $_GET['metric'] ?? 'producao_estimada';
            $allowedMetric = ['producao_estimada', 'producao_bruta', 'area'];
            if (!in_array($metric, $allowedMetric)) $metric = 'producao_estimada';

            $groupBy = $_GET['group'] ?? 'total';

            if ($groupBy === 'uf' && !empty($_GET['uf'])) {
                $ufs = is_array($_GET['uf']) ? $_GET['uf'] : [$_GET['uf']];
                $ufPlaceholders = implode(',', array_fill(0, count($ufs), '?'));

                // Remove UF filter from where to avoid duplication, rebuild without uf
                $customGet = $_GET;
                unset($customGet['uf']);
                [$customWhere, $customParams] = buildFilters($customGet);

                $ufCondition = "uf IN ($ufPlaceholders)";
                if ($customWhere) {
                    $customWhere .= " AND $ufCondition";
                } else {
                    $customWhere = "WHERE $ufCondition";
                }
                $customParams = array_merge($customParams, $ufs);

                $sql = "SELECT safra, uf as serie,
                               COALESCE(SUM($metric), 0) as valor
                        FROM dados_campo
                        $customWhere
                        GROUP BY safra, uf
                        ORDER BY safra, uf";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($customParams);
            } elseif ($groupBy === 'cultivar' && !empty($_GET['cultivar'])) {
                $cultivares = is_array($_GET['cultivar']) ? $_GET['cultivar'] : [$_GET['cultivar']];
                $cvPlaceholders = implode(',', array_fill(0, count($cultivares), '?'));

                $customGet = $_GET;
                unset($customGet['cultivar']);
                [$customWhere, $customParams] = buildFilters($customGet);

                $cvCondition = "cultivar IN ($cvPlaceholders)";
                if ($customWhere) {
                    $customWhere .= " AND $cvCondition";
                } else {
                    $customWhere = "WHERE $cvCondition";
                }
                $customParams = array_merge($customParams, $cultivares);

                $sql = "SELECT safra, cultivar as serie,
                               COALESCE(SUM($metric), 0) as valor
                        FROM dados_campo
                        $customWhere
                        GROUP BY safra, cultivar
                        ORDER BY safra, cultivar";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($customParams);
            } else {
                $sql = "SELECT safra, 'Total' as serie,
                               COALESCE(SUM($metric), 0) as valor
                        FROM dados_campo
                        $whereClause
                        GROUP BY safra
                        ORDER BY safra";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            }

            $rows = $stmt->fetchAll();

            // Reorganiza para formato de séries
            $series = [];
            $safras = [];
            foreach ($rows as $row) {
                $safras[$row['safra']] = true;
                $series[$row['serie']][$row['safra']] = (float)$row['valor'];
            }

            echo json_encode([
                'success' => true,
                'safras'  => array_keys($safras),
                'series'  => $series,
            ], JSON_UNESCAPED_UNICODE);
            break;

        // ========== COMPARATIVO: lado a lado ==========
        case 'comparativo':
            $compareBy = $_GET['compare'] ?? 'safra';

            if ($compareBy === 'safra' && !empty($_GET['safra']) && is_array($_GET['safra'])) {
                $results = [];
                foreach ($_GET['safra'] as $safra) {
                    $stmt = $pdo->prepare("
                        SELECT ? as label,
                               COUNT(*) as registros,
                               COALESCE(SUM(area), 0) as total_area,
                               COALESCE(SUM(producao_estimada), 0) as total_producao,
                               COALESCE(SUM(producao_bruta), 0) as total_producao_bruta,
                               COUNT(DISTINCT cultivar) as total_cultivares,
                               COUNT(DISTINCT municipio) as total_municipios,
                               COUNT(DISTINCT uf) as total_estados
                        FROM dados_campo
                        WHERE safra = ?
                    ");
                    $stmt->execute([$safra, $safra]);
                    $results[] = $stmt->fetch();
                }
                echo json_encode(['success' => true, 'data' => $results], JSON_UNESCAPED_UNICODE);

            } elseif ($compareBy === 'cultivar' && !empty($_GET['cultivar']) && is_array($_GET['cultivar'])) {
                $results = [];
                foreach ($_GET['cultivar'] as $cv) {
                    [$extraWhere, $extraParams] = buildFilters(array_diff_key($_GET, ['cultivar' => 1]));
                    $cvWhere = $extraWhere
                        ? "$extraWhere AND cultivar = ?"
                        : "WHERE cultivar = ?";
                    $extraParams[] = $cv;

                    $stmt = $pdo->prepare("
                        SELECT ? as label,
                               COUNT(*) as registros,
                               COALESCE(SUM(area), 0) as total_area,
                               COALESCE(SUM(producao_estimada), 0) as total_producao,
                               COALESCE(SUM(producao_bruta), 0) as total_producao_bruta,
                               COUNT(DISTINCT municipio) as total_municipios,
                               COUNT(DISTINCT uf) as total_estados
                        FROM dados_campo
                        $cvWhere
                    ");
                    $stmt->execute(array_merge([$cv], $extraParams));
                    $results[] = $stmt->fetch();
                }
                echo json_encode(['success' => true, 'data' => $results], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['success' => false, 'error' => 'Selecione pelo menos 2 itens para comparar.'], JSON_UNESCAPED_UNICODE);
            }
            break;

        // ========== CULTIVARES POR FILTRO (autocomplete) ==========
        case 'cultivares':
            $sql = "SELECT DISTINCT cultivar FROM dados_campo $whereClause ORDER BY cultivar LIMIT 200";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Tipo inválido'], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
