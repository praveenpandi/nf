<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

error_reporting(E_ALL);
ini_set('display_errors', 0);

$host = '72.60.122.109';
$user = 'u295462275_praveenpandi';
$password = 'W4dxj1j4y6@9786';
$database = 'u295462275_nf';

$conn = @new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]);
    exit;
}
$conn->set_charset('utf8mb4');

// Dynamic Table Name Resolution per Company & Date
$req_company = isset($_GET['company']) ? strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $_GET['company'])) : 'nflua';
if (empty($req_company)) $req_company = 'nflua';

$req_date = isset($_GET['date']) ? $_GET['date'] : '';
$date_suffix = preg_replace('/[^0-9]/', '', $req_date);

function resolveTable($conn, $company, $baseName, $suffix) {
    if ($baseName === 'account_receivable_of') {
        $target = "{$company}_account_receivable_of_{$suffix}";
    } elseif ($baseName === 'account_rec_not_to_use') {
        $target = "{$company}_account_rec_{$suffix}_not_to_use";
    } else {
        $target = "{$company}_{$baseName}_{$suffix}";
    }
    
    if (!empty($suffix)) {
        $check = $conn->query("SHOW TABLES LIKE '$target'");
        if ($check && $check->num_rows > 0) {
            return "`$target`";
        }
    }
    
    // Pattern Search 1: Match company and baseName
    if ($baseName === 'account_receivable_of') {
        $pattern = "{$company}_account_receivable_of_%";
    } elseif ($baseName === 'account_rec_not_to_use') {
        $pattern = "{$company}_account_rec_%_not_to_use";
    } else {
        $pattern = "{$company}_{$baseName}_%";
    }

    $res = $conn->query("SHOW TABLES LIKE '$pattern'");
    if ($res && $res->num_rows > 0) {
        $tables = [];
        while ($row = $res->fetch_array()) {
            $tables[] = $row[0];
        }
        rsort($tables);
        return "`{$tables[0]}`";
    }

    // Pattern Search 2: Match any company and baseName
    if ($baseName === 'account_receivable_of') {
        $pattern_any = "%_account_receivable_of_%";
    } elseif ($baseName === 'account_rec_not_to_use') {
        $pattern_any = "%_account_rec_%_not_to_use";
    } else {
        $pattern_any = "%_{$baseName}_%";
    }
    $res_any = $conn->query("SHOW TABLES LIKE '$pattern_any'");
    if ($res_any && $res_any->num_rows > 0) {
        $tables = [];
        while ($row = $res_any->fetch_array()) {
            $tables[] = $row[0];
        }
        rsort($tables);
        return "`{$tables[0]}`";
    }

    return "`$target`";
}

function sqlDate($col) {
    return "(CASE 
        WHEN {$col} REGEXP '^[0-9]{4,5}(\\.[0-9]+)?$' THEN DATE_ADD('1899-12-30', INTERVAL CAST({$col} AS SIGNED) DAY)
        WHEN {$col} LIKE '%/%/%' THEN STR_TO_DATE({$col}, '%m/%d/%Y')
        WHEN {$col} REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}' THEN STR_TO_DATE(SUBSTRING({$col}, 1, 10), '%Y-%m-%d')
        ELSE STR_TO_DATE({$col}, '%Y-%m-%d')
    END)";
}

$tbl_charges_created = resolveTable($conn, $req_company, 'charges_by_created_date', $date_suffix);
$tbl_charges_dos = resolveTable($conn, $req_company, 'charges_by_date_of_service', $date_suffix);
$tbl_ar = resolveTable($conn, $req_company, 'account_receivable_of', $date_suffix);
$tbl_ar_not_use = resolveTable($conn, $req_company, 'account_rec_not_to_use', $date_suffix);
$tbl_appt = resolveTable($conn, $req_company, 'appointmentappointmentanalysisgrid', $date_suffix);
$tbl_missing = resolveTable($conn, $req_company, 'appointmentmissingchargesreport', $date_suffix);
$tbl_patient_pmt = resolveTable($conn, $req_company, 'paymentpatientpayments', $date_suffix);
$tbl_tx_grid = resolveTable($conn, $req_company, 'transactiontransactiondetailsanalysisgrid', $date_suffix);

$cd_expr = sqlDate('created_date');
$dos_expr = sqlDate('date_of_service');
$appt_expr = sqlDate('appointment_date');
$ledger_expr = sqlDate('ledger_date');

// Action handler for AR Aging Drill-Down Analytics
if (isset($_GET['action']) && $_GET['action'] === 'ar_drilldown') {
    $bucket = $_GET['bucket'] ?? '30-60';
    $type = 'ar';
    
    if ($bucket === 'charge_lag') {
        $type = 'charge_lag';
        $title = "Charge Lag Breakdown Analytics (Unique Charges)";
        $sql = "SELECT 
            charge_id,
            COALESCE(NULLIF(rendering_provider, ''), 'N/A') AS rendering_provider,
            date_of_service,
            created_date,
            DATEDIFF({$cd_expr}, {$dos_expr}) AS lag_days,
            ROUND(SUM(charge_amount), 2) AS total_charge
        FROM {$tbl_charges_created}
        WHERE created_date IS NOT NULL 
          AND date_of_service IS NOT NULL 
          AND created_date <> '0000-00-00' 
          AND date_of_service <> '0000-00-00'
          AND {$cd_expr} >= {$dos_expr}
        GROUP BY charge_id, rendering_provider, date_of_service, created_date
        ORDER BY lag_days DESC
        LIMIT 200";

        $res = $conn->query($sql);
        $records = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $records[] = [
                    'charge_id' => $row['charge_id'],
                    'rendering_provider' => $row['rendering_provider'],
                    'date_of_service' => $row['date_of_service'],
                    'created_date' => $row['created_date'],
                    'lag_days' => (int)$row['lag_days'],
                    'total_charge' => (float)$row['total_charge']
                ];
            }
        }
    } elseif ($bucket === 'annual_gross_revenue') {
        $type = 'annual_gross_revenue';
        $title = "Annual Gross Revenue Breakdown (Total Collections / 12)";
        $sql = "SELECT 
            Year,
            ROUND(SUM(Insurance_Payment), 2) AS Total_Insurance_Payment,
            ROUND(SUM(Patient_Payment), 2) AS Total_Patient_Payment,
            ROUND(SUM(Insurance_Payment + Patient_Payment), 2) AS Total_Annual_Collections,
            ROUND(SUM(Insurance_Payment + Patient_Payment) / 12, 2) AS Monthly_Gross_Revenue
        FROM (
            SELECT 
                YEAR({$cd_expr}) AS Year,
                SUM(insurance_payment) AS Insurance_Payment,
                0 AS Patient_Payment
            FROM {$tbl_tx_grid}
            WHERE created_date IS NOT NULL AND created_date <> '0000-00-00'
            GROUP BY YEAR({$cd_expr})
            
            UNION ALL
            
            SELECT 
                YEAR({$cd_expr}) AS Year,
                0 AS Insurance_Payment,
                SUM(collected_amount) AS Patient_Payment
            FROM {$tbl_patient_pmt}
            WHERE created_date IS NOT NULL AND created_date <> '0000-00-00'
            GROUP BY YEAR({$cd_expr})
        ) AS combined_collections
        GROUP BY Year
        ORDER BY Year DESC";

        $res = $conn->query($sql);
        $records = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $records[] = [
                    'year' => $row['Year'],
                    'insurance_payment' => (float)$row['Total_Insurance_Payment'],
                    'patient_payment' => (float)$row['Total_Patient_Payment'],
                    'total_collections' => (float)$row['Total_Annual_Collections'],
                    'monthly_gross_revenue' => (float)$row['Monthly_Gross_Revenue']
                ];
            }
        }
    } elseif ($bucket === 'monthly_claim_volume') {
        $type = 'monthly_claim_volume';
        $title = "Monthly Claim Volume Breakdown (Month & Provider Level)";
        $sql = "SELECT 
            YEAR({$cd_expr}) AS Year,
            MONTH({$cd_expr}) AS Month_No,
            DATE_FORMAT({$cd_expr}, '%b %Y') AS Month_Year,
            COALESCE(NULLIF(rendering_provider, ''), 'Unassigned Provider') AS rendering_provider,
            COUNT(DISTINCT charge_id) AS Claim_Volume,
            ROUND(SUM(CAST(NULLIF(charge_amount, '') AS DECIMAL(15,2))), 2) AS Total_Charges
        FROM {$tbl_charges_created}
        WHERE created_date IS NOT NULL 
          AND created_date <> '0000-00-00'
        GROUP BY YEAR({$cd_expr}), MONTH({$cd_expr}), rendering_provider
        ORDER BY YEAR({$cd_expr}) DESC, MONTH({$cd_expr}) DESC, Claim_Volume DESC";

        $res = $conn->query($sql);
        $records = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $records[] = [
                    'year' => $row['Year'],
                    'month_year' => $row['Month_Year'],
                    'rendering_provider' => $row['rendering_provider'],
                    'claim_volume' => (int)$row['Claim_Volume'],
                    'total_charges' => (float)$row['Total_Charges']
                ];
            }
        }
    } else {
        if ($bucket === '0-30') {
            $where_clause = "CAST(NULLIF(ar_age_by_created_date, '') AS SIGNED) BETWEEN 0 AND 30";
            $title = "AR 0–30 Days Drill-Down Analytics";
        } elseif ($bucket === '31-60') {
            $where_clause = "CAST(NULLIF(ar_age_by_created_date, '') AS SIGNED) BETWEEN 31 AND 60";
            $title = "AR 31–60 Days Drill-Down Analytics";
        } elseif ($bucket === '61-90') {
            $where_clause = "CAST(NULLIF(ar_age_by_created_date, '') AS SIGNED) BETWEEN 61 AND 90";
            $title = "AR 61–90 Days Drill-Down Analytics";
        } elseif ($bucket === '91-120') {
            $where_clause = "CAST(NULLIF(ar_age_by_created_date, '') AS SIGNED) BETWEEN 91 AND 120";
            $title = "AR 91–120 Days Drill-Down Analytics";
        } elseif ($bucket === '120+') {
            $where_clause = "CAST(NULLIF(ar_age_by_created_date, '') AS SIGNED) > 120";
            $title = "AR 120+ Days Drill-Down Analytics";
        } else {
            $where_clause = "CAST(NULLIF(ar_age_by_created_date, '') AS SIGNED) BETWEEN 0 AND 30";
            $title = "AR 0–30 Days Drill-Down Analytics";
        }

        $sql = "SELECT 
            COALESCE(NULLIF(patient_name, ''), 'Unknown') AS patient_name,
            COALESCE(NULLIF(procedure_code, ''), 'N/A') AS procedure_code,
            date_of_service,
            CAST(NULLIF(ar_age_by_created_date, '') AS SIGNED) AS aging_days,
            ROUND(CAST(NULLIF(total_balance, '') AS DECIMAL(15,2)), 2) AS total_balance,
            ROUND(CAST(NULLIF(insurance_balance, '') AS DECIMAL(15,2)), 2) AS insurance_balance,
            ROUND(CAST(NULLIF(patient_balance, '') AS DECIMAL(15,2)), 2) AS patient_balance
        FROM {$tbl_ar}
        WHERE $where_clause
        ORDER BY total_balance DESC
        LIMIT 200";

        $res = $conn->query($sql);
        $records = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $records[] = [
                    'patient_name' => $row['patient_name'],
                    'procedure_code' => $row['procedure_code'],
                    'date_of_service' => $row['date_of_service'],
                    'aging_days' => (int)$row['aging_days'],
                    'total_balance' => (float)$row['total_balance'],
                    'insurance_balance' => (float)$row['insurance_balance'],
                    'patient_balance' => (float)$row['patient_balance']
                ];
            }
        }
    }

    echo json_encode([
        'status' => 'success',
        'type' => $type,
        'title' => $title,
        'bucket' => $bucket,
        'sql' => $sql,
        'total_count' => count($records),
        'data' => $records
    ], JSON_PRETTY_PRINT);
    $conn->close();
    exit;
}

// Complete Query Definitions in Requested Order
$queries = [
    // 1. Charges Count by Created Date
    'charges_count_created' => "SELECT
        DATE_FORMAT({$cd_expr}, '%b %Y') AS Month_Year,
        rendering_provider,
        COUNT(*) AS Total_Charges
    FROM {$tbl_charges_created}
    WHERE created_date IS NOT NULL
      AND created_date <> '0000-00-00'
    GROUP BY
        YEAR({$cd_expr}),
        MONTH({$cd_expr}),
        rendering_provider
    ORDER BY
        YEAR({$cd_expr}) ASC,
        MONTH({$cd_expr}) ASC,
        rendering_provider ASC",

    // 2. Charges $ Value by Created Date
    'charges_value_created' => "SELECT
        DATE_FORMAT({$cd_expr}, '%b %Y') AS Month_Year,
        rendering_provider,
        ROUND(SUM(charge_amount), 2) AS Total_Charge
    FROM {$tbl_charges_created}
    WHERE created_date IS NOT NULL
      AND created_date <> '0000-00-00'
    GROUP BY
        YEAR({$cd_expr}),
        MONTH({$cd_expr}),
        rendering_provider
    ORDER BY
        YEAR({$cd_expr}) ASC,
        MONTH({$cd_expr}) ASC,
        rendering_provider ASC",

    // 3. Location wise analysis by Created Date
    'charges_by_location_created' => "SELECT
    rendering_provider,
    location_name,

    COUNT(DISTINCT CASE WHEN MONTH({$cd_expr}) = 1 THEN charge_id END) AS Jan,
    COUNT(DISTINCT CASE WHEN MONTH({$cd_expr}) = 2 THEN charge_id END) AS Feb,
    COUNT(DISTINCT CASE WHEN MONTH({$cd_expr}) = 3 THEN charge_id END) AS Mar,
    COUNT(DISTINCT CASE WHEN MONTH({$cd_expr}) = 4 THEN charge_id END) AS Apr,
    COUNT(DISTINCT CASE WHEN MONTH({$cd_expr}) = 5 THEN charge_id END) AS May,
    COUNT(DISTINCT CASE WHEN MONTH({$cd_expr}) = 6 THEN charge_id END) AS Jun,
    COUNT(DISTINCT CASE WHEN MONTH({$cd_expr}) = 7 THEN charge_id END) AS Jul,
    COUNT(DISTINCT CASE WHEN MONTH({$cd_expr}) = 8 THEN charge_id END) AS Aug,
    COUNT(DISTINCT CASE WHEN MONTH({$cd_expr}) = 9 THEN charge_id END) AS Sep,
    COUNT(DISTINCT CASE WHEN MONTH({$cd_expr}) = 10 THEN charge_id END) AS Oct,
    COUNT(DISTINCT CASE WHEN MONTH({$cd_expr}) = 11 THEN charge_id END) AS Nov,
    COUNT(DISTINCT CASE WHEN MONTH({$cd_expr}) = 12 THEN charge_id END) AS `Dec`,
    COUNT(DISTINCT charge_id) AS Total

FROM {$tbl_charges_created}
WHERE created_date IS NOT NULL
  AND created_date <> '0000-00-00'
GROUP BY
    rendering_provider,
    location_name",

    // 4. Charges Count by Date of Service
    'charges_count_dos' => "SELECT
        DATE_FORMAT({$dos_expr}, '%b %Y') AS Month_Year,
        rendering_provider,
        COUNT(*) AS Total_Charges
    FROM {$tbl_charges_created}
    WHERE date_of_service IS NOT NULL
      AND date_of_service <> '0000-00-00'
    GROUP BY
        YEAR({$dos_expr}),
        MONTH({$dos_expr}),
        rendering_provider
    ORDER BY
        YEAR({$dos_expr}) ASC,
        MONTH({$dos_expr}) ASC,
        rendering_provider ASC",

    // 5. Charges $ Value by Date of Service
    'charges_value_dos' => "SELECT
        DATE_FORMAT({$dos_expr}, '%b %Y') AS Month_Year,
        rendering_provider,
        ROUND(SUM(charge_amount), 2) AS Total_Charge
    FROM {$tbl_charges_created}
    WHERE date_of_service IS NOT NULL
      AND date_of_service <> '0000-00-00'
    GROUP BY
        YEAR({$dos_expr}),
        MONTH({$dos_expr}),
        rendering_provider
    ORDER BY
        YEAR({$dos_expr}) ASC,
        MONTH({$dos_expr}) ASC,
        rendering_provider ASC",

    // 6. Location Wise Analysis by Date of Service
    'charges_by_location_dos' => "SELECT
    rendering_provider,
    location_name,

    COUNT(DISTINCT CASE WHEN MONTH({$dos_expr}) = 1 THEN charge_id END) AS Jan,
    COUNT(DISTINCT CASE WHEN MONTH({$dos_expr}) = 2 THEN charge_id END) AS Feb,
    COUNT(DISTINCT CASE WHEN MONTH({$dos_expr}) = 3 THEN charge_id END) AS Mar,
    COUNT(DISTINCT CASE WHEN MONTH({$dos_expr}) = 4 THEN charge_id END) AS Apr,
    COUNT(DISTINCT CASE WHEN MONTH({$dos_expr}) = 5 THEN charge_id END) AS May,
    COUNT(DISTINCT CASE WHEN MONTH({$dos_expr}) = 6 THEN charge_id END) AS Jun,
    COUNT(DISTINCT CASE WHEN MONTH({$dos_expr}) = 7 THEN charge_id END) AS Jul,
    COUNT(DISTINCT CASE WHEN MONTH({$dos_expr}) = 8 THEN charge_id END) AS Aug,
    COUNT(DISTINCT CASE WHEN MONTH({$dos_expr}) = 9 THEN charge_id END) AS Sep,
    COUNT(DISTINCT CASE WHEN MONTH({$dos_expr}) = 10 THEN charge_id END) AS Oct,
    COUNT(DISTINCT CASE WHEN MONTH({$dos_expr}) = 11 THEN charge_id END) AS Nov,
    COUNT(DISTINCT CASE WHEN MONTH({$dos_expr}) = 12 THEN charge_id END) AS `Dec`,
    COUNT(DISTINCT charge_id) AS Total

FROM {$tbl_charges_dos}
WHERE date_of_service IS NOT NULL
  AND date_of_service <> '0000-00-00'
GROUP BY
    rendering_provider,
    location_name",

    // 7. Insurance Payment Analysis month wise
    'insurance_payments' => "SELECT
        rendering_provider,
        YEAR({$ledger_expr}) AS Year,
        MONTH({$ledger_expr}) AS Month_No,
        DATE_FORMAT({$ledger_expr}, '%b %Y') AS Month_Year,
        ROUND(SUM(insurance_payment), 2) AS Total_Insurance_Payment
    FROM {$tbl_tx_grid}
    WHERE ledger_date IS NOT NULL
      AND ledger_date <> '0000-00-00'
    GROUP BY
        rendering_provider,
        YEAR({$ledger_expr}),
        MONTH({$ledger_expr})
    ORDER BY
        Year ASC,
        Month_No ASC,
        rendering_provider ASC",

    // 8. Patient Payment Analysis month wise
    'patient_payments' => "SELECT
        YEAR({$cd_expr}) AS Year,
        MONTH({$cd_expr}) AS Month_No,
        DATE_FORMAT({$cd_expr}, '%b %Y') AS Month_Year,
        ROUND(SUM(collected_amount), 2) AS Total_Patient_Payment
    FROM {$tbl_patient_pmt}
    WHERE created_date IS NOT NULL
      AND created_date <> '0000-00-00'
    GROUP BY YEAR({$cd_expr}), MONTH({$cd_expr})
    ORDER BY Year ASC, Month_No ASC",

    // 9. Total Collections Analysis month wise
    'total_collections_monthwise' => "SELECT *
FROM (
    SELECT
        COALESCE(p.Year, i.Year) AS Year,
        COALESCE(p.Month_No, i.Month_No) AS Month_No,
        DATE_FORMAT(
            STR_TO_DATE(CONCAT(COALESCE(p.Year, i.Year), '-', LPAD(COALESCE(p.Month_No, i.Month_No), 2, '0'), '-01'), '%Y-%m-%d'),
            '%b %Y'
        ) AS Month_Year,

        ROUND(IFNULL(p.Patient_Payment, 0), 2) AS Patient_Payment,
        ROUND(IFNULL(i.Insurance_Payment, 0), 2) AS Insurance_Payment,
        ROUND(IFNULL(p.Patient_Payment, 0) + IFNULL(i.Insurance_Payment, 0), 2) AS Total_Collection

    FROM
    (
        SELECT
            YEAR({$cd_expr}) AS Year,
            MONTH({$cd_expr}) AS Month_No,
            SUM(collected_amount) AS Patient_Payment
        FROM {$tbl_patient_pmt}
        WHERE created_date IS NOT NULL AND created_date <> ''
        GROUP BY YEAR({$cd_expr}), MONTH({$cd_expr})
    ) p

    LEFT JOIN
    (
        SELECT
            YEAR({$cd_expr}) AS Year,
            MONTH({$cd_expr}) AS Month_No,
            SUM(insurance_payment) AS Insurance_Payment
        FROM {$tbl_tx_grid}
        WHERE created_date IS NOT NULL AND created_date <> ''
        GROUP BY YEAR({$cd_expr}), MONTH({$cd_expr})
    ) i
    ON p.Year = i.Year
    AND p.Month_No = i.Month_No

    UNION

    SELECT
        i.Year,
        i.Month_No,
        DATE_FORMAT(
            STR_TO_DATE(CONCAT(i.Year, '-', LPAD(i.Month_No, 2, '0'), '-01'), '%Y-%m-%d'),
            '%b %Y'
        ) AS Month_Year,

        ROUND(0, 2) AS Patient_Payment,
        ROUND(i.Insurance_Payment, 2) AS Insurance_Payment,
        ROUND(i.Insurance_Payment, 2) AS Total_Collection

    FROM
    (
        SELECT
            YEAR({$cd_expr}) AS Year,
            MONTH({$cd_expr}) AS Month_No,
            SUM(insurance_payment) AS Insurance_Payment
        FROM {$tbl_tx_grid}
        WHERE created_date IS NOT NULL AND created_date <> ''
        GROUP BY YEAR({$cd_expr}), MONTH({$cd_expr})
    ) i

    LEFT JOIN
    (
        SELECT
            YEAR({$cd_expr}) AS Year,
            MONTH({$cd_expr}) AS Month_No
        FROM {$tbl_patient_pmt}
        WHERE created_date IS NOT NULL AND created_date <> ''
        GROUP BY YEAR({$cd_expr}), MONTH({$cd_expr})
    ) p
    ON p.Year = i.Year
    AND p.Month_No = i.Month_No

    WHERE p.Year IS NULL
) AS MonthlyReport

UNION ALL

SELECT
    NULL AS Year,
    NULL AS Month_No,
    'Grand Total' AS Month_Year,
    ROUND(SUM(Patient_Payment), 2) AS Patient_Payment,
    ROUND(SUM(Insurance_Payment), 2) AS Insurance_Payment,
    ROUND(SUM(Total_Collection), 2) AS Total_Collection
FROM (
    SELECT
        COALESCE(p.Year, i.Year) AS Year,
        COALESCE(p.Month_No, i.Month_No) AS Month_No,
        ROUND(IFNULL(p.Patient_Payment, 0), 2) AS Patient_Payment,
        ROUND(IFNULL(i.Insurance_Payment, 0), 2) AS Insurance_Payment,
        ROUND(IFNULL(p.Patient_Payment, 0) + IFNULL(i.Insurance_Payment, 0), 2) AS Total_Collection

    FROM
    (
        SELECT
            YEAR({$cd_expr}) AS Year,
            MONTH({$cd_expr}) AS Month_No,
            SUM(collected_amount) AS Patient_Payment
        FROM {$tbl_patient_pmt}
        WHERE created_date IS NOT NULL AND created_date <> ''
        GROUP BY YEAR({$cd_expr}), MONTH({$cd_expr})
    ) p

    LEFT JOIN
    (
        SELECT
            YEAR({$cd_expr}) AS Year,
            MONTH({$cd_expr}) AS Month_No,
            SUM(insurance_payment) AS Insurance_Payment
        FROM {$tbl_tx_grid}
        WHERE created_date IS NOT NULL AND created_date <> ''
        GROUP BY YEAR({$cd_expr}), MONTH({$cd_expr})
    ) i
    ON p.Year = i.Year
    AND p.Month_No = i.Month_No

    UNION

    SELECT
        i.Year,
        i.Month_No,
        0,
        ROUND(i.Insurance_Payment, 2),
        ROUND(i.Insurance_Payment, 2)
    FROM
    (
        SELECT
            YEAR({$cd_expr}) AS Year,
            MONTH({$cd_expr}) AS Month_No,
            SUM(insurance_payment) AS Insurance_Payment
        FROM {$tbl_tx_grid}
        WHERE created_date IS NOT NULL AND created_date <> ''
        GROUP BY YEAR({$cd_expr}), MONTH({$cd_expr})
    ) i
    LEFT JOIN
    (
        SELECT
            YEAR({$cd_expr}) AS Year,
            MONTH({$cd_expr}) AS Month_No
        FROM {$tbl_patient_pmt}
        WHERE created_date IS NOT NULL AND created_date <> ''
        GROUP BY YEAR({$cd_expr}), MONTH({$cd_expr})
    ) p
    ON p.Year = i.Year
    AND p.Month_No = i.Month_No
    WHERE p.Year IS NULL
) AS Totals

ORDER BY
    CASE WHEN Year IS NULL THEN 1 ELSE 0 END,
    Year,
    Month_No",

    // 10. Top 10 CPT Codes Analysis (from transaction grid)
    'top_10_cpt_codes' => "SELECT
        COALESCE(NULLIF(procedure_code, ''), 'N/A') AS CPT_Code,
        COUNT(DISTINCT claim_id) AS Total_Units,
        ROUND(SUM(CAST(NULLIF(charge, '') AS DECIMAL(15,2))), 2) AS Total_Charges,
        ROUND(SUM(insurance_payment), 2) AS Insurance_Paid,
        ROUND(SUM(patient_payment), 2) AS Patient_Paid,
        ROUND(SUM(insurance_payment + patient_payment), 2) AS Total_Collections
    FROM {$tbl_tx_grid}
    WHERE procedure_code IS NOT NULL AND procedure_code <> ''
    GROUP BY procedure_code
    ORDER BY Insurance_Paid DESC
    LIMIT 10",

    // 11. Top 10 Insurance Payers (from transaction grid)
    'top_10_payers' => "SELECT
        COALESCE(NULLIF(payer_name, ''), 'Self-Pay / Unassigned') AS Payer_Name,
        COUNT(DISTINCT claim_id) AS Total_Claims,
        ROUND(SUM(CAST(NULLIF(charge, '') AS DECIMAL(15,2))), 2) AS Total_Charges,
        ROUND(SUM(insurance_payment), 2) AS Insurance_Payment,
        ROUND(SUM(patient_payment), 2) AS Patient_Payment,
        ROUND(SUM(insurance_payment + patient_payment), 2) AS Total_Collections
    FROM {$tbl_tx_grid}
    WHERE payer_name IS NOT NULL AND payer_name <> ''
    GROUP BY payer_name
    ORDER BY Insurance_Payment DESC
    LIMIT 10",

    // 12. Outstanding A/R
    'outstanding_ar' => "SELECT
        'Current Outstanding A/R' AS Category,
        ROUND(SUM(CAST(NULLIF(total_balance, '') AS DECIMAL(15,2))), 2) AS Total_AR,
        ROUND(SUM(CAST(NULLIF(insurance_balance, '') AS DECIMAL(15,2))), 2) AS Insurance_AR,
        ROUND(SUM(CAST(NULLIF(patient_balance, '') AS DECIMAL(15,2))), 2) AS Patient_AR,
        ROUND(SUM(CASE WHEN CAST(NULLIF(ar_age_by_created_date, '') AS SIGNED) BETWEEN 0 AND 30 THEN CAST(NULLIF(total_balance, '') AS DECIMAL(15,2)) ELSE 0 END), 2) AS AR_0_30,
        ROUND(SUM(CASE WHEN CAST(NULLIF(ar_age_by_created_date, '') AS SIGNED) BETWEEN 31 AND 60 THEN CAST(NULLIF(total_balance, '') AS DECIMAL(15,2)) ELSE 0 END), 2) AS AR_31_60,
        ROUND(SUM(CASE WHEN CAST(NULLIF(ar_age_by_created_date, '') AS SIGNED) BETWEEN 61 AND 90 THEN CAST(NULLIF(total_balance, '') AS DECIMAL(15,2)) ELSE 0 END), 2) AS AR_61_90,
        ROUND(SUM(CASE WHEN CAST(NULLIF(ar_age_by_created_date, '') AS SIGNED) BETWEEN 91 AND 120 THEN CAST(NULLIF(total_balance, '') AS DECIMAL(15,2)) ELSE 0 END), 2) AS AR_91_120,
        ROUND(SUM(CASE WHEN CAST(NULLIF(ar_age_by_created_date, '') AS SIGNED) > 120 THEN CAST(NULLIF(total_balance, '') AS DECIMAL(15,2)) ELSE 0 END), 2) AS AR_120_Plus
    FROM {$tbl_ar}",

    // Legacy alias backwards compatibility
    'charges_count_dos_tbl' => "SELECT YEAR({$dos_expr}) AS Year, MONTH({$dos_expr}) AS Month_No, DATE_FORMAT({$dos_expr}, '%b %Y') AS Month_Year, COUNT(*) AS Total_Charges FROM {$tbl_charges_dos} WHERE date_of_service IS NOT NULL AND date_of_service <> '0000-00-00' GROUP BY YEAR({$dos_expr}), MONTH({$dos_expr}) ORDER BY YEAR({$dos_expr}), MONTH({$dos_expr})",
    'charges_value_dos_tbl' => "SELECT YEAR({$dos_expr}) AS Year, MONTH({$dos_expr}) AS Month_No, DATE_FORMAT({$dos_expr}, '%b %Y') AS Month_Year, ROUND(SUM(charge_amount), 2) AS Total_Charge FROM {$tbl_charges_dos} WHERE date_of_service IS NOT NULL AND date_of_service <> '0000-00-00' GROUP BY YEAR({$dos_expr}), MONTH({$dos_expr}) ORDER BY YEAR({$dos_expr}), MONTH({$dos_expr})",
    'charges_by_location' => "SELECT location_name AS `Row Labels`, COUNT(DISTINCT CASE WHEN MONTH({$cd_expr}) = 1 THEN charge_id END) AS Jan, COUNT(DISTINCT CASE WHEN MONTH({$cd_expr}) = 2 THEN charge_id END) AS Feb, COUNT(DISTINCT CASE WHEN MONTH({$cd_expr}) = 3 THEN charge_id END) AS Mar, COUNT(DISTINCT CASE WHEN MONTH({$cd_expr}) = 4 THEN charge_id END) AS Apr, COUNT(DISTINCT CASE WHEN MONTH({$cd_expr}) = 5 THEN charge_id END) AS May, COUNT(DISTINCT CASE WHEN MONTH({$cd_expr}) = 6 THEN charge_id END) AS Jun, COUNT(DISTINCT CASE WHEN MONTH({$cd_expr}) = 7 THEN charge_id END) AS Jul, COUNT(DISTINCT CASE WHEN MONTH({$cd_expr}) = 8 THEN charge_id END) AS Aug, COUNT(DISTINCT CASE WHEN MONTH({$cd_expr}) = 9 THEN charge_id END) AS Sep, COUNT(DISTINCT CASE WHEN MONTH({$cd_expr}) = 10 THEN charge_id END) AS Oct, COUNT(DISTINCT CASE WHEN MONTH({$cd_expr}) = 11 THEN charge_id END) AS Nov, COUNT(DISTINCT CASE WHEN MONTH({$cd_expr}) = 12 THEN charge_id END) AS `Dec`, COUNT(DISTINCT charge_id) AS `Grand Total` FROM {$tbl_charges_created} WHERE created_date IS NOT NULL AND created_date <> '0000-00-00' GROUP BY location_name ORDER BY `Grand Total` DESC",
    'charges_by_dos' => "SELECT YEAR({$dos_expr}) AS Year, MONTH({$dos_expr}) AS Month_No, DATE_FORMAT({$dos_expr}, '%b %Y') AS Month_Year, ROUND(SUM(charge_amount), 2) AS Total_Charge FROM {$tbl_charges_created} WHERE date_of_service IS NOT NULL AND date_of_service <> '0000-00-00' GROUP BY YEAR({$dos_expr}), MONTH({$dos_expr}) ORDER BY YEAR({$dos_expr}), MONTH({$dos_expr})",
    'appointment_analysis' => "SELECT YEAR({$dos_expr}) AS year, MONTH({$dos_expr}) AS month, COUNT(*) AS total_records FROM {$tbl_appt} WHERE appointment_status IN ('Checked In', 'Checked Out', 'Confirmed', 'New') GROUP BY YEAR({$dos_expr}), MONTH({$dos_expr}) ORDER BY YEAR({$dos_expr}), MONTH({$dos_expr})",
    'appt_level1' => "SELECT YEAR({$dos_expr}) AS Year, MONTH({$dos_expr}) AS Month_No, DATE_FORMAT({$dos_expr},'%b %Y') AS Month_Year, COUNT(*) AS Total_Appointments FROM {$tbl_appt} WHERE appointment_status IN ('Checked In', 'Checked Out', 'Confirmed', 'New') GROUP BY YEAR({$dos_expr}), MONTH({$dos_expr}) ORDER BY Year, Month_No",
    'appt_level2' => "SELECT YEAR({$dos_expr}) AS Year, MONTH({$dos_expr}) AS Month_No, DATE_FORMAT({$dos_expr},'%b %Y') AS Month_Year, appointment_type, COUNT(*) AS Total_Appointments FROM {$tbl_appt} WHERE appointment_status IN ('Checked In', 'Checked Out', 'Confirmed', 'New') GROUP BY YEAR({$dos_expr}), MONTH({$dos_expr}), appointment_type ORDER BY Year, Month_No, Total_Appointments DESC",
    'appt_level3' => "SELECT YEAR({$dos_expr}) AS Year, MONTH({$dos_expr}) AS Month_No, DATE_FORMAT({$dos_expr},'%b %Y') AS Month_Year, appointment_type, patient_name, COUNT(*) AS Appointments, ROUND(SUM(co_pay), 2) AS Total_Copay, ROUND(AVG(co_pay), 2) AS Avg_Copay, ROUND(MAX(co_pay), 2) AS Highest_Copay FROM {$tbl_appt} WHERE appointment_status IN ('Checked In', 'Checked Out', 'Confirmed', 'New') GROUP BY YEAR({$dos_expr}), MONTH({$dos_expr}), appointment_type, patient_name ORDER BY Total_Copay DESC",
    'appt_level4' => "SELECT YEAR({$dos_expr}) AS Year, MONTH({$dos_expr}) AS Month_No, DATE_FORMAT({$dos_expr},'%b %Y') AS Month_Year, practice_name, COUNT(*) AS Total_Appointments FROM {$tbl_appt} WHERE appointment_status IN ('Checked In', 'Checked Out', 'Confirmed', 'New') GROUP BY YEAR({$dos_expr}), MONTH({$dos_expr}), practice_name ORDER BY Year, Month_No, Total_Appointments DESC",
    'appt_level5' => "SELECT YEAR({$dos_expr}) AS Year, MONTH({$dos_expr}) AS Month_No, DATE_FORMAT({$dos_expr},'%b %Y') AS Month_Year, practice_name, location_name, COUNT(*) AS Total_Appointments FROM {$tbl_appt} WHERE appointment_status IN ('Checked In', 'Checked Out', 'Confirmed', 'New') GROUP BY YEAR({$dos_expr}), MONTH({$dos_expr}), practice_name, location_name ORDER BY Year, Month_No, Total_Appointments DESC",
    'missing_charges' => "SELECT location_name, YEAR({$appt_expr}) AS Year, MONTH({$appt_expr}) AS Month_No, DATE_FORMAT({$appt_expr}, '%b %Y') AS Month_Year, COUNT(*) AS Total_Missing_Charges FROM {$tbl_missing} WHERE appointment_date IS NOT NULL AND appointment_date <> '0000-00-00' AND non_charge = '' AND possible_match = '' GROUP BY location_name, YEAR({$appt_expr}), MONTH({$appt_expr}) ORDER BY Year ASC, Month_No ASC, location_name ASC",
    'monthly_adjustments' => "SELECT MONTHNAME({$cd_expr}) AS month_name, MONTH({$cd_expr}) AS month_number, ROUND(SUM(adjustment)) AS total_adjustment FROM {$tbl_tx_grid} GROUP BY MONTH({$cd_expr}), MONTHNAME({$cd_expr}) ORDER BY month_number"
];

$response = [
    'status' => 'success',
    'timestamp' => date('Y-m-d H:i:s'),
    'data' => []
];

foreach ($queries as $key => $sql) {
    $res = $conn->query($sql);
    $rows = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            foreach ($r as $col => $val) {
                if (is_numeric($val) && strpos($col, 'Year') === false && strpos($col, 'year') === false && strpos($col, 'Month') === false && strpos($col, 'month') === false) {
                    $r[$col] = (float)$val == (int)$val ? (int)$val : (float)$val;
                } elseif (in_array($col, ['Year', 'year', 'Month_No', 'month', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Grand Total', 'Total', 'Total_Charges', 'total_records', 'Total_Records', 'Total_Appointments', 'Appointments'])) {
                    $r[$col] = (int)$val;
                }
            }
            $rows[] = $r;
        }
    }
    $response['data'][$key] = $rows;
}

// Aggregate summary metrics
$total_charges_cnt = 0;
foreach ($response['data']['charges_count_created'] as $r) {
    $total_charges_cnt += (int)($r['Total_Charges'] ?? 0);
}

$total_charges_amt = 0;
foreach ($response['data']['charges_value_created'] as $r) {
    $total_charges_amt += (float)($r['Total_Charge'] ?? 0);
}

$total_dos_amt = 0;
$dos_value_arr = $response['data']['charges_value_dos'] ?? $response['data']['charges_value_dos_tbl'] ?? [];
foreach ($dos_value_arr as $r) {
    $total_dos_amt += (float)($r['Total_Charge'] ?? 0);
}

$total_dos_cnt = 0;
$dos_cnt_arr = $response['data']['charges_count_dos'] ?? $response['data']['charges_count_dos_tbl'] ?? [];
foreach ($dos_cnt_arr as $r) {
    $total_dos_cnt += (int)($r['Total_Charges'] ?? 0);
}

$total_patient_pmt = 0;
foreach ($response['data']['patient_payments'] as $r) {
    $total_patient_pmt += (float)($r['Total_Patient_Payment'] ?? 0);
}

$total_insurance_pmt = 0;
foreach ($response['data']['insurance_payments'] as $r) {
    $total_insurance_pmt += (float)($r['Total_Insurance_Payment'] ?? 0);
}

$total_missing_charges = 0;
foreach ($response['data']['missing_charges'] as $r) {
    $total_missing_charges += (int)($r['Total_Missing_Charges'] ?? $r['Total_Records'] ?? 0);
}

$total_appointments = 0;
$appts_arr = $response['data']['appt_level1'] ?? $response['data']['appointment_analysis'] ?? [];
foreach ($appts_arr as $r) {
    $total_appointments += (int)($r['Total_Appointments'] ?? $r['total_records'] ?? 0);
}

$sql_exec = "SELECT
    -- Total Charges
    (SELECT ROUND(IFNULL(SUM(charge_amount),0),2)
     FROM {$tbl_charges_created}
    ) AS Total_Charges,
 
    -- Insurance Payment
    (SELECT ROUND(IFNULL(SUM(insurance_payment),0),2)
     FROM {$tbl_tx_grid}
    ) AS Insurance_Payment,
 
    -- Patient Payment
    (SELECT ROUND(IFNULL(SUM(collected_amount),0),2)
     FROM {$tbl_patient_pmt}
    ) AS Patient_Payment,
 
    -- Patient Balance
    (SELECT ROUND(IFNULL(SUM(CAST(NULLIF(patient_balance, '') AS DECIMAL(15,2))), 0), 2)
     FROM {$tbl_ar}
    ) AS Patient_Balance,

    -- Adjustment
    (SELECT ROUND(IFNULL(SUM(adjustment),0),2)
     FROM {$tbl_tx_grid}
    ) AS Adjustment,

    -- Adjustment Count
    (SELECT COUNT(*)
     FROM {$tbl_tx_grid}
     WHERE adjustment IS NOT NULL AND adjustment <> 0
    ) AS Adjustment_Count,
 
    -- Write Off
    (SELECT ROUND(IFNULL(SUM(write_off),0),2)
     FROM {$tbl_tx_grid}
    ) AS Write_Off,
 
    -- Insurance AR
    (SELECT ROUND(IFNULL(SUM(insurance_balance),0),2)
     FROM {$tbl_ar}
    ) AS Insurance_AR,
 
    -- Total Collections
    ROUND(
        (
            (SELECT IFNULL(SUM(insurance_payment),0)
             FROM {$tbl_tx_grid})
            +
            (SELECT IFNULL(SUM(collected_amount),0)
             FROM {$tbl_patient_pmt})
        ),
        2
    ) AS Total_Collections,

    -- Annual Gross Revenue (Total Collections / 12)
    ROUND(
        (
            (SELECT IFNULL(SUM(insurance_payment),0) FROM {$tbl_tx_grid})
            +
            (SELECT IFNULL(SUM(collected_amount),0) FROM {$tbl_patient_pmt})
        ) / 12,
        2
    ) AS Annual_Gross_Revenue,
 
    -- Number of DOS
    (
        SELECT COUNT(DISTINCT date_of_service)
        FROM {$tbl_charges_created}
    ) AS Number_of_DOS,
 
    -- Average Daily Charges
    ROUND(
        (
            SELECT IFNULL(SUM(charge_amount),0)
            FROM {$tbl_charges_created}
        )
        /
        NULLIF(
            (
                SELECT COUNT(DISTINCT date_of_service)
                FROM {$tbl_charges_created}
            ),
            0
        ),
        2
    ) AS Average_Daily_Charges,
 
    -- Collection Rate
    ROUND(
        (
            (
                (SELECT IFNULL(SUM(insurance_payment),0)
                 FROM {$tbl_tx_grid})
                +
                (SELECT IFNULL(SUM(collected_amount),0)
                 FROM {$tbl_patient_pmt})
            )
            /
            NULLIF(
                (
                    SELECT IFNULL(SUM(charge_amount),0)
                    FROM {$tbl_charges_created}
                ),
                0
            )
        ) * 100,
        2
    ) AS Collection_Rate,

    -- Net Collection Ratio = ((Insurance Payment + Patient Payment + Patient Balance) / (Total Charges - Adjustment)) * 100
    ROUND(
        (
            (
                (SELECT IFNULL(SUM(insurance_payment),0) FROM {$tbl_tx_grid})
                +
                (SELECT IFNULL(SUM(collected_amount),0) FROM {$tbl_patient_pmt})
                +
                (SELECT IFNULL(SUM(CAST(NULLIF(patient_balance, '') AS DECIMAL(15,2))), 0) FROM {$tbl_ar})
            )
            /
            NULLIF(
                (
                    (SELECT IFNULL(SUM(charge_amount),0) FROM {$tbl_charges_created})
                    -
                    (SELECT IFNULL(SUM(adjustment),0) FROM {$tbl_tx_grid})
                ),
                0
            )
        ) * 100,
        2
    ) AS Net_Collection_Ratio,

    -- AR Days = Insurance AR / Average Daily Charges
    ROUND(
        (SELECT IFNULL(SUM(insurance_balance),0) FROM {$tbl_ar})
        /
        NULLIF(
            (
                (SELECT IFNULL(SUM(charge_amount),0) FROM {$tbl_charges_created})
                /
                NULLIF((SELECT COUNT(DISTINCT date_of_service) FROM {$tbl_charges_created}), 0)
            ),
            0
        ),
        1
    ) AS AR_Days,

    -- AR 0-30 Days Amount & %
    (SELECT ROUND(IFNULL(SUM(CASE WHEN CAST(NULLIF(ar_age_by_created_date, '') AS SIGNED) BETWEEN 0 AND 30 THEN CAST(NULLIF(total_balance, '') AS DECIMAL(15,2)) ELSE 0 END), 0), 2) FROM {$tbl_ar}) AS AR_0_30_Amount,
    ROUND(((SELECT IFNULL(SUM(CASE WHEN CAST(NULLIF(ar_age_by_created_date, '') AS SIGNED) BETWEEN 0 AND 30 THEN CAST(NULLIF(total_balance, '') AS DECIMAL(15,2)) ELSE 0 END), 0) FROM {$tbl_ar}) / NULLIF((SELECT IFNULL(SUM(insurance_balance), 0) FROM {$tbl_ar}), 0)) * 100, 2) AS AR_0_30_Percent,

    -- AR 31-60 Days Amount & %
    (SELECT ROUND(IFNULL(SUM(CASE WHEN CAST(NULLIF(ar_age_by_created_date, '') AS SIGNED) BETWEEN 31 AND 60 THEN CAST(NULLIF(total_balance, '') AS DECIMAL(15,2)) ELSE 0 END), 0), 2) FROM {$tbl_ar}) AS AR_31_60_Amount,
    ROUND(((SELECT IFNULL(SUM(CASE WHEN CAST(NULLIF(ar_age_by_created_date, '') AS SIGNED) BETWEEN 31 AND 60 THEN CAST(NULLIF(total_balance, '') AS DECIMAL(15,2)) ELSE 0 END), 0) FROM {$tbl_ar}) / NULLIF((SELECT IFNULL(SUM(insurance_balance), 0) FROM {$tbl_ar}), 0)) * 100, 2) AS AR_31_60_Percent,

    -- AR 61-90 Days Amount & %
    (SELECT ROUND(IFNULL(SUM(CASE WHEN CAST(NULLIF(ar_age_by_created_date, '') AS SIGNED) BETWEEN 61 AND 90 THEN CAST(NULLIF(total_balance, '') AS DECIMAL(15,2)) ELSE 0 END), 0), 2) FROM {$tbl_ar}) AS AR_61_90_Amount,
    ROUND(((SELECT IFNULL(SUM(CASE WHEN CAST(NULLIF(ar_age_by_created_date, '') AS SIGNED) BETWEEN 61 AND 90 THEN CAST(NULLIF(total_balance, '') AS DECIMAL(15,2)) ELSE 0 END), 0) FROM {$tbl_ar}) / NULLIF((SELECT IFNULL(SUM(insurance_balance), 0) FROM {$tbl_ar}), 0)) * 100, 2) AS AR_61_90_Percent,

    -- AR 31-90 Days Amount & %
    (SELECT ROUND(IFNULL(SUM(CASE WHEN CAST(NULLIF(ar_age_by_created_date, '') AS SIGNED) BETWEEN 31 AND 90 THEN CAST(NULLIF(total_balance, '') AS DECIMAL(15,2)) ELSE 0 END), 0), 2) FROM {$tbl_ar}) AS AR_31_90_Amount,
    ROUND(((SELECT IFNULL(SUM(CASE WHEN CAST(NULLIF(ar_age_by_created_date, '') AS SIGNED) BETWEEN 31 AND 90 THEN CAST(NULLIF(total_balance, '') AS DECIMAL(15,2)) ELSE 0 END), 0) FROM {$tbl_ar}) / NULLIF((SELECT IFNULL(SUM(insurance_balance), 0) FROM {$tbl_ar}), 0)) * 100, 2) AS AR_31_90_Percent,

    -- AR 91-120 Days Amount & %
    (SELECT ROUND(IFNULL(SUM(CASE WHEN CAST(NULLIF(ar_age_by_created_date, '') AS SIGNED) BETWEEN 91 AND 120 THEN CAST(NULLIF(total_balance, '') AS DECIMAL(15,2)) ELSE 0 END), 0), 2) FROM {$tbl_ar}) AS AR_91_120_Amount,
    ROUND(((SELECT IFNULL(SUM(CASE WHEN CAST(NULLIF(ar_age_by_created_date, '') AS SIGNED) BETWEEN 91 AND 120 THEN CAST(NULLIF(total_balance, '') AS DECIMAL(15,2)) ELSE 0 END), 0) FROM {$tbl_ar}) / NULLIF((SELECT IFNULL(SUM(insurance_balance), 0) FROM {$tbl_ar}), 0)) * 100, 2) AS AR_91_120_Percent,

    -- AR 120+ Days Amount & %
    (SELECT ROUND(IFNULL(SUM(CASE WHEN CAST(NULLIF(ar_age_by_created_date, '') AS SIGNED) > 120 THEN CAST(NULLIF(total_balance, '') AS DECIMAL(15,2)) ELSE 0 END), 0), 2)
     FROM {$tbl_ar}
    ) AS AR_120_Plus_Amount,

    ROUND(
        (
            (SELECT IFNULL(SUM(CASE WHEN CAST(NULLIF(ar_age_by_created_date, '') AS SIGNED) > 120 THEN CAST(NULLIF(total_balance, '') AS DECIMAL(15,2)) ELSE 0 END), 0) FROM {$tbl_ar})
            /
            NULLIF((SELECT IFNULL(SUM(insurance_balance), 0) FROM {$tbl_ar}), 0)
        ) * 100,
        2
    ) AS AR_120_Percent,
 
    -- Denied Claims
    (
        SELECT COUNT(DISTINCT charge_id)
        FROM {$tbl_ar}
        WHERE insurance_balance <> 0
          AND insurance_payment = 0
          AND DATEDIFF(CURDATE(), {$dos_expr}) >= 60
    ) AS Denied_Claims,
 
    -- Total Claims Submitted
    (
        SELECT COUNT(DISTINCT charge_id)
        FROM {$tbl_charges_created}
    ) AS Total_Claims_Submitted,

    -- Current Monthly Claim Volume
    (
        SELECT COUNT(DISTINCT charge_id)
        FROM {$tbl_charges_created}
        WHERE created_date IS NOT NULL AND created_date <> '0000-00-00'
          AND DATE_FORMAT({$cd_expr}, '%Y-%m') = (
              SELECT DATE_FORMAT(MAX({$cd_expr}), '%Y-%m') 
              FROM {$tbl_charges_created} 
              WHERE created_date IS NOT NULL AND created_date <> '0000-00-00'
          )
    ) AS Current_Monthly_Claim_Volume,
 
    -- Denial Rate
    ROUND(
        (
            (
                SELECT COUNT(DISTINCT charge_id)
                FROM {$tbl_ar}
                WHERE insurance_balance <> 0
                  AND insurance_payment = 0
                  AND DATEDIFF(CURDATE(), {$dos_expr}) >= 60
            )
            /
            NULLIF(
                (
                    SELECT COUNT(DISTINCT charge_id)
                    FROM {$tbl_charges_created}
                ),
                0
            )
        ) * 100,
        2
    ) AS Denial_Rate,
 
    -- Claims Paid on First Submission
    (
        SELECT COUNT(DISTINCT charge_id)
        FROM {$tbl_ar_not_use}
        WHERE date_of_service IS NOT NULL
          AND created_date IS NOT NULL
          AND DATEDIFF({$cd_expr}, {$dos_expr}) <= 30
          AND insurance_payment > 0
    ) AS Claims_Paid_On_First_Submission,
 
    -- Avg Charge Lag (Days)
    (SELECT ROUND(AVG(DATEDIFF({$cd_expr}, {$dos_expr})), 1)
     FROM {$tbl_charges_created}
     WHERE created_date IS NOT NULL 
       AND date_of_service IS NOT NULL 
       AND created_date <> '0000-00-00' 
       AND date_of_service <> '0000-00-00'
       AND {$cd_expr} >= {$dos_expr}
    ) AS Avg_Charge_Lag,

    -- First Pass Resolution Rate (FPRR)
    ROUND(
        (
            (
                SELECT COUNT(DISTINCT charge_id)
                FROM {$tbl_ar_not_use}
                WHERE date_of_service IS NOT NULL
                  AND created_date IS NOT NULL
                  AND DATEDIFF({$cd_expr}, {$dos_expr}) <= 30
                  AND insurance_payment > 0
            )
            /
            NULLIF(
                (
                    SELECT COUNT(DISTINCT charge_id)
                    FROM {$tbl_charges_created}
                ),
                0
            )
        ) * 100,
        2
    ) AS First_Pass_Resolution_Rate";

$res_exec = $conn->query($sql_exec);
$exec_row = $res_exec ? $res_exec->fetch_assoc() : [];

$response['executive_metrics'] = [
    'Total_Charges' => (float)($exec_row['Total_Charges'] ?? 0),
    'Insurance_Payment' => (float)($exec_row['Insurance_Payment'] ?? 0),
    'Patient_Payment' => (float)($exec_row['Patient_Payment'] ?? 0),
    'Patient_Balance' => (float)($exec_row['Patient_Balance'] ?? 0),
    'Adjustment' => (float)($exec_row['Adjustment'] ?? 0),
    'Adjustment_Count' => (int)($exec_row['Adjustment_Count'] ?? 0),
    'Write_Off' => (float)($exec_row['Write_Off'] ?? 0),
    'Insurance_AR' => (float)($exec_row['Insurance_AR'] ?? 0),
    'Total_Collections' => (float)($exec_row['Total_Collections'] ?? 0),
    'Annual_Gross_Revenue' => (float)($exec_row['Annual_Gross_Revenue'] ?? 0),
    'Number_of_DOS' => (int)($exec_row['Number_of_DOS'] ?? 0),
    'Average_Daily_Charges' => (float)($exec_row['Average_Daily_Charges'] ?? 0),
    'Collection_Rate' => (float)($exec_row['Collection_Rate'] ?? 0),
    'Net_Collection_Ratio' => (float)($exec_row['Net_Collection_Ratio'] ?? 0),
    'AR_Days' => (float)($exec_row['AR_Days'] ?? 0),
    'Avg_Charge_Lag' => (float)($exec_row['Avg_Charge_Lag'] ?? 0),
    'AR_0_30_Amount' => (float)($exec_row['AR_0_30_Amount'] ?? 0),
    'AR_0_30_Percent' => (float)($exec_row['AR_0_30_Percent'] ?? 0),
    'AR_31_60_Amount' => (float)($exec_row['AR_31_60_Amount'] ?? 0),
    'AR_31_60_Percent' => (float)($exec_row['AR_31_60_Percent'] ?? 0),
    'AR_61_90_Amount' => (float)($exec_row['AR_61_90_Amount'] ?? 0),
    'AR_61_90_Percent' => (float)($exec_row['AR_61_90_Percent'] ?? 0),
    'AR_31_90_Amount' => (float)($exec_row['AR_31_90_Amount'] ?? 0),
    'AR_31_90_Percent' => (float)($exec_row['AR_31_90_Percent'] ?? 0),
    'AR_91_120_Amount' => (float)($exec_row['AR_91_120_Amount'] ?? 0),
    'AR_91_120_Percent' => (float)($exec_row['AR_91_120_Percent'] ?? 0),
    'AR_120_Plus_Amount' => (float)($exec_row['AR_120_Plus_Amount'] ?? 0),
    'AR_120_Percent' => (float)($exec_row['AR_120_Percent'] ?? 0),
    'Denied_Claims' => (int)($exec_row['Denied_Claims'] ?? 0),
    'Total_Claims_Submitted' => (int)($exec_row['Total_Claims_Submitted'] ?? 0),
    'Current_Monthly_Claim_Volume' => (int)($exec_row['Current_Monthly_Claim_Volume'] ?? 0),
    'Denial_Rate' => (float)($exec_row['Denial_Rate'] ?? 0),
    'Claims_Paid_On_First_Submission' => (int)($exec_row['Claims_Paid_On_First_Submission'] ?? 0),
    'First_Pass_Resolution_Rate' => (float)($exec_row['First_Pass_Resolution_Rate'] ?? 0)
];

$response['summary'] = [
    'total_charges_count' => $total_charges_cnt,
    'total_charges_amount' => round($total_charges_amt, 2),
    'total_dos_amount' => round($total_dos_amt, 2),
    'total_dos_count' => $total_dos_cnt,
    'total_patient_payment' => round($total_patient_pmt, 2),
    'total_insurance_payment' => round($total_insurance_pmt, 2),
    'total_missing_charges' => $total_missing_charges,
    'total_appointments' => $total_appointments,
    'locations_count' => count($response['data']['charges_by_location'])
];

echo json_encode($response, JSON_PRETTY_PRINT);
$conn->close();
