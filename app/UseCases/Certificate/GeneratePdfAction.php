<?php

declare(strict_types=1);

namespace App\UseCases\Certificate;

use App\Models\Certificate;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use RuntimeException;

final class GeneratePdfAction
{
    public function __invoke(Certificate $certificate): void
    {
        $html = view('certificates.pdf', [
            'certificate' => $certificate,
        ])->render();

        $mpdf = new Mpdf([
            'format' => 'A4',
            'orientation' => 'L',
        ]);

        $mpdf->WriteHTML($html);

        $pdf = $mpdf->Output('', 'S');

        $stored = Storage::disk('private')->put(
            $certificate->pdf_path,
            $pdf,
        );

        if (! $stored) {
            throw new RuntimeException('修了証PDFの保存に失敗しました。');
        }
    }
}
