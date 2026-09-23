<?php

declare(strict_types=1);

namespace App\UseCases\Certificate;

use App\Models\Certificate;
use Illuminate\Support\Facades\Storage;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use RuntimeException;

/**
 * PDF生成失敗時のテストでMockeryによるモック化が必要なため、finalは付けない。
 */
class GeneratePdfAction
{
    public function __invoke(Certificate $certificate): void
    {
        $html = view('certificates.pdf', [
            'certificate' => $certificate,
        ])->render();

        $defaultConfig = (new ConfigVariables)->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];

        $defaultFontConfig = (new FontVariables)->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        $fontDirs[] = resource_path('fonts');

        $fontData['ipaexg'] = [
            'R' => 'ipaexg.ttf',
        ];

        $mpdf = new Mpdf([
            'format' => 'A4',
            'orientation' => 'L',
            'fontDir' => $fontDirs,
            'fontdata' => $fontData,
            'default_font' => 'ipaexg',
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
