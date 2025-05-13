<?php

declare(strict_types=1);

namespace TimAlexander\Myagent\PDF;

use League\CommonMark\CommonMarkConverter;
use Dompdf\Dompdf;
use Dompdf\Options;

final class PDFService
{
    private CommonMarkConverter $converter;
    private Dompdf $dompdf;
    private string $reportsDirectory;

    public function __construct(string $reportsDirectory = 'reports')
    {
        $this->converter = new CommonMarkConverter();
        
        $options = new Options();
        $options->set('defaultFont', 'Helvetica');
        $options->set('isRemoteEnabled', true);
        
        $this->dompdf = new Dompdf($options);
        $this->reportsDirectory = $reportsDirectory;
        
        // Create the reports directory if it doesn't exist
        if (!is_dir($this->reportsDirectory)) {
            mkdir($this->reportsDirectory, 0755, true);
        }
    }

    /**
     * Convert markdown to PDF and save it to the reports directory
     *
     * @param string $markdown The markdown content
     * @param string $title The title of the PDF (also used for filename)
     * @param string|null $filename Optional custom filename (without extension)
     * @return string Path to the generated PDF file
     */
    public function convertAndSave(string $markdown, string $title, ?string $filename = null): string
    {
        // Convert markdown to HTML
        $html = $this->converter->convert($markdown);
        
        // Create a complete HTML document with title
        $fullHtml = <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>{$title}</title>
            <style>
                body { font-family: Helvetica, Arial, sans-serif; margin: 40px; }
                h1 { text-align: center; margin-bottom: 30px; }
                pre { background-color: #f5f5f5; padding: 10px; border-radius: 4px; }
                code { font-family: monospace; }
            </style>
        </head>
        <body>
            <h1>{$title}</h1>
            {$html}
        </body>
        </html>
        HTML;
        
        // Load HTML into DOMPDF
        $this->dompdf->loadHtml($fullHtml);
        
        // Set paper size and orientation
        $this->dompdf->setPaper('A4', 'portrait');
        
        // Render PDF
        $this->dompdf->render();
        
        // Generate filename
        $safeFilename = $filename ?? $this->sanitizeFilename($title);
        $pdfPath = $this->reportsDirectory . '/' . $safeFilename . '.pdf';
        
        // Save to file
        file_put_contents($pdfPath, $this->dompdf->output());
        
        return $pdfPath;
    }
    
    /**
     * Create a safe filename from a title
     *
     * @param string $title
     * @return string
     */
    private function sanitizeFilename(string $title): string
    {
        // Replace non-alphanumeric characters with underscores
        $filename = preg_replace('/[^a-zA-Z0-9]/', '_', $title);
        // Convert to lowercase
        $filename = strtolower($filename);
        // Add timestamp to ensure uniqueness
        $filename .= '_' . date('Y-m-d_H-i-s');
        
        return $filename;
    }
} 