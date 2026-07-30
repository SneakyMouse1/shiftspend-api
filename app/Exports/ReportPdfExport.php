<?php

namespace App\Exports;

use Barryvdh\DomPDF\Facade\Pdf;

class ReportPdfExport
{
    /**
     * Generate PDF binary string from report data array.
     */
    public function generate(array $data): string
    {
        @ini_set('memory_limit', '512M');
        @ini_set('max_execution_time', '300');

        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = Pdf::loadView('exports.report-pdf', $data)
            ->setOption('defaultFont', 'dejavu sans')
            ->setPaper('a4', 'portrait');

        return $pdf->output();
    }
}
