<?php

/**
 * Build Super Hukdis templates from scratch using PHPWord.
 *
 * Menghasilkan file .docx template yang bersih dengan placeholder ${VARIABLE}
 * untuk setiap kategori Super Hukdis.
 *
 * Template dihasilkan berdasarkan format surat asli yang ada di folder superhukdis/
 */

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Font;

$outputDir = __DIR__ . '/../storage/app/templates/super-hukdis';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

// ─── Style definitions ──────────────────────────────────────────────
$fontName = 'Arial';
$fontSize = 12;
$fontSizeSmall = 11;
$fontSizeBig = 13;

$styleParagraphCenter = ['alignment' => Jc::CENTER, 'spaceAfter' => 0, 'spaceBefore' => 0];
$styleParagraphLeft = ['alignment' => Jc::BOTH, 'spaceAfter' => 0, 'spaceBefore' => 0];
$styleParagraphField = ['alignment' => Jc::LEFT, 'spaceAfter' => 20, 'spaceBefore' => 20, 'tabs' => [
    new \PhpOffice\PhpWord\Style\Tab('left', 2800),
    new \PhpOffice\PhpWord\Style\Tab('left', 3100),
]];

$fontBold = ['name' => $fontName, 'size' => $fontSize, 'bold' => true];
$fontNormal = ['name' => $fontName, 'size' => $fontSize];
$fontSmall = ['name' => $fontName, 'size' => $fontSizeSmall];
$fontBig = ['name' => $fontName, 'size' => $fontSizeBig, 'bold' => true];
$fontBigCenter = ['name' => $fontName, 'size' => $fontSizeBig, 'bold' => true];
$fontUnderlineBold = ['name' => $fontName, 'size' => $fontSize, 'bold' => true, 'underline' => 'single'];

// ─── Template definitions ────────────────────────────────────────────

$templates = [
    // === FORMAT A: "Tidak sedang dalam proses atau menjalani hukuman disiplin" ===
    'mutasi' => [
        'judul' => "SURAT PERNYATAAN\nTIDAK SEDANG DALAM PROSES ATAU MENJALANI\nHUKUMAN DISIPLIN DAN/ATAU DALAM PROSES PERADILAN",
        'nomor' => 'Nomor : 800.1.6.2/      -MPPEKA/BKD,DIKLAT/${TAHUN}',
        'isi_pernyataan' => 'tidak sedang dalam proses atau menjalani hukuman disiplin dan/atau dalam proses peradilan.',
        'penutup' => 'Demikian Surat Pernyataan ini dibuat dalam keadaan sebenar-benarnya untuk dapat dipergunakan sebagaimana mestinya.',
        'format' => 'A',
    ],
    'promosi' => [
        'judul' => "SURAT PERNYATAAN\nTIDAK SEDANG DALAM PROSES ATAU MENJALANI\nHUKUMAN DISIPLIN DAN/ATAU DALAM PROSES PERADILAN",
        'nomor' => 'Nomor : 800.1.6.2/      -MPPEKA/BKD,Diklat/${TAHUN}',
        'isi_pernyataan' => 'tidak sedang dalam proses atau menjalani hukuman disiplin dan/atau dalam proses peradilan.',
        'penutup' => 'Demikian Surat Pernyataan ini dibuat dalam keadaan sebenar-benarnya untuk dapat dipergunakan sebagaimana mestinya.',
        'format' => 'A',
    ],

    // === FORMAT B: "Tidak pernah dijatuhi hukuman disiplin tingkat sedang/berat" ===
    'pensiun' => [
        'judul' => "SURAT PERNYATAAN\nTIDAK PERNAH DIJATUHI HUKUMAN DISIPLIN\nTINGKAT SEDANG/BERAT",
        'nomor' => 'NOMOR: 800.1.6.2/      -MPPEKA/BKD,Diklat/${TAHUN}',
        'isi_pernyataan' => 'dalam satu tahun terakhir tidak pernah dijatuhi hukuman disiplin tingkat sedang/berat.',
        'penutup' => 'Demikian surat pernyataan ini saya buat dengan sesungguhnya dengan mengingat sumpah jabatan dan apabila dikemudian hari ternyata isi surat pernyataan ini tidak benar yang mengakibatkan kerugian bagi negara maka saya bersedia menanggung kerugian tersebut.',
        'format' => 'B',
    ],
    'slks' => [
        'judul' => "SURAT PERNYATAAN\nTIDAK PERNAH DIJATUHI HUKUMAN DISIPLIN\nTINGKAT SEDANG/BERAT",
        'nomor' => 'NOMOR: 800.1.6.2/      -Kum.Dis/BKD,Diklat/${TAHUN}',
        'isi_pernyataan' => 'tidak pernah dijatuhi hukuman disiplin tingkat sedang/berat.',
        'penutup' => 'Demikian surat pernyataan ini saya buat dengan sesungguhnya dengan mengingat sumpah jabatan dan apabila dikemudian hari ternyata isi surat pernyataan ini tidak benar yang mengakibatkan kerugian bagi negara maka saya bersedia menanggung kerugian tersebut.',
        'format' => 'B',
    ],
    'seleksi-jpt' => [
        'judul' => "SURAT PERNYATAAN\nTIDAK PERNAH DIJATUHI HUKUMAN DISIPLIN\nTINGKAT SEDANG/BERAT",
        'nomor' => 'NOMOR: 800.1.6.2/      - MPEKA/BKPSDM/${TAHUN}',
        'isi_pernyataan' => 'tidak pernah dijatuhi hukuman disiplin tingkat sedang/berat.',
        'penutup' => 'Demikian surat pernyataan ini saya buat dengan sesungguhnya dengan mengingat sumpah jabatan dan apabila dikemudian hari ternyata isi surat pernyataan ini tidak benar yang mengakibatkan kerugian bagi negara maka saya bersedia menanggung kerugian tersebut.',
        'format' => 'B',
    ],
    'pangkat' => [
        'judul' => "SURAT PERNYATAAN\nTIDAK PERNAH DIJATUHI HUKUMAN DISIPLIN\nTINGKAT SEDANG/BERAT",
        'nomor' => 'NOMOR: 800.1.6.2/      -MPPEKA/BKD,Diklat/${TAHUN}',
        'isi_pernyataan' => 'dalam satu tahun terakhir tidak pernah dijatuhi hukuman disiplin tingkat sedang/berat.',
        'penutup' => 'Demikian surat pernyataan ini saya buat dengan sesungguhnya dengan mengingat sumpah jabatan dan apabila dikemudian hari ternyata isi surat pernyataan ini tidak benar yang mengakibatkan kerugian bagi negara maka saya bersedia menanggung kerugian tersebut.',
        'format' => 'B',
    ],
    'ukom-inpassing' => [
        'judul' => "SURAT PERNYATAAN\nTIDAK PERNAH DIJATUHI HUKUMAN DISIPLIN\nTINGKAT SEDANG/BERAT",
        'nomor' => 'NOMOR: 800.1.6.2/      -MPPEKA/BKD,Diklat/${TAHUN}',
        'isi_pernyataan' => 'dalam satu tahun terakhir tidak pernah dijatuhi hukuman disiplin tingkat sedang/berat.',
        'penutup' => 'Demikian surat pernyataan ini saya buat dengan sesungguhnya dengan mengingat sumpah jabatan dan apabila dikemudian hari ternyata isi surat pernyataan ini tidak benar yang mengakibatkan kerugian bagi negara maka saya bersedia menanggung kerugian tersebut.',
        'format' => 'B',
    ],
];

// ─── Build each template ─────────────────────────────────────────────

foreach ($templates as $key => $tpl) {
    echo "Building $key.docx...\n";

    $phpWord = new PhpWord();
    $phpWord->setDefaultFontName($fontName);
    $phpWord->setDefaultFontSize($fontSize);

    // Page setup: A4, Folio-ish margins
    $section = $phpWord->addSection([
        'pageSizeW' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(21.5),
        'pageSizeH' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(33),
        'marginTop' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2),
        'marginBottom' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2),
        'marginLeft' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(3),
        'marginRight' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2),
    ]);

    // ─── KOP SURAT ───
    $section->addText('PEMERINTAH KOTA BANJARMASIN', $fontBold, $styleParagraphCenter);
    $section->addText('BADAN KEPEGAWAIAN DAN', $fontBold, $styleParagraphCenter);
    $section->addText('PENGEMBANGAN SUMBER DAYA MANUSIA', $fontBold, $styleParagraphCenter);
    $section->addText(
        'Jalan R.E. Martadinata No.1 Telp. (0511) 3363790 Fax. (0511) 3353933',
        $fontSmall,
        $styleParagraphCenter
    );
    $section->addText('Kotak Pos 79 Banjarmasin 70111', $fontSmall, $styleParagraphCenter);

    // Garis pembatas
    $section->addText('', [], ['spaceAfter' => 0, 'spaceBefore' => 100,
        'borderBottomSize' => 18, 'borderBottomColor' => '000000']);

    $section->addTextBreak(1);

    // ─── JUDUL SURAT ───
    $judulLines = explode("\n", $tpl['judul']);
    foreach ($judulLines as $line) {
        $section->addText($line, $fontBigCenter, $styleParagraphCenter);
    }

    // NOMOR
    $section->addText($tpl['nomor'], $fontNormal, $styleParagraphCenter);

    $section->addTextBreak(1);

    // ─── BAGIAN PENANDATANGAN ───
    if ($tpl['format'] === 'A') {
        $section->addText('Saya yang bertandatangan di bawah ini :', $fontNormal, $styleParagraphLeft);
    } else {
        $section->addText('Yang bertanda tangan dibawah ini :', $fontNormal, $styleParagraphLeft);
    }

    $section->addTextBreak(0);

    // Data penandatangan (Kepala BKPSDM) — ini BUKAN placeholder, ini static
    addFieldRow($section, 'N a m a', '${KEPALA_NAMA}', $fontNormal, $styleParagraphField);
    addFieldRow($section, 'N I P', '${KEPALA_NIP}', $fontNormal, $styleParagraphField);
    addFieldRow($section, 'Pangkat/Gol.Ruang', '${KEPALA_PANGKAT}', $fontNormal, $styleParagraphField);
    addFieldRow($section, 'Jabatan', '${KEPALA_JABATAN}', $fontNormal, $styleParagraphField);

    if ($tpl['format'] === 'A') {
        addFieldRow($section, 'Instansi', 'Pemerintah Kota Banjarmasin', $fontNormal, $styleParagraphField);
    }

    $section->addTextBreak(0);

    // ─── PERNYATAAN TENTANG PNS ───
    if ($tpl['format'] === 'A') {
        $section->addText('Dengan ini menyatakan dengan sesungguhnya bahwa Pegawai Negeri Sipil :', $fontNormal, $styleParagraphLeft);
    } else {
        $section->addText('dengan ini menyatakan dengan sesungguhnya, bahwa Pegawai Negeri Sipil :', $fontNormal, $styleParagraphLeft);
    }

    $section->addTextBreak(0);

    // Data pegawai — INI YANG PAKAI PLACEHOLDER
    addFieldRow($section, 'N a m a', '${NAMA}', $fontNormal, $styleParagraphField);
    addFieldRow($section, 'N I P', '${NIP}', $fontNormal, $styleParagraphField);
    addFieldRow($section, 'Pangkat/Gol.Ruang', '${PANGKAT_GOLONGAN}', $fontNormal, $styleParagraphField);
    addFieldRow($section, 'Jabatan', '${JABATAN}', $fontNormal, $styleParagraphField);
    addFieldRow($section, 'Unit Kerja', '${UNIT_KERJA}', $fontNormal, $styleParagraphField);

    if ($tpl['format'] === 'A') {
        addFieldRow($section, 'Instansi', '${INSTANSI}', $fontNormal, $styleParagraphField);
    }

    $section->addTextBreak(0);

    // ─── ISI PERNYATAAN ───
    $section->addText($tpl['isi_pernyataan'], $fontNormal, $styleParagraphLeft);

    $section->addTextBreak(0);

    // ─── PENUTUP ───
    $section->addText($tpl['penutup'], $fontNormal, $styleParagraphLeft);

    $section->addTextBreak(1);

    // ─── TANDA TANGAN ───
    $ttdParagraph = ['alignment' => Jc::RIGHT, 'spaceAfter' => 0, 'spaceBefore' => 0, 'indentRight' => 200];

    $section->addText('Banjarmasin, ${TANGGAL}', $fontNormal, $ttdParagraph);
    $section->addText('Kepala,', $fontNormal, $ttdParagraph);

    $section->addTextBreak(3);

    $section->addText('${KEPALA_NAMA}', $fontUnderlineBold, $ttdParagraph);
    $section->addText('${KEPALA_PANGKAT}', $fontNormal, $ttdParagraph);
    $section->addText('NIP. ${KEPALA_NIP}', $fontNormal, $ttdParagraph);

    // ─── Save ───
    $outputPath = $outputDir . '/' . $key . '.docx';
    $phpWord->save($outputPath, 'Word2007');
    echo "  Saved to $outputPath\n";

    // Verify
    $tp = new \PhpOffice\PhpWord\TemplateProcessor($outputPath);
    $vars = $tp->getVariables();
    echo "  Variables: " . implode(', ', $vars) . "\n\n";
}

echo "=== All templates built! ===\n";

// ─── Helper ──────────────────────────────────────────────────────────

function addFieldRow($section, string $label, string $value, array $fontStyle, array $paragraphStyle): void
{
    $section->addText($label . "\t: " . $value, $fontStyle, $paragraphStyle);
}
