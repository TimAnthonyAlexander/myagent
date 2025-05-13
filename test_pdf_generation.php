<?php

require_once __DIR__ . '/vendor/autoload.php';

use TimAlexander\Myagent\PDF\PDFService;

// Create the PDFService
$pdfService = new PDFService();

// Read the test markdown file
$markdownContent = file_get_contents(__DIR__ . '/test_table.md');

// Convert and save to PDF
$pdfPath = $pdfService->convertAndSave(
    $markdownContent,
    'Markdown Table Test',
    'table_test'
);

echo "PDF generated successfully: $pdfPath" . PHP_EOL; 