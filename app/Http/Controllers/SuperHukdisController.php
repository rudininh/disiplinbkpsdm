<?php

namespace App\Http\Controllers;

use App\Models\SiasnPnsProfile;
use App\Services\SiasnProfileService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use PhpOffice\PhpWord\TemplateProcessor;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Controller untuk fitur Super Hukdis (Supervisi Hukuman Disiplin).
 *
 * Menggenerate Surat Pernyataan Tidak Pernah Dijatuhi Hukuman Disiplin
 * berdasarkan kategori yang dipilih dan data pegawai ASN dari SIASN.
 */
class SuperHukdisController extends Controller
{
    /**
     * Daftar kategori Super Hukdis beserta label dan nama file template.
     */
    private const KATEGORI = [
        'mutasi' => [
            'label' => 'Mutasi',
            'template' => 'mutasi.docx',
            'deskripsi' => 'Surat Pernyataan untuk keperluan Mutasi / Pindah Instansi',
        ],
        'pensiun' => [
            'label' => 'Pensiun',
            'template' => 'pensiun.docx',
            'deskripsi' => 'Surat Pernyataan untuk keperluan Pensiun',
        ],
        'slks' => [
            'label' => 'SLKS',
            'template' => 'slks.docx',
            'deskripsi' => 'Surat Pernyataan untuk keperluan SLKS',
        ],
        'seleksi-jpt' => [
            'label' => 'Seleksi JPT',
            'template' => 'seleksi-jpt.docx',
            'deskripsi' => 'Surat Pernyataan untuk keperluan Seleksi Jabatan Pimpinan Tinggi',
        ],
        'pangkat' => [
            'label' => 'Pangkat',
            'template' => 'pangkat.docx',
            'deskripsi' => 'Surat Pernyataan untuk keperluan Kenaikan Pangkat',
        ],
        'ukom-inpassing' => [
            'label' => 'Ukom / Inpassing',
            'template' => 'ukom-inpassing.docx',
            'deskripsi' => 'Surat Pernyataan untuk keperluan Uji Kompetensi atau Inpassing',
        ],
        'promosi' => [
            'label' => 'Promosi',
            'template' => 'promosi.docx',
            'deskripsi' => 'Surat Pernyataan untuk keperluan Promosi Jabatan',
        ],
    ];

    public function __construct(
        private readonly SiasnProfileService $siasnService
    ) {}

    /**
     * Tampilkan halaman Super Hukdis dengan form generate surat.
     */
    public function index(Request $request): View
    {
        $this->forgetExpiredSiasnToken($request);

        return view('absensi-cms.super-hukdis', [
            'kategoriList' => self::KATEGORI,
            'storedToken' => $this->storedSiasnToken($request),
            'result' => $request->session()->get('super_hukdis_result'),
            'lastProfile' => $request->session()->get('super_hukdis_profile'),
        ]);
    }

    /**
     * Generate file .docx surat pernyataan berdasarkan NIP dan kategori.
     */
    public function generate(Request $request): BinaryFileResponse|\Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'nip' => ['required', 'digits:18'],
            'kategori' => ['required', 'string', 'in:' . implode(',', array_keys(self::KATEGORI))],
        ], [
            'nip.required' => 'NIP wajib diisi.',
            'nip.digits' => 'NIP harus terdiri dari 18 digit.',
            'kategori.required' => 'Kategori surat wajib dipilih.',
            'kategori.in' => 'Kategori surat tidak valid.',
        ]);

        // Ambil token SIASN dari session
        $storedToken = $this->storedSiasnToken($request);
        if ($storedToken === null) {
            return redirect()
                ->route('cms.super-hukdis.index')
                ->with('super_hukdis_result', [
                    'success' => false,
                    'message' => 'Token SIASN belum tersimpan atau sudah kedaluwarsa. Silakan login SIASN terlebih dahulu.',
                ]);
        }

        // Fetch data pegawai dari SIASN API
        try {
            $result = $this->siasnService->fetchAndStore($data['nip'], $storedToken['token']);
        } catch (\Throwable $exception) {
            return redirect()
                ->route('cms.super-hukdis.index')
                ->with('super_hukdis_result', [
                    'success' => false,
                    'message' => 'Gagal mengambil data SIASN: ' . $exception->getMessage(),
                ])
                ->withInput();
        }

        /** @var SiasnPnsProfile $profile */
        $profile = $result['profile'];
        $merged = $profile->raw_data['merged'] ?? [];

        // Siapkan data untuk template
        $templateData = $this->buildTemplateData($profile, $merged);

        // Simpan profil terakhir untuk ditampilkan di halaman
        $request->session()->flash('super_hukdis_profile', $templateData);

        // Cek template file
        $kategoriConfig = self::KATEGORI[$data['kategori']];
        $templatePath = storage_path('app/templates/super-hukdis/' . $kategoriConfig['template']);

        if (! file_exists($templatePath)) {
            return redirect()
                ->route('cms.super-hukdis.index')
                ->with('super_hukdis_result', [
                    'success' => false,
                    'message' => 'Template surat untuk kategori "' . $kategoriConfig['label'] . '" belum tersedia. Letakkan file template di: storage/app/templates/super-hukdis/' . $kategoriConfig['template'],
                ])
                ->withInput();
        }

        // Generate dokumen Word menggunakan TemplateProcessor
        try {
            $outputPath = $this->generateDocument($templatePath, $templateData, $profile, $kategoriConfig);
        } catch (\Throwable $exception) {
            return redirect()
                ->route('cms.super-hukdis.index')
                ->with('super_hukdis_result', [
                    'success' => false,
                    'message' => 'Gagal generate dokumen: ' . $exception->getMessage(),
                ])
                ->withInput();
        }

        // Buat nama file output yang deskriptif
        $namaFile = 'SP Tidak Pernah Dijatuhi Hukuman Disiplin a.n. '
            . strtoupper($templateData['NAMA'] ?? 'PEGAWAI')
            . ' (' . $kategoriConfig['label'] . ').docx';

        $request->session()->flash('super_hukdis_result', [
            'success' => true,
            'message' => 'Surat berhasil di-generate untuk ' . ($templateData['NAMA'] ?? 'pegawai') . ' — kategori ' . $kategoriConfig['label'] . '.',
        ]);

        return response()
            ->download($outputPath, $namaFile)
            ->deleteFileAfterSend(true);
    }

    /**
     * Ambil data dari profil SIASN dan susun menjadi array placeholder template.
     */
    private function buildTemplateData(SiasnPnsProfile $profile, array $merged): array
    {
        // Ambil pangkat / golongan dari raw_data merged
        $pangkat = $this->stringValue($merged['gol_akhir_nama'] ?? null);
        $golongan = $this->stringValue($merged['gol_akhir_id'] ?? null);
        $pangkatGolongan = $pangkat;
        if ($golongan !== null && $pangkat !== null) {
            $pangkatGolongan = $pangkat . ' / ' . $golongan;
        }

        // Tempat & tanggal lahir
        $tempatLahir = $this->stringValue($merged['tempat_lahir'] ?? null);
        $tglLahirRaw = $merged['tgl_lahir'] ?? null;
        $tanggalLahir = null;
        if ($tglLahirRaw !== null) {
            try {
                $tanggalLahir = Carbon::parse((string) $tglLahirRaw)->translatedFormat('d F Y');
            } catch (\Throwable) {
                $tanggalLahir = (string) $tglLahirRaw;
            }
        }

        $ttl = null;
        if ($tempatLahir !== null && $tanggalLahir !== null) {
            $ttl = $tempatLahir . ', ' . $tanggalLahir;
        } elseif ($tempatLahir !== null) {
            $ttl = $tempatLahir;
        } elseif ($tanggalLahir !== null) {
            $ttl = $tanggalLahir;
        }

        // Alamat
        $alamat = $this->stringValue($merged['alamat'] ?? null);

        // Jenis kelamin
        $jenisKelamin = match ((string) ($merged['jenis_kelamin'] ?? '')) {
            '1', 'L', 'Laki-Laki', 'LAKI-LAKI' => 'Laki-Laki',
            '2', 'P', 'Perempuan', 'PEREMPUAN' => 'Perempuan',
            default => $this->stringValue($merged['jenis_kelamin'] ?? null),
        };

        // Agama
        $agama = $this->stringValue($merged['agama'] ?? $merged['agama_nama'] ?? null);

        // Pendidikan
        $pendidikan = $this->stringValue($merged['pendidikan_terakhir_nama'] ?? $merged['tingkat_pendidikan_nama'] ?? null);

        // Tanggal surat hari ini
        $tanggalSurat = Carbon::now()->translatedFormat('d F Y');
        $tahun = Carbon::now()->format('Y');

        return [
            'NAMA' => $profile->nama ?? '-',
            'NIP' => $profile->nip ?? '-',
            'PANGKAT_GOLONGAN' => $pangkatGolongan ?? '-',
            'PANGKAT' => $pangkat ?? '-',
            'GOLONGAN' => $golongan ?? '-',
            'JABATAN' => $profile->jabatan ?? '-',
            'JENIS_JABATAN' => $profile->jenis_jabatan ?? '-',
            'UNIT_KERJA' => $profile->unit_organisasi ?? '-',
            'UNIT_KERJA_INDUK' => $profile->unit_organisasi_induk ?? '-',
            'INSTANSI' => $profile->instansi_kerja ?? '-',
            'SATUAN_KERJA' => $profile->satuan_kerja ?? '-',
            'LOKASI_KERJA' => $profile->lokasi_kerja ?? '-',
            'TEMPAT_LAHIR' => $tempatLahir ?? '-',
            'TANGGAL_LAHIR' => $tanggalLahir ?? '-',
            'TTL' => $ttl ?? '-',
            'ALAMAT' => $alamat ?? '-',
            'JENIS_KELAMIN' => $jenisKelamin ?? '-',
            'AGAMA' => $agama ?? '-',
            'PENDIDIKAN' => $pendidikan ?? '-',
            'TANGGAL' => $tanggalSurat,
            'TAHUN' => $tahun,
            'JENIS_ASN' => $profile->jenis_asn ?? '-',

            // Data Penandatangan (Kepala BKPSDM)
            // Hardcoded untuk saat ini, idealnya diambil dari database/settings
            'KEPALA_NAMA' => 'TOTOK AGUS DARYANTO, M.Pd',
            'KEPALA_NIP' => '19670814 199001 1 001',
            'KEPALA_PANGKAT' => 'Pembina Utama Muda (IV/c)',
            'KEPALA_JABATAN' => 'Kepala Badan Kepegawaian Daerah, Pendidikan dan Pelatihan Kota Banjarmasin',
        ];
    }

    /**
     * Generate file Word dari template menggunakan PHPWord TemplateProcessor.
     */
    private function generateDocument(string $templatePath, array $templateData, SiasnPnsProfile $profile, array $kategoriConfig): string
    {
        $template = new TemplateProcessor($templatePath);

        // Replace semua placeholder ${VARIABLE} dengan data pegawai
        foreach ($templateData as $key => $value) {
            $template->setValue($key, $value ?? '-');
        }

        // Simpan ke file temporary
        $outputDir = storage_path('app/temp');
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $outputPath = $outputDir . '/super-hukdis-' . time() . '-' . uniqid() . '.docx';
        $template->saveAs($outputPath);

        return $outputPath;
    }

    // ─── Helper: Token SIASN dari Session ───────────────────────────

    /**
     * Ambil token SIASN yang tersimpan di session.
     * Reuse pattern dari SiasnProfileController.
     */
    private function storedSiasnToken(Request $request): ?array
    {
        $token = (string) $request->session()->get('siasn_token', '');
        if ($token === '') {
            return null;
        }

        $expiresAt = $request->session()->get('siasn_token_expires_at');
        if ($expiresAt !== null && (int) $expiresAt <= now()->timestamp) {
            $this->forgetExpiredSiasnToken($request);

            return null;
        }

        return [
            'token' => $token,
            'expires_at_text' => $request->session()->get('siasn_token_expires_at_text'),
            'identity' => $request->session()->get('siasn_token_identity', 'pengguna SIASN'),
        ];
    }

    /**
     * Hapus token SIASN yang sudah expired dari session.
     */
    private function forgetExpiredSiasnToken(Request $request): void
    {
        $expiresAt = $request->session()->get('siasn_token_expires_at');
        if ($expiresAt !== null && (int) $expiresAt <= now()->timestamp) {
            $request->session()->forget([
                'siasn_token',
                'siasn_token_expires_at',
                'siasn_token_expires_at_text',
                'siasn_token_identity',
            ]);
        }
    }

    /**
     * Ambil string value yang bersih dari data SIASN.
     */
    private function stringValue(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === '-') {
            return null;
        }

        if (is_array($value)) {
            return $value['nama'] ?? $value['name'] ?? null;
        }

        $str = trim((string) $value);

        return $str !== '' && $str !== '-' ? $str : null;
    }
}
