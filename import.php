<?php
require 'vendor/autoload.php';
require 'db.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    $action = $_POST['action'] ?? null;

    if ($action === 'upload_subjects') {
        handleSubjectUpload($pdo);
    } elseif ($action === 'upload_grades') {
        handleGradesUpload($pdo);
    } elseif ($action === 'calculate') {
        handleCalculate($pdo);
    } elseif ($action === 'toggle_exclusion') {
        handleToggleExclusion($pdo);
    } elseif ($action === 'get_group_data') {
        handleGetGroupData($pdo);
    } elseif ($action === 'recalculate') {
        handleRecalculate($pdo);
    } elseif ($action === 'clear_all') {
        handleClearAll($pdo);
    } else {
        throw new Exception('Nezināma darbība');
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    http_response_code(400);
}

echo json_encode($response);
exit;

// ============================================================================
// FILE UPLOAD HANDLERS
// ============================================================================

function handleSubjectUpload($pdo)
{
    global $response;

    if (!isset($_FILES['subjects_file']) || $_FILES['subjects_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Faila augšupielāde neizdevās');
    }

    $fileTmpName = $_FILES['subjects_file']['tmp_name'];
    $fileName = $_FILES['subjects_file']['name'];

    if (!in_array(pathinfo($fileName, PATHINFO_EXTENSION), ['xlsx', 'xls'])) {
        throw new Exception('Tikai Excel faili (.xlsx, .xls) ir atļauti');
    }

    $spreadsheet = IOFactory::load($fileTmpName);
    $worksheet = $spreadsheet->getActiveSheet();
    $rows = $worksheet->toArray();

    $pdo->exec('DELETE FROM subjects');

    $insertCount = 0;
    $stmt = $pdo->prepare('INSERT INTO subjects (name, type) VALUES (?, ?)');

    foreach ($rows as $index => $row) {
        if ($index === 0)
            continue;
        if (empty($row[0]))
            continue;

        $name = trim($row[0] ?? '');
        $type = strtoupper(trim($row[1] ?? ''));

        if (!in_array($type, ['VIMP', 'PROF']))
            continue;

        if (!empty($name)) {
            try {
                $stmt->execute([$name, $type]);
                $insertCount++;
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000')
                    throw $e;
            }
        }
    }

    $response['success'] = true;
    $response['message'] = "Mācību priekšmeti veiksmīgi augšupielādēti: {$insertCount} priekšmeti";
    $response['count'] = $insertCount;
}

function handleGradesUpload($pdo)
{
    global $response;

    if (!isset($_FILES['grades_file']) || $_FILES['grades_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Faila augšupielāde neizdevās');
    }

    $fileTmpName = $_FILES['grades_file']['tmp_name'];
    $fileName = $_FILES['grades_file']['name'];

    if (!in_array(pathinfo($fileName, PATHINFO_EXTENSION), ['xlsx', 'xls'])) {
        throw new Exception('Tikai Excel faili (.xlsx, .xls) ir atļauti');
    }

    $result = parseEklaseFile($fileTmpName, $pdo);
    $response['success'] = true;
    $response['message'] = "Dati veiksmīgi augšupielādēti: " . $result['students'] .
        " izglītojamie, " . $result['grades'] . " vērtējumi";
    $response['students'] = $result['students'];
    $response['grades'] = $result['grades'];
}

// ============================================================================
// E-KLASE PARSER
// ============================================================================

function parseEklaseFile($filePath, $pdo)
{
    try {
        $spreadsheet = IOFactory::load($filePath);
    } catch (Exception $e) {
        throw new Exception('Excel failu nevar nolasīt. Pārbaudiet, vai tas ir derīgs E-klases fails (.xlsx)');
    }

    // Clear existing data
    $pdo->exec("DELETE FROM excluded_grades");
    $pdo->exec("DELETE FROM grades");
    $pdo->exec("DELETE FROM students");

    $skipSheets = ['Apv. žurn. - žurn. pārb.', 'Ind. d. žurn. - žurn. pārb.'];
    $totalStudents = 0;
    $totalGrades = 0;
    $studentCache = [];

    foreach ($spreadsheet->getAllSheets() as $sheet) {
        $sheetName = $sheet->getTitle();
        if (in_array($sheetName, $skipSheets))
            continue;

        $rows = $sheet->toArray(null, true, true, false);

        if (empty($rows) || count($rows) < 4)
            continue;

        $groupName = trim($rows[0][0] ?? $sheetName);
        $headerRow = $rows[1];
        $firstNameRow = $rows[2];
        $personalCodeRow = $rows[3];

        $nonStudentMarkers = ['Tēma', 'Mājasdarba uzdevums', 'Piezīmes', 'Autors', 'Priekšmeta skolotājs'];
        $students = [];

        for ($col = 5; $col < count($headerRow); $col++) {
            $val = trim($headerRow[$col] ?? '');
            if ($val === '' || $val === "\xc2\xa0" || in_array($val, $nonStudentMarkers))
                break;

            $lastName = $val;
            $firstName = trim($firstNameRow[$col] ?? '');
            $personalCode = trim($personalCodeRow[$col] ?? '');

            if (empty($personalCode) || $personalCode === "\xc2\xa0")
                continue;

            if (!isset($studentCache[$personalCode])) {
                $stmt = $pdo->prepare(
                    "INSERT INTO students (last_name, first_name, personal_code, group_name)
                     VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)"
                );
                $stmt->execute([$lastName, $firstName, $personalCode, $groupName]);
                $studentId = $pdo->lastInsertId();
                if (!$studentId) {
                    $getStmt = $pdo->prepare("SELECT id FROM students WHERE personal_code = ?");
                    $getStmt->execute([$personalCode]);
                    $existing = $getStmt->fetch();
                    $studentId = $existing ? $existing['id'] : null;
                }
                $studentCache[$personalCode] = $studentId;
                if ($studentId)
                    $totalStudents++;
            }

            if ($studentCache[$personalCode]) {
                $students[$col] = ['id' => $studentCache[$personalCode]];
            }
        }

        $validGradeTypes = [
            'I semestra vērtējums',
            'II semestra vērtējums',
            'Galīgais vērtējums priekšmetā',
            'Gada vērtējums',
        ];

        $gradeStmt = $pdo->prepare(
            "INSERT INTO grades (student_id, subject_id, subject_name, grade_type, grade_value, is_insufficient, grade_date)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

        for ($rowIdx = 4; $rowIdx < count($rows); $rowIdx++) {
            $row = $rows[$rowIdx];
            if (trim($row[0] ?? '') !== 'Žurnāls')
                continue;

            $subjectName = trim($row[1] ?? '');
            $dateStr = trim($row[3] ?? '');
            $gradeType = trim($row[4] ?? '');

            if (!in_array($gradeType, $validGradeTypes))
                continue;

            $date = null;
            if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $dateStr, $m)) {
                $date = "{$m[3]}-{$m[2]}-{$m[1]}";
            }
            if (!$date)
                continue;

            $subjectId = null;
            if (!empty($subjectName)) {
                $subjectStmt = $pdo->prepare("SELECT id FROM subjects WHERE name = ? LIMIT 1");
                $subjectStmt->execute([$subjectName]);
                $subjectRow = $subjectStmt->fetch();
                $subjectId = $subjectRow ? $subjectRow['id'] : null;
            }

            foreach ($students as $col => $student) {
                $rawVal = trim((string) ($row[$col] ?? ''));

                if ($rawVal === '' || $rawVal === "\xc2\xa0" || strtolower($rawVal) === 'n')
                    continue;

                if (strpos($rawVal, '%') !== false)
                    continue;

                $gradeValue = null;
                $isInsufficient = 0;

                if (strtolower($rawVal) === 'nv') {
                    $isInsufficient = 1;
                } elseif (is_numeric($rawVal)) {
                    $gradeValue = (float) $rawVal;
                } else {
                    continue;
                }

                try {
                    $gradeStmt->execute([
                        $student['id'],
                        $subjectId,
                        $subjectName,
                        $gradeType,
                        $gradeValue,
                        $isInsufficient,
                        $date
                    ]);
                    $totalGrades++;
                } catch (Exception $e) {
                    // Skip on error
                }
            }
        }
    }

    return ['students' => count($studentCache), 'grades' => $totalGrades];
}

// ============================================================================
// CALCULATION
// ============================================================================

function handleCalculate($pdo)
{
    global $response;

    $periodStart = $_POST['period_start'] ?? null;
    $periodEnd = $_POST['period_end'] ?? null;
    $monthlyBudget = floatval($_POST['monthly_budget'] ?? 0);
    $brackets = $_POST['brackets'] ?? [];

    if (!$periodStart || !$periodEnd) {
        throw new Exception('Jāievada perioda sākums un beigas');
    }
    if (strtotime($periodStart) > strtotime($periodEnd)) {
        throw new Exception('Sākuma datums nevar būt vēlāks par beigu datumu');
    }
    if ($monthlyBudget <= 0) {
        throw new Exception('Mēneša budžetam jābūt lielākam par 0');
    }
    if (empty($brackets)) {
        $brackets = [
            ['from' => 4.0, 'to' => 5.0, 'amount' => 50],
            ['from' => 5.0, 'to' => 6.0, 'amount' => 75],
            ['from' => 6.0, 'to' => 7.0, 'amount' => 100],
            ['from' => 7.0, 'to' => 8.0, 'amount' => 125],
            ['from' => 8.0, 'to' => 9.0, 'amount' => 150],
            ['from' => 9.0, 'to' => 10.0, 'amount' => 200],
        ];
    }

    $pdo->exec('DELETE FROM scholarship_config');
    
    $stmt = $pdo->prepare('INSERT INTO scholarship_config (period_start, period_end, monthly_budget) VALUES (?, ?, ?)');
    $stmt->execute([$periodStart, $periodEnd, $monthlyBudget]);
    $configId = $pdo->lastInsertId();

    $bracketStmt = $pdo->prepare('INSERT INTO scholarship_brackets (config_id, range_from, range_to, amount) VALUES (?, ?, ?, ?)');
    foreach ($brackets as $bracket) {
        $bracketStmt->execute([
            $configId,
            floatval($bracket['from'] ?? 0),
            floatval($bracket['to'] ?? 0),
            floatval($bracket['amount'] ?? 0)
        ]);
    }

    $students = $pdo->query('SELECT id FROM students')->fetchAll();
    $resultStmt = $pdo->prepare('INSERT INTO scholarship_results (config_id, student_id, average_grade, scholarship_amount, scholarship_reason) VALUES (?, ?, ?, ?, ?)');
    $totalPayout = 0;

    foreach ($students as $student) {
        $result = calculateStudentScholarship($pdo, $student['id'], $configId, $periodStart, $periodEnd);
        $resultStmt->execute([$configId, $student['id'], $result['average_grade'], $result['amount'], $result['reason']]);
        $totalPayout += $result['amount'];
    }

    $response['success'] = true;
    $response['message'] = 'Stipendijas veiksmīgi aprēķinātas';
    $response['configId'] = $configId;
    $response['totalPayout'] = round($totalPayout, 2);
}

function handleRecalculate($pdo)
{
    global $response;

    $configId = $_POST['config_id'] ?? null;
    if (!$configId)
        throw new Exception('Config ID nav norādīts');

    $config = $pdo->prepare('SELECT * FROM scholarship_config WHERE id = ?');
    $config->execute([$configId]);
    $cfg = $config->fetch();
    if (!$cfg)
        throw new Exception('Konfigurācija nav atrasta');

    $periodStart = $cfg['period_start'];
    $periodEnd = $cfg['period_end'];

    $pdo->prepare('DELETE FROM scholarship_results WHERE config_id = ?')->execute([$configId]);

    $students = $pdo->query('SELECT id FROM students')->fetchAll();
    $resultStmt = $pdo->prepare('INSERT INTO scholarship_results (config_id, student_id, average_grade, scholarship_amount, scholarship_reason) VALUES (?, ?, ?, ?, ?)');
    $totalPayout = 0;

    foreach ($students as $student) {
        $result = calculateStudentScholarship($pdo, $student['id'], $configId, $periodStart, $periodEnd);
        $resultStmt->execute([$configId, $student['id'], $result['average_grade'], $result['amount'], $result['reason']]);
        $totalPayout += $result['amount'];
    }

    $response['success'] = true;
    $response['message'] = 'Stipendijas pārrēķinātas';
    $response['totalPayout'] = round($totalPayout, 2);
}

function calculateStudentScholarship($pdo, $studentId, $configId, $periodStart, $periodEnd)
{
    $stmt = $pdo->prepare("
        SELECT
            g.subject_name,
            g.grade_type,
            g.grade_value,
            g.is_insufficient,
            s.type AS subject_type
        FROM grades g
        LEFT JOIN subjects s ON s.name = g.subject_name
        WHERE g.student_id = ?
          AND g.grade_date BETWEEN ? AND ?
          AND g.grade_type IN (
              'Galīgais vērtējums priekšmetā',
              'II semestra vērtējums',
              'I semestra vērtējums'
          )
          AND NOT EXISTS (
              SELECT 1 FROM excluded_grades eg
              WHERE eg.config_id = ?
                AND eg.student_id = g.student_id
                AND eg.subject_id = g.subject_id
          )
        ORDER BY g.subject_name, g.grade_type
    ");
    $stmt->execute([$studentId, $periodStart, $periodEnd, $configId]);
    $rows = $stmt->fetchAll();

    if (empty($rows)) {
        return ['average_grade' => null, 'amount' => 0, 'reason' => 'Nav vērtējumu periodā'];
    }

    $subjects = [];
    foreach ($rows as $row) {
        $key = $row['subject_name'];

        $priority = match ($row['grade_type']) {
            'Galīgais vērtējums priekšmetā' => 0,
            'II semestra vērtējums'          => 1,
            'I semestra vērtējums'           => 2,
            default                          => 999,
        };

        if ($priority === 999) continue;

        if (!isset($subjects[$key]) || $priority < $subjects[$key]['priority']) {
            $subjects[$key] = [
                'subject_type' => $row['subject_type'] ?? 'VIMP',
                'grade_value'  => ($row['grade_value'] !== null) ? (float) $row['grade_value'] : null,
                'insufficient' => (bool) $row['is_insufficient'],
                'priority'     => $priority,
            ];
        }
    }

    if (empty($subjects)) {
        return ['average_grade' => null, 'amount' => 0, 'reason' => 'Nav derīgu vērtējumu periodā'];
    }

    $insufficientCount = 0;
    $gradeValuesForAverage = [];
    $insufficientDetails = [];

    foreach ($subjects as $subjectName => $data) {
        $threshold = ($data['subject_type'] === 'PROF') ? 5.0 : 4.0;

        if ($data['insufficient']) {
            $insufficientCount++;
            $insufficientDetails[] = "{$subjectName}: nv (nepietiekams)";
        } elseif ($data['grade_value'] !== null) {
            $gradeValuesForAverage[] = $data['grade_value'];
            if ($data['grade_value'] < $threshold) {
                $insufficientCount++;
                $insufficientDetails[] = "{$subjectName}: {$data['grade_value']} (< {$threshold} {$data['subject_type']})";
            }
        }
    }

    $average = !empty($gradeValuesForAverage)
        ? round(array_sum($gradeValuesForAverage) / count($gradeValuesForAverage), 2)
        : null;

    if ($insufficientCount >= 2) {
        $detailStr = implode('; ', $insufficientDetails);
        return [
            'average_grade' => $average,
            'amount'        => 0,
            'reason'        => "{$insufficientCount} nepietiekami vērtējumi — stipendija netiek piešķirta. {$detailStr}",
        ];
    }
    if ($insufficientCount === 1) {
        $detailStr = implode('; ', $insufficientDetails);
        return [
            'average_grade' => $average,
            'amount'        => 15.00,
            'reason'        => "1 nepietiekams vērtējums — minimālā stipendija. {$detailStr}",
        ];
    }

    if ($average === null) {
        return ['average_grade' => null, 'amount' => 0, 'reason' => 'Nav skaitlisko vērtējumu periodā'];
    }

    $bracketStmt = $pdo->prepare(
        'SELECT amount, range_from, range_to FROM scholarship_brackets
         WHERE config_id = ?
           AND range_from <= ?
           AND (? < range_to OR range_to = (SELECT MAX(range_to) FROM scholarship_brackets WHERE config_id = ?))
         ORDER BY range_from DESC
         LIMIT 1'
    );
    $bracketStmt->execute([$configId, $average, $average, $configId]);
    $bracket = $bracketStmt->fetch();

    if ($bracket && $average >= floatval($bracket['range_from']) && $average <= floatval($bracket['range_to'])) {
        $amount = floatval($bracket['amount']);
        $reason = "Vidējais {$average} — diapazons {$bracket['range_from']}–{$bracket['range_to']}";
    } else {
        $amount = 0.00;
        $reason = "Vidējais {$average} — neatbilst nevienam diapazonam";
    }

    return ['average_grade' => $average, 'amount' => $amount, 'reason' => $reason];
}

// ============================================================================
// GROUP VIEW DATA
// ============================================================================

function handleGetGroupData($pdo)
{
    global $response;

    $groupName = $_POST['group_name'] ?? null;
    $configId = $_POST['config_id'] ?? null;

    if (!$groupName || !$configId)
        throw new Exception('Trūkst parametru');

    $config = $pdo->prepare('SELECT * FROM scholarship_config WHERE id = ?');
    $config->execute([$configId]);
    $cfg = $config->fetch();
    if (!$cfg)
        throw new Exception('Konfigurācija nav atrasta');

    $periodStart = $cfg['period_start'];
    $periodEnd = $cfg['period_end'];

    $stmt = $pdo->prepare('SELECT id, last_name, first_name, personal_code FROM students WHERE group_name = ? ORDER BY last_name, first_name');
    $stmt->execute([$groupName]);
    $students = $stmt->fetchAll();

    if (empty($students)) {
        $response['success'] = true;
        $response['students'] = [];
        $response['subjects'] = [];
        return;
    }

    $studentIds = array_column($students, 'id');
    $placeholders = implode(',', array_fill(0, count($studentIds), '?'));

    $subjectStmt = $pdo->prepare("
        SELECT DISTINCT g.subject_name, g.subject_id
        FROM grades g
        WHERE g.student_id IN ($placeholders)
          AND g.grade_date BETWEEN ? AND ?
          AND g.grade_type != 'Gada vērtējums'
        ORDER BY g.subject_name
    ");
    $subjectStmt->execute(array_merge($studentIds, [$periodStart, $periodEnd]));
    $subjectRows = $subjectStmt->fetchAll();

    $subjects = [];
    foreach ($subjectRows as $row) {
        $subjects[$row['subject_name']] = $row['subject_id'];
    }

    $exclStmt = $pdo->prepare("
        SELECT eg.student_id, s.name as subject_name
        FROM excluded_grades eg
        JOIN subjects s ON s.id = eg.subject_id
        WHERE eg.config_id = ? AND eg.student_id IN ($placeholders)
    ");
    $exclStmt->execute(array_merge([$configId], $studentIds));
    $exclusions = [];
    foreach ($exclStmt->fetchAll() as $row) {
        $exclusions[$row['student_id']][$row['subject_name']] = true;
    }

    $studentData = [];
    foreach ($students as $student) {
        $sid = $student['id'];

        $gradeStmt = $pdo->prepare("
            SELECT g.subject_name, g.grade_type, g.grade_value, g.is_insufficient, g.subject_id
            FROM grades g
            WHERE g.student_id = ?
              AND g.grade_date BETWEEN ? AND ?
              AND g.grade_type != 'Gada vērtējums'
            ORDER BY g.subject_name, g.grade_type
        ");
        $gradeStmt->execute([$sid, $periodStart, $periodEnd]);
        $gradeRows = $gradeStmt->fetchAll();

        $chosen = [];
        foreach ($gradeRows as $row) {
            $key = $row['subject_name'];
            $priority = match ($row['grade_type']) {
                'Galīgais vērtējums priekšmetā' => 0,
                'II semestra vērtējums' => 1,
                'I semestra vērtējums' => 2,
                default => 999,
            };
            if (!isset($chosen[$key]) || $priority < $chosen[$key]['priority']) {
                $chosen[$key] = [
                    'priority' => $priority,
                    'grade_value' => $row['grade_value'],
                    'insufficient' => (bool) $row['is_insufficient'],
                    'subject_id' => $row['subject_id'],
                ];
            }
        }

        $gradeMap = [];
        foreach ($chosen as $subjectName => $data) {
            $gradeMap[$subjectName] = [
                'display' => $data['insufficient'] ? 'nv' : ($data['grade_value'] !== null ? number_format((float) $data['grade_value'], 0) : '—'),
                'subject_id' => $data['subject_id'],
                'excluded' => isset($exclusions[$sid][$subjectName]),
            ];
        }

        $resStmt = $pdo->prepare('SELECT average_grade, scholarship_amount FROM scholarship_results WHERE config_id = ? AND student_id = ?');
        $resStmt->execute([$configId, $sid]);
        $result = $resStmt->fetch();

        $studentData[] = [
            'id' => $sid,
            'last_name' => $student['last_name'],
            'first_name' => $student['first_name'],
            'personal_code' => $student['personal_code'],
            'grades' => $gradeMap,
            'average' => $result ? $result['average_grade'] : null,
            'scholarship' => $result ? $result['scholarship_amount'] : 0,
        ];
    }

    $response['success'] = true;
    $response['students'] = $studentData;
    $response['subjects'] = array_keys($subjects);
    $response['configId'] = $configId;
}

// ============================================================================
// EXCLUSION TOGGLE
// ============================================================================

function handleToggleExclusion($pdo)
{
    global $response;

    $configId = $_POST['config_id'] ?? null;
    $studentId = $_POST['student_id'] ?? null;
    $subjectId = $_POST['subject_id'] ?? null;
    $exclude = filter_var($_POST['exclude'] ?? false, FILTER_VALIDATE_BOOLEAN);

    if (!$configId || !$studentId || !$subjectId) {
        throw new Exception('Trūkst parametru');
    }

    if ($exclude) {
        $stmt = $pdo->prepare('INSERT IGNORE INTO excluded_grades (config_id, student_id, subject_id) VALUES (?, ?, ?)');
        $stmt->execute([$configId, $studentId, $subjectId]);
    } else {
        $stmt = $pdo->prepare('DELETE FROM excluded_grades WHERE config_id = ? AND student_id = ? AND subject_id = ?');
        $stmt->execute([$configId, $studentId, $subjectId]);
    }

    $response['success'] = true;
    $response['message'] = 'Izslēgšana atjaunināta';
}

// ============================================================================
// CLEAR ALL DATA
// ============================================================================

function handleClearAll($pdo)
{
    global $response;

    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        
        $tables = [
            'excluded_grades',
            'scholarship_results',
            'scholarship_brackets',
            'scholarship_config',
            'grades',
            'students',
            'subjects'
        ];

        foreach ($tables as $table) {
            $pdo->exec("TRUNCATE TABLE `$table`");
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $response['success'] = true;
        $response['message'] = 'Visi dati veiksmīgi iztīrīti (t.sk. konfigurācijas, studenti, vērtējumi)';

    } catch (Exception $e) {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        throw $e;
    }
}