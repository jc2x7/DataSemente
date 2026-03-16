<?php
/**
 * Script de importação do CSV para MySQL
 *
 * Uso via terminal (SSH):
 *   php import_csv.php /caminho/para/arquivo.csv
 *
 * IMPORTANTE: Remova ou proteja este arquivo após a importação!
 */

set_time_limit(0);
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../config.php';

if (php_sapi_name() === 'cli') {
    if ($argc < 2) {
        echo "Uso: php import_csv.php <caminho_do_csv>\n";
        exit(1);
    }
    $csvFile = $argv[1];
} else {
    if (!isset($_GET['file'])) {
        echo "<p>Passe o parâmetro ?file=caminho/do/arquivo.csv</p>";
        exit(1);
    }
    $csvFile = $_GET['file'];
    echo "<pre>";
}

if (!file_exists($csvFile)) {
    echo "Arquivo não encontrado: $csvFile\n";
    exit(1);
}

$pdo = getConnection();

// Detecta delimitador
$firstLine = fgets(fopen($csvFile, 'r'));
$delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
echo "Delimitador detectado: '$delimiter'\n";

$handle = fopen($csvFile, 'r');

// Lê cabeçalho e normaliza
$header = fgetcsv($handle, 0, $delimiter);
$header = array_map(function ($col) {
    $col = trim($col);
    $col = mb_strtolower($col, 'UTF-8');
    $col = str_replace(' ', '_', $col);
    // Remove acentos
    $col = preg_replace('/[^a-z0-9_]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $col));
    return $col;
}, $header);

echo "Colunas no CSV: " . implode(' | ', $header) . "\n\n";

// Mapeamento das colunas do CSV → banco
$columnMap = [
    'safra'             => ['safra', 'ano_safra', 'crop_year'],
    'especie'           => ['especie', 'especies', 'species', 'cultura', 'crop'],
    'categoria'         => ['categoria', 'category', 'cat'],
    'cultivar'          => ['cultivar', 'variedade', 'variety'],
    'municipio'         => ['municipio', 'cidade', 'city', 'nome_municipio'],
    'uf'                => ['uf', 'estado', 'state', 'sigla_uf'],
    'status_registro'   => ['status', 'status_registro', 'situacao'],
    'data_plantio'      => ['data_do_plantio', 'data_plantio', 'plantio', 'dt_plantio'],
    'data_colheita'     => ['data_de_colheita', 'data_colheita', 'colheita', 'dt_colheita'],
    'area'              => ['area', 'area_plantada', 'hectares', 'area_ha'],
    'producao_bruta'    => ['producao_bruta', 'prod_bruta', 'producao_real'],
    'producao_estimada' => ['producao_estimada', 'prod_estimada', 'estimativa'],
];

$resolvedMap = [];
foreach ($columnMap as $dbCol => $csvOptions) {
    foreach ($csvOptions as $opt) {
        $idx = array_search($opt, $header);
        if ($idx !== false) {
            $resolvedMap[$dbCol] = $idx;
            echo "  $dbCol => '$opt' (col $idx)\n";
            break;
        }
    }
}

if (empty($resolvedMap)) {
    echo "\nNenhuma coluna mapeada. Ajuste o \$columnMap.\n";
    echo "Colunas disponíveis: " . implode(', ', $header) . "\n";
    exit(1);
}

echo "\n";

$dbCols = array_keys($resolvedMap);
$placeholders = implode(',', array_fill(0, count($dbCols), '?'));
$colNames = implode(',', $dbCols);
$sql = "INSERT INTO dados_campo ($colNames) VALUES ($placeholders)";
$stmt = $pdo->prepare($sql);

$batchSize = 1000;
$totalRows = 0;
$errors = 0;

$pdo->beginTransaction();

while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
    $values = [];
    foreach ($resolvedMap as $dbCol => $csvIdx) {
        $val = isset($row[$csvIdx]) ? trim($row[$csvIdx]) : null;

        if ($val === '' || $val === '-' || $val === 'N/A') {
            $val = null;
        }

        // Números: troca formato BR por formato SQL
        if (in_array($dbCol, ['area', 'producao_bruta', 'producao_estimada']) && $val !== null) {
            $val = str_replace('.', '', $val);
            $val = str_replace(',', '.', $val);
        }

        // Datas BR (dd/mm/aaaa) → SQL (aaaa-mm-dd)
        if (in_array($dbCol, ['data_plantio', 'data_colheita']) && $val !== null) {
            if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $val, $m)) {
                $val = "$m[3]-$m[2]-$m[1]";
            }
        }

        $values[] = $val;
    }

    try {
        $stmt->execute($values);
        $totalRows++;
    } catch (PDOException $e) {
        $errors++;
        if ($errors <= 10) {
            echo "Erro linha $totalRows: " . $e->getMessage() . "\n";
        }
    }

    if ($totalRows % $batchSize === 0) {
        $pdo->commit();
        $pdo->beginTransaction();
        echo "  $totalRows linhas importadas...\n";
    }
}

$pdo->commit();
fclose($handle);

echo "\n========================================\n";
echo "Importação concluída!\n";
echo "Linhas importadas: $totalRows\n";
echo "Erros: $errors\n";
echo "========================================\n";
