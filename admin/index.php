<?php
/**
 * DataSemente - Painel Admin: Upload de CSV
 * Senha de acesso: definida em $ADMIN_PASSWORD
 */

session_start();
set_time_limit(300);
ini_set('memory_limit', '512M');
ini_set('upload_max_filesize', '100M');
ini_set('post_max_size', '110M');

$ADMIN_PASSWORD = '290212';

// --- Logout ---
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// --- Login ---
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['senha'])) {
    if ($_POST['senha'] === $ADMIN_PASSWORD) {
        $_SESSION['admin_auth'] = true;
    } else {
        $loginError = 'Senha incorreta.';
    }
}

if (empty($_SESSION['admin_auth'])) {
    showLogin($loginError);
    exit;
}

// --- Upload + Importação ---
$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv'])) {
    $file = $_FILES['csv'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = 'Erro no upload: código ' . $file['error'];
        $msgType = 'error';
    } elseif (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') {
        $msg = 'Apenas arquivos .csv são aceitos.';
        $msgType = 'error';
    } else {
        $result = importCSV($file['tmp_name']);
        $msg = $result['message'];
        $msgType = $result['type'];
    }
}

// Contagem de registros
$totalRegistros = 0;
try {
    require_once __DIR__ . '/../config.php';
    $pdo = getConnection();
    $totalRegistros = $pdo->query('SELECT COUNT(*) FROM dados_campo')->fetchColumn();
} catch (Exception $e) {
    // banco ainda não configurado
}

showDashboard($msg, $msgType, $totalRegistros);

// ===================== FUNÇÕES =====================

function importCSV(string $tmpPath): array
{
    try {
        require_once __DIR__ . '/../config.php';
        $pdo = getConnection();
    } catch (Exception $e) {
        return ['type' => 'error', 'message' => 'Erro de conexão: ' . $e->getMessage()];
    }

    $handle = fopen($tmpPath, 'r');
    if (!$handle) {
        return ['type' => 'error', 'message' => 'Não foi possível ler o arquivo.'];
    }

    // Detecta delimitador
    $firstLine = fgets($handle);
    rewind($handle);
    $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';

    // Lê cabeçalho
    $header = fgetcsv($handle, 0, $delimiter);
    $header = array_map(function ($col) {
        $col = trim($col);
        $col = mb_strtolower($col, 'UTF-8');
        $col = str_replace(' ', '_', $col);
        $col = preg_replace('/[^a-z0-9_]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $col));
        return $col;
    }, $header);

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
                break;
            }
        }
    }

    if (empty($resolvedMap)) {
        fclose($handle);
        return ['type' => 'error', 'message' => 'Nenhuma coluna reconhecida no CSV. Colunas encontradas: ' . implode(', ', $header)];
    }

    $dbCols = array_keys($resolvedMap);
    $placeholders = implode(',', array_fill(0, count($dbCols), '?'));
    $colNames = implode(',', $dbCols);
    $sql = "INSERT INTO dados_campo ($colNames) VALUES ($placeholders)";
    $stmt = $pdo->prepare($sql);

    $totalRows = 0;
    $errors = 0;
    $batchSize = 1000;

    $pdo->beginTransaction();

    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        $values = [];
        foreach ($resolvedMap as $dbCol => $csvIdx) {
            $val = isset($row[$csvIdx]) ? trim($row[$csvIdx]) : null;

            if ($val === '' || $val === '-' || $val === 'N/A') {
                $val = null;
            }

            if (in_array($dbCol, ['area', 'producao_bruta', 'producao_estimada']) && $val !== null) {
                $val = str_replace('.', '', $val);
                $val = str_replace(',', '.', $val);
            }

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
        }

        if ($totalRows % $batchSize === 0) {
            $pdo->commit();
            $pdo->beginTransaction();
        }
    }

    $pdo->commit();
    fclose($handle);

    $colsMapped = implode(', ', $dbCols);
    $msgParts = ["Importação concluída! <strong>$totalRows</strong> linhas importadas."];
    if ($errors > 0) {
        $msgParts[] = "$errors erros encontrados.";
    }
    $msgParts[] = "Colunas mapeadas: $colsMapped";

    return ['type' => 'success', 'message' => implode('<br>', $msgParts)];
}

function showLogin(string $error): void
{
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DataSemente - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0f0f1a; color: #e8e8f0; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-box { background: #1a1a2e; border: 1px solid #2d2d44; border-radius: 16px; padding: 40px; width: 100%; max-width: 380px; text-align: center; }
        .login-box h1 { font-size: 1.4rem; font-weight: 800; color: #a29bfe; margin-bottom: 6px; }
        .login-box p { font-size: 0.8rem; color: #5a5b75; margin-bottom: 28px; }
        .login-box input { width: 100%; padding: 12px 14px; border: 1px solid #2d2d44; border-radius: 10px; background: #0f0f1a; color: #e8e8f0; font-size: 0.95rem; font-family: inherit; text-align: center; letter-spacing: 4px; margin-bottom: 16px; }
        .login-box input:focus { outline: none; border-color: #6c5ce7; box-shadow: 0 0 0 3px rgba(108,92,231,0.2); }
        .login-box button { width: 100%; padding: 12px; background: #6c5ce7; color: white; border: none; border-radius: 10px; font-size: 0.9rem; font-weight: 700; cursor: pointer; font-family: inherit; transition: background 0.15s; }
        .login-box button:hover { background: #5a4bd1; }
        .error { color: #e17055; font-size: 0.82rem; margin-bottom: 14px; }
    </style>
</head>
<body>
    <form class="login-box" method="POST">
        <h1>DataSemente</h1>
        <p>Painel Administrativo</p>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <input type="password" name="senha" placeholder="Senha" autofocus required>
        <button type="submit">Entrar</button>
    </form>
</body>
</html>
<?php
}

function showDashboard(string $msg, string $msgType, int $total): void
{
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DataSemente - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0f0f1a; color: #e8e8f0; min-height: 100vh; padding: 30px 20px; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h1 { font-size: 1.3rem; font-weight: 800; color: #a29bfe; }
        .header a { color: #5a5b75; font-size: 0.82rem; text-decoration: none; }
        .header a:hover { color: #e17055; }
        .stat-card { background: #1a1a2e; border: 1px solid #2d2d44; border-radius: 12px; padding: 20px; margin-bottom: 20px; text-align: center; border-top: 3px solid #6c5ce7; }
        .stat-value { font-size: 2rem; font-weight: 800; color: #e8e8f0; }
        .stat-label { font-size: 0.72rem; color: #5a5b75; text-transform: uppercase; letter-spacing: 1px; margin-top: 4px; font-weight: 600; }
        .upload-card { background: #1a1a2e; border: 1px solid #2d2d44; border-radius: 12px; padding: 28px; }
        .upload-card h2 { font-size: 1rem; font-weight: 700; margin-bottom: 18px; }
        .drop-zone { border: 2px dashed #2d2d44; border-radius: 10px; padding: 40px 20px; text-align: center; cursor: pointer; transition: all 0.2s; margin-bottom: 16px; position: relative; }
        .drop-zone:hover, .drop-zone.dragover { border-color: #6c5ce7; background: rgba(108,92,231,0.05); }
        .drop-zone input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
        .drop-zone .icon { font-size: 2.2rem; margin-bottom: 8px; }
        .drop-zone .label { font-size: 0.88rem; color: #8b8ca7; }
        .drop-zone .sublabel { font-size: 0.75rem; color: #5a5b75; margin-top: 4px; }
        .file-name { font-size: 0.82rem; color: #a29bfe; margin-bottom: 14px; display: none; text-align: center; }
        .btn { display: block; width: 100%; padding: 14px; background: #6c5ce7; color: white; border: none; border-radius: 10px; font-size: 0.9rem; font-weight: 700; cursor: pointer; font-family: inherit; transition: all 0.15s; }
        .btn:hover { background: #5a4bd1; }
        .btn:disabled { opacity: 0.4; cursor: not-allowed; }
        .msg { padding: 14px 18px; border-radius: 10px; font-size: 0.85rem; line-height: 1.6; margin-bottom: 20px; }
        .msg.success { background: rgba(0,184,148,0.12); border: 1px solid rgba(0,184,148,0.3); color: #00b894; }
        .msg.error { background: rgba(225,112,85,0.12); border: 1px solid rgba(225,112,85,0.3); color: #e17055; }
        .hint { font-size: 0.75rem; color: #5a5b75; margin-top: 14px; line-height: 1.6; }
        .progress-bar { display: none; height: 6px; background: #2d2d44; border-radius: 3px; margin-bottom: 16px; overflow: hidden; }
        .progress-bar .fill { height: 100%; width: 0%; background: #6c5ce7; border-radius: 3px; transition: width 0.3s; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Admin - DataSemente</h1>
            <a href="?logout=1">Sair</a>
        </div>

        <div class="stat-card">
            <div class="stat-value"><?= number_format($total, 0, ',', '.') ?></div>
            <div class="stat-label">Registros no banco</div>
        </div>

        <?php if ($msg): ?>
            <div class="msg <?= $msgType ?>"><?= $msg ?></div>
        <?php endif; ?>

        <div class="upload-card">
            <h2>Importar CSV</h2>
            <form method="POST" enctype="multipart/form-data" id="upload-form">
                <div class="drop-zone" id="drop-zone">
                    <input type="file" name="csv" accept=".csv" id="csv-input" required>
                    <div class="icon">&#128196;</div>
                    <div class="label">Clique ou arraste o arquivo CSV</div>
                    <div class="sublabel">Tamanho máximo: 100MB</div>
                </div>
                <div class="file-name" id="file-name"></div>
                <div class="progress-bar" id="progress-bar"><div class="fill" id="progress-fill"></div></div>
                <button type="submit" class="btn" id="btn-submit" disabled>Enviar e Importar</button>
            </form>
            <div class="hint">
                O sistema detecta automaticamente as colunas do CSV (separador <strong>;</strong> ou <strong>,</strong>).<br>
                Colunas aceitas: safra, especie, categoria, cultivar, municipio, uf, status, area, producao_bruta, producao_estimada, data_plantio, data_colheita.
            </div>
        </div>
    </div>

    <script>
    const dropZone = document.getElementById('drop-zone');
    const csvInput = document.getElementById('csv-input');
    const fileName = document.getElementById('file-name');
    const btnSubmit = document.getElementById('btn-submit');
    const form = document.getElementById('upload-form');
    const progressBar = document.getElementById('progress-bar');
    const progressFill = document.getElementById('progress-fill');

    csvInput.addEventListener('change', () => {
        if (csvInput.files.length) {
            const f = csvInput.files[0];
            fileName.textContent = f.name + ' (' + (f.size / 1024 / 1024).toFixed(1) + ' MB)';
            fileName.style.display = 'block';
            btnSubmit.disabled = false;
        }
    });

    ['dragover', 'dragenter'].forEach(evt => {
        dropZone.addEventListener(evt, e => { e.preventDefault(); dropZone.classList.add('dragover'); });
    });
    ['dragleave', 'drop'].forEach(evt => {
        dropZone.addEventListener(evt, e => { e.preventDefault(); dropZone.classList.remove('dragover'); });
    });

    form.addEventListener('submit', () => {
        btnSubmit.disabled = true;
        btnSubmit.textContent = 'Importando... aguarde';
        progressBar.style.display = 'block';
        progressFill.style.width = '80%';
    });
    </script>
</body>
</html>
<?php
}
