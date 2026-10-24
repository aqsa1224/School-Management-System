<?php

namespace App\Support;

use Dompdf\Dompdf;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTML se PDF banata hai (DomPDF library se).
 * Library install karne ke liye:  composer require dompdf/dompdf
 */
class Pdf
{
    public static function available(): bool
    {
        return class_exists(Dompdf::class);
    }

    public static function download(string $html, string $filename, string $paper = 'A5', string $orientation = 'portrait'): Response
    {
        $dir = storage_path('app/dompdf');
        File::ensureDirectoryExists($dir);

        $dompdf = new Dompdf([
            'defaultFont'          => 'DejaVu Sans',
            'isRemoteEnabled'      => false,
            'isHtml5ParserEnabled' => true,
            'fontDir'              => $dir,
            'fontCache'            => $dir,
            'tempDir'              => $dir,
            'chroot'               => base_path(),
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper($paper, $orientation === 'landscape' ? 'landscape' : 'portrait');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
