<?php

namespace Tests\Feature;

use App\Models\SiasnAbsensiLocationEmployee;
use App\Models\SiasnPnsProfile;
use App\Services\PetaJabatanExcelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class PetaJabatanSiasnTest extends TestCase
{
    use RefreshDatabase;

    public function test_siasn_people_are_matched_to_tpp_skpd_by_code_when_ids_shift(): void
    {
        config(['services.tpp.peta_jabatan_excel_path' => $this->makePetaJabatanExcel()]);

        $profile = SiasnPnsProfile::query()->create([
            'nip' => '198001012006041001',
            'jenis_asn' => 'PNS',
            'nama' => 'Pegawai BKPSDM',
            'jabatan' => 'Analis SDM Aparatur Ahli Muda',
            'unit_organisasi' => 'BKPSDM',
        ]);

        SiasnAbsensiLocationEmployee::query()->create([
            'skpd_id' => 24,
            'kode_skpd' => '4.01.07.',
            'nama_skpd' => 'Badan Kepegawaian Daerah, Pendidikan dan Pelatihan',
            'lokasi_id' => 'excel-siasn:24',
            'lokasi_nama' => 'BKPSDM',
            'nip' => '198001012006041001',
            'nama' => 'Pegawai BKPSDM',
            'siasn_pns_profile_id' => $profile->id,
            'siasn_unit_organisasi' => 'BKPSDM',
            'siasn_jabatan' => 'Analis SDM Aparatur Ahli Muda',
            'match_status' => 'excel_siasn_import',
            'row_data' => [
                'UNOR 1' => 'BADAN KEPEGAWAIAN DAN PENGEMBANGAN SUMBER DAYA MANUSIA',
                'siasn_status' => 'PNS',
            ],
        ]);

        $payload = [
            'skpd' => [
                [
                    'skpd_id' => 24,
                    'kode' => '4.01.06.',
                    'nama' => 'Inspektorat',
                    'tree' => [],
                ],
                [
                    'skpd_id' => 25,
                    'kode' => '4.01.07.',
                    'nama' => 'Badan Kepegawaian dan Pengembangan Sumber Daya Manusia',
                    'tree' => [],
                ],
            ],
        ];

        $comparison = app(PetaJabatanExcelService::class)->comparison($payload, 0, true, false);
        $sheet = $comparison['sheets'][0];
        $record = $sheet['comparison_records'][0];

        $this->assertTrue($comparison['success']);
        $this->assertSame(25, $sheet['matched_skpd']['skpd_id']);
        $this->assertSame(1, $record['filled']);
        $this->assertSame(0, $record['vacant']);
        $this->assertSame(['Pegawai BKPSDM'], $record['people']);
    }

    private function makePetaJabatanExcel(): string
    {
        $rows = [
            ['PETA JABATAN BADAN KEPEGAWAIAN DAN PENGEMBANGAN SUMBER DAYA MANUSIA'],
            ['Jabatan Fungsional'],
            ['JABATAN', 'KELAS', 'B', 'K', '+/-'],
            ['Analis SDM Aparatur Ahli Muda', '10', '0', '1', '-1'],
        ];
        $path = storage_path('framework/testing/peta-jabatan-test-'.str_replace('.', '', uniqid('', true)).'.xlsx');

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('xl/workbook.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="BKPSDM" sheetId="1" r:id="rId1"/>
    </sheets>
</workbook>
XML);
        $zip->addFromString('xl/_rels/workbook.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>
XML);
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheetXml($rows));
        $zip->close();

        return $path;
    }

    private function sheetXml(array $rows): string
    {
        $maxColumn = max(array_map('count', $rows));
        $xml = ['<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'];
        $xml[] = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml[] = '<dimension ref="A1:'.$this->excelColumn($maxColumn).count($rows).'"/>';
        $xml[] = '<sheetData>';

        foreach ($rows as $rowIndex => $row) {
            $rowNumber = $rowIndex + 1;
            $xml[] = '<row r="'.$rowNumber.'">';

            foreach ($row as $columnIndex => $value) {
                $reference = $this->excelColumn($columnIndex + 1).$rowNumber;
                $xml[] = '<c r="'.$reference.'" t="inlineStr"><is><t>'.htmlspecialchars($value, ENT_XML1).'</t></is></c>';
            }

            $xml[] = '</row>';
        }

        $xml[] = '</sheetData>';
        $xml[] = '</worksheet>';

        return implode('', $xml);
    }

    private function excelColumn(int $number): string
    {
        $column = '';

        while ($number > 0) {
            $number--;
            $column = chr(65 + ($number % 26)).$column;
            $number = intdiv($number, 26);
        }

        return $column;
    }
}
