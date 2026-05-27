<?php
require 'vendor/autoload.php';
require 'db.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

try {
    $configId = $_GET['config_id'] ?? null;

    if (!$configId) {
        throw new Exception('Konfigurācija nav norādīta');
    }

    // Get config
    $stmt = $pdo->prepare('SELECT * FROM scholarship_config WHERE id = ?');
    $stmt->execute([$configId]);
    $config = $stmt->fetch();

    if (!$config) {
        throw new Exception('Konfigurācija nav atrasta');
    }

    // Get results ordered by last_name, first_name
    $stmt = $pdo->prepare("
        SELECT 
            s.last_name,
            s.first_name,
            s.personal_code,
            s.group_name,
            sr.average_grade,
            sr.scholarship_amount
        FROM scholarship_results sr
        JOIN students s ON sr.student_id = s.id
        WHERE sr.config_id = ?
        ORDER BY s.group_name, s.last_name, s.first_name
    ");
    $stmt->execute([$configId]);
    $results = $stmt->fetchAll();

    // Create spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Stipendijas rezultāti');

    // Headers
    $headers = ['Uzvārds', 'Vārds', 'Personas kods', 'Grupa', 'Vidējais vērtējums', 'Stipendija (EUR)'];
    $sheet->fromArray([$headers], null, 'A1');

    // Format header row
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '000000']],
        'alignment' => ['horizontal' => 'center', 'vertical' => 'center']
    ];
    $sheet->getStyle('A1:F1')->applyFromArray($headerStyle);

    // Data rows
    $row = 2;
    foreach ($results as $result) {
        $sheet->setCellValue('A' . $row, $result['last_name']);
        $sheet->setCellValue('B' . $row, $result['first_name']);
        $sheet->setCellValue('C' . $row, $result['personal_code']);
        $sheet->setCellValue('D' . $row, $result['group_name']);

        if ($result['average_grade'] !== null) {
            $sheet->setCellValue('E' . $row, round($result['average_grade'], 2));
            $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode('0.00');
        } else {
            $sheet->setCellValue('E' . $row, '—');
        }

        $sheet->setCellValue('F' . $row, round($result['scholarship_amount'], 2));
        $sheet->getStyle('F' . $row)->getNumberFormat()->setFormatCode('0.00');

        $row++;
    }

    // Set column widths
    $sheet->getColumnDimension('A')->setWidth(15);
    $sheet->getColumnDimension('B')->setWidth(15);
    $sheet->getColumnDimension('C')->setWidth(15);
    $sheet->getColumnDimension('D')->setWidth(12);
    $sheet->getColumnDimension('E')->setWidth(18);
    $sheet->getColumnDimension('F')->setWidth(15);

    // Output file
    $writer = new Xlsx($spreadsheet);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="stipendijas_rezultati.xlsx"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    $writer->save('php://output');

} catch (Exception $e) {
    header('HTTP/1.0 400 Bad Request');
    echo 'Kļūda: ' . htmlspecialchars($e->getMessage());
}
