<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

ini_set('memory_limit', '512M');
ini_set('max_execution_time', '300');
error_reporting(E_ALL);
ini_set('display_errors', 0);

$host = 'localhost';
$user = 'root';
$password = '';
$database = 'test';

$conn = @new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]);
    exit;
}
$conn->set_charset('utf8mb4');

// --------------------------------------------------
// Column & Table Cleaning Functions (Python Logic Ported to PHP)
// --------------------------------------------------

function cleanColumnName($columnName) {
    $name = trim(strtolower((string)$columnName));
    $name = str_replace('&', 'and', $name);
    // Replace anything except letters, numbers, and underscore
    $name = preg_replace('/[^\w]+/', '_', $name);
    $name = trim($name, '_');
    if (empty($name)) {
        $name = 'unnamed_column';
    }
    return substr($name, 0, 64);
}

function makeUniqueColumnNames($columns) {
    $uniqueColumns = [];
    $usedNames = [];

    foreach ($columns as $column) {
        $baseName = cleanColumnName($column);

        if (!isset($usedNames[$baseName])) {
            $usedNames[$baseName] = 0;
            $uniqueColumns[] = $baseName;
        } else {
            $usedNames[$baseName]++;
            $newName = substr($baseName . '_' . $usedNames[$baseName], 0, 64);
            while (isset($usedNames[$newName]) || in_array($newName, $uniqueColumns)) {
                $usedNames[$baseName]++;
                $newName = substr($baseName . '_' . $usedNames[$baseName], 0, 64);
            }
            $usedNames[$newName] = 0;
            $uniqueColumns[] = $newName;
        }
    }
    return $uniqueColumns;
}

function removeDatesFromName($fileStem) {
    $name = $fileStem;
    $datePatterns = [
        '/\b\d{1,4}[-_\/]\d{1,2}[-_\/]\d{1,4}\b/i',
        '/\b\d{8}\b/i',
        '/\bto\b/i'
    ];
    foreach ($datePatterns as $pattern) {
        $name = preg_replace($pattern, ' ', $name);
    }
    $name = preg_replace('/[-_.]+/', ' ', $name);
    $name = preg_replace('/\s+/', ' ', $name);
    return trim($name);
}

function createBaseTableName($file_name) {
    $fileStem = pathinfo($file_name, PATHINFO_FILENAME);
    $cleanStem = removeDatesFromName($fileStem);
    $tableName = cleanColumnName($cleanStem);
    if (empty($tableName)) {
        $tableName = "imported_table";
    }
    return substr($tableName, 0, 64);
}

// --------------------------------------------------
// Main Action Handler
// --------------------------------------------------

$action = $_REQUEST['action'] ?? '';

if ($action === 'process_import') {
    $raw_company = trim($_POST['company'] ?? 'NFLUA');
    $raw_date = trim($_POST['file_date'] ?? date('Y-m-d'));
    $canonical_key = trim($_POST['canonical_key'] ?? '');
    $file_name = trim($_POST['file_name'] ?? '');
    $records_json = $_POST['records'] ?? '[]';

    $chunk_index = isset($_POST['chunk_index']) ? (int)$_POST['chunk_index'] : 0;
    $total_chunks = isset($_POST['total_chunks']) ? (int)$_POST['total_chunks'] : 1;

    $records = json_decode($records_json, true);
    if (!is_array($records)) {
        $records = [];
    }

    $company_clean = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $raw_company));
    if (empty($company_clean)) $company_clean = 'nflua';

    $date_suffix = preg_replace('/[^0-9]/', '', $raw_date);
    if (empty($date_suffix)) $date_suffix = date('Ymd');

    // Base Table Mapping
    $table_map = [
        'charges_by_created_date' => 'charges_by_created_date',
        'charges_by_date_of_service' => 'charges_by_date_of_service',
        'account_receivable' => 'account_receivable_of',
        'account_rec_not_to_use' => 'account_rec_' . $date_suffix . '_not_to_use',
        'appointment_analysis' => 'appointmentappointmentanalysisgrid',
        'missing_charges' => 'appointmentmissingchargesreport',
        'patient_payments' => 'paymentpatientpayments',
        'transaction_details' => 'transactiontransactiondetailsanalysisgrid'
    ];

    if (!empty($canonical_key) && isset($table_map[$canonical_key])) {
        $base_table = $table_map[$canonical_key];
        if ($canonical_key === 'account_rec_not_to_use') {
            $target_table = "{$company_clean}_{$base_table}";
        } else {
            $target_table = "{$company_clean}_{$base_table}_{$date_suffix}";
        }
    } else {
        $target_table = createBaseTableName($file_name);
    }

    // On First Chunk: Recreate SQL Table with TEXT columns
    if ($chunk_index === 0) {
        $raw_headers = !empty($records) ? array_keys($records[0]) : ['column_1'];
        $unique_columns = makeUniqueColumnNames($raw_headers);

        $cols_sql = ["`id` INT AUTO_INCREMENT PRIMARY KEY"];
        foreach ($unique_columns as $col) {
            $cols_sql[] = "`$col` TEXT";
        }

        $create_sql = "CREATE TABLE IF NOT EXISTS `$target_table` (" . implode(', ', $cols_sql) . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $conn->query("DROP TABLE IF EXISTS `$target_table`");
        if (!$conn->query($create_sql)) {
            echo json_encode(['status' => 'error', 'message' => 'Table Creation Error: ' . $conn->error]);
            exit;
        }
    }

    // Fetch existing columns in MySQL target table
    $existing_cols = [];
    $col_res = $conn->query("SHOW COLUMNS FROM `$target_table`");
    if ($col_res) {
        while ($r = $col_res->fetch_assoc()) {
            if ($r['Field'] !== 'id') {
                $existing_cols[] = $r['Field'];
            }
        }
    }

    // Insert Chunk Data
    $inserted = 0;
    if (!empty($records) && !empty($existing_cols)) {
        $raw_headers = array_keys($records[0]);
        $cleaned_headers = makeUniqueColumnNames($raw_headers);

        // Build Insert Query Column Headers
        $escaped_cols = array_map(function($c) { return "`$c`"; }, $existing_cols);
        $col_sql = implode(', ', $escaped_cols);

        $val_rows = [];
        foreach ($records as $r) {
            $vals = [];
            foreach ($existing_cols as $idx => $db_col) {
                // Find corresponding raw header by cleaned index
                $raw_key = $raw_headers[$idx] ?? null;
                $val = ($raw_key !== null && isset($r[$raw_key])) ? $r[$raw_key] : null;

                if ($val !== null && $val !== '' && strpos(strtolower($db_col), 'date') !== false && strpos(strtolower($db_col), 'update') === false) {
                    if (is_numeric($val) && (float)$val > 10000 && (float)$val < 70000) {
                        $days = (int)floor((float)$val);
                        $val = date('n/j/Y', strtotime("1899-12-30 +{$days} days"));
                    } else {
                        $ts = strtotime((string)$val);
                        if ($ts !== false && $ts > 0) {
                            $val = date('n/j/Y', $ts);
                        }
                    }
                }

                if ($val === null || $val === '' || (is_string($val) && strtolower(trim($val)) === 'nan')) {
                    $vals[] = "''";
                } else {
                    $vals[] = "'" . $conn->real_escape_string((string)$val) . "'";
                }
            }
            $val_rows[] = "(" . implode(', ', $vals) . ")";
        }

        if (!empty($val_rows)) {
            $insert_sql = "INSERT INTO `$target_table` ($col_sql) VALUES " . implode(', ', $val_rows);
            if ($conn->query($insert_sql)) {
                $inserted = count($records);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'MySQL Insert Error: ' . $conn->error]);
                exit;
            }
        }
    }

    // Generate Dynamic Output Dashboard HTML file on Final Chunk
    $dashboard_filename = "";
    if ($chunk_index >= $total_chunks - 1) {
        $company_upper = strtoupper($raw_company);
        $formatted_date = date('Y-m-d', strtotime($raw_date));
        $dashboard_filename = "{$company_upper}_{$formatted_date}.html";
        $target_html_path = __DIR__ . '/' . $dashboard_filename;

        $base_template_path = __DIR__ . '/dashboard2.html';
        if (file_exists($base_template_path)) {
            $html_content = file_get_contents($base_template_path);

            $html_content = str_replace(
                '<title>Healthcare AR & Revenue Cycle Executive Analytics Dashboard</title>',
                "<title>{$company_upper} Analytics Dashboard - {$formatted_date}</title>",
                $html_content
            );

            $html_content = str_replace(
                '<span class="brand-title">Narayan Perinchery Analytics Demo</span>',
                "<span class=\"brand-title\">{$company_upper} Healthcare Analytics ({$formatted_date})</span>",
                $html_content
            );

            $config_script = "<script>window.CUSTOM_COMPANY = '{$company_upper}'; window.CUSTOM_DATE = '{$formatted_date}';</script>\n</head>";
            $html_content = str_replace('</head>', $config_script, $html_content);

            $old_ajax = "url: 'demo_api.php',";
            $new_ajax = "url: 'demo_api.php?company=' + (window.CUSTOM_COMPANY || 'NFLUA') + '&date=' + (window.CUSTOM_DATE || '{$formatted_date}'),";
            $html_content = str_replace($old_ajax, $new_ajax, $html_content);

            file_put_contents($target_html_path, $html_content);
        }
    }

    echo json_encode([
        'status' => 'success',
        'message' => "Successfully created table '{$target_table}' & inserted {$inserted} rows.",
        'table_name' => $target_table,
        'chunk_index' => $chunk_index,
        'inserted_count' => $inserted,
        'dashboard_url' => $dashboard_filename
    ]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action specification.']);
exit;
