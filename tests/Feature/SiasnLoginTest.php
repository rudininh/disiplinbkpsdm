<?php

namespace Tests\Feature;

use App\Models\SiasnAbsensiLocationEmployee;
use App\Models\SiasnPnsProfile;
use App\Services\SiasnProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;
use ZipArchive;

class SiasnLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_siasn_page_shows_siasn_login_test_button(): void
    {
        $response = $this->get(route('cms.siasn.index'));

        $response
            ->assertOk()
            ->assertSeeText('Tes Login SIASN')
            ->assertSeeText('Login SIASN')
            ->assertSeeText('Disiplin BKPSDM')
            ->assertSeeText('Absensi CMS')
            ->assertSeeText('Laporan Cuti')
            ->assertSeeText('SIASN Profil ASN')
            ->assertSeeText('Referensi Unit Kerja Dikdas')
            ->assertSeeText('Sinkron Pegawai Absensi')
            ->assertSeeText('Cek Semua SIASN')
            ->assertSeeText('Cek Data Excel ke SIASN')
            ->assertSeeText('TK NEGERI PEMBINA BANJARMASIN TIMUR 2')
            ->assertSeeText('SD NEGERI ALALAK SELATAN 2')
            ->assertSeeText('SMP Negeri 35 Banjarmasin')
            ->assertDontSeeText('Data Tersimpan')
            ->assertDontSeeText('Ambil Data Per NIP')
            ->assertDontSeeText('Sinkron Lokasi Dinas Pendidikan')
            ->assertDontSeeText('Database SIASN Lokal');
    }

    public function test_siasn_unit_kerja_reference_data_has_expected_total_and_order(): void
    {
        $path = base_path('resources/data/siasn-unit-kerja-dikdas-banjarmasin.json');
        $payload = json_decode((string) file_get_contents($path), true);

        $this->assertCount(256, $payload['rows']);
        $this->assertSame(4, $payload['summary']['tk']);
        $this->assertSame(213, $payload['summary']['sd_sederajat']);
        $this->assertSame(39, $payload['summary']['smp_sederajat']);
        $this->assertSame('TK', $payload['rows'][0]['jenjang']);
        $this->assertSame('TK NEGERI PEMBINA BANJARMASIN TIMUR 2', $payload['rows'][0]['unit_kerja']);
        $this->assertSame('SD Sederajat', $payload['rows'][4]['jenjang']);
        $this->assertSame('SMP Sederajat', $payload['rows'][255]['jenjang']);
    }

    public function test_siasn_unit_rows_show_stored_absensi_employees(): void
    {
        SiasnAbsensiLocationEmployee::query()->create([
            'skpd_id' => 1,
            'kode_skpd' => '1.01.01.',
            'nama_skpd' => 'Dinas Pendidikan',
            'lokasi_id' => '1776',
            'lokasi_nama' => 'SDN PEKAPURAN RAYA 1',
            'lokasi_alamat' => 'Jl. Pekapuran',
            'nip' => '196905222022212001',
            'nama' => 'JAHRAH',
            'match_status' => 'lokasi_absensi_cocok',
            'row_data' => [
                'referensi_npsn' => '30304165',
                'referensi_unit_kerja' => 'SD NEGERI PEKAPURAN RAYA 1',
            ],
            'fetched_at' => now(),
        ]);

        $response = $this->get(route('cms.siasn.index'));

        $response
            ->assertOk()
            ->assertSeeText('SD NEGERI PEKAPURAN RAYA 1')
            ->assertSeeText('SDN PEKAPURAN RAYA 1')
            ->assertSeeText('JAHRAH')
            ->assertSeeText('196905222022212001');
    }

    public function test_siasn_login_test_uses_submitted_token(): void
    {
        $this->mock(SiasnProfileService::class, function (MockInterface $mock): void {
            $mock
                ->shouldReceive('testAccess')
                ->once()
                ->with('Bearer token-test', '199711282020121001')
                ->andReturn([
                    'success' => true,
                    'message' => 'Login SIASN berhasil. Profil PNS bisa diakses.',
                    'profile' => [
                        'Jenis ASN' => 'PNS',
                        'NIP' => '199711282020121001',
                        'Nama' => 'RUDINI NOR HABIBI',
                        'Jabatan' => 'Analis SDM Aparatur',
                        'Unit Organisasi' => 'BKPSDM',
                    ],
                ]);
            $mock
                ->shouldReceive('tokenInfo')
                ->once()
                ->with('Bearer token-test')
                ->andReturn([
                    'token' => 'token-test',
                    'expires_at' => null,
                    'expires_at_text' => null,
                    'nama' => 'RUDINI NOR HABIBI',
                    'nip' => '199711282020121001',
                ]);
        });

        $response = $this->followingRedirects()->post(route('cms.siasn.test-login'), [
            'nip' => '199711282020121001',
            'bearer_token' => 'Bearer token-test',
        ]);

        $response
            ->assertOk()
            ->assertSeeText('Login SIASN berhasil')
            ->assertSeeText('Profil PNS bisa diakses')
            ->assertSeeText('Profil SIASN')
            ->assertSeeText('RUDINI NOR HABIBI')
            ->assertSeeText('Analis SDM Aparatur')
            ->assertSeeText('BKPSDM')
            ->assertSessionHas('siasn_token', 'token-test')
            ->assertSeeText('Token SIASN tersimpan')
            ->assertSeeText('Hapus Token');
    }

    public function test_can_sync_all_absensi_employees_to_siasn_and_mark_missing_as_pensiun_mutasi(): void
    {
        $profile = SiasnPnsProfile::query()->create([
            'pns_id' => 'pns-1',
            'nip' => '199711282020121001',
            'jenis_asn' => 'PPPK',
            'nama' => 'PEGAWAI AKTIF',
            'jabatan' => 'Guru',
            'unit_organisasi' => 'SD NEGERI AKTIF',
            'fetched_at' => now(),
        ]);

        SiasnAbsensiLocationEmployee::query()->create([
            'skpd_id' => 1,
            'kode_skpd' => '1.01.01.',
            'nama_skpd' => 'Dinas Pendidikan',
            'lokasi_id' => '100',
            'lokasi_nama' => 'SDN AKTIF',
            'nip' => '199711282020121001',
            'nama' => 'PEGAWAI AKTIF',
            'match_status' => 'lokasi_absensi_cocok',
            'row_data' => ['referensi_npsn' => '30304165'],
            'fetched_at' => now(),
        ]);

        SiasnAbsensiLocationEmployee::query()->create([
            'skpd_id' => 1,
            'kode_skpd' => '1.01.01.',
            'nama_skpd' => 'Dinas Pendidikan',
            'lokasi_id' => '101',
            'lokasi_nama' => 'SDN MUTASI',
            'nip' => '198001012020121001',
            'nama' => 'PEGAWAI MUTASI',
            'match_status' => 'lokasi_absensi_cocok',
            'row_data' => ['referensi_npsn' => '30304165'],
            'fetched_at' => now(),
        ]);

        $this->mock(SiasnProfileService::class, function (MockInterface $mock) use ($profile): void {
            $mock
                ->shouldReceive('fetchAndStore')
                ->once()
                ->with('199711282020121001', 'stored-token')
                ->andReturn([
                    'success' => true,
                    'profile' => $profile,
                ]);

            $mock
                ->shouldReceive('fetchAndStore')
                ->once()
                ->with('198001012020121001', 'stored-token')
                ->andThrow(new \RuntimeException('Data PNS/PPPK tidak ditemukan dari SIASN untuk NIP tersebut.'));
        });

        $response = $this
            ->withSession(['siasn_token' => 'stored-token'])
            ->followingRedirects()
            ->post(route('cms.siasn.sync-all-absensi-employees-siasn'));

        $response
            ->assertOk()
            ->assertSeeText('1 aktif tersinkron')
            ->assertSeeText('1 ditandai PENSIUN/MUTASI');

        $active = SiasnAbsensiLocationEmployee::query()
            ->where('nip', '199711282020121001')
            ->firstOrFail();
        $missing = SiasnAbsensiLocationEmployee::query()
            ->where('nip', '198001012020121001')
            ->firstOrFail();

        $this->assertSame($profile->id, $active->siasn_pns_profile_id);
        $this->assertSame('SD NEGERI AKTIF', $active->siasn_unit_organisasi);
        $this->assertSame('PENSIUN/MUTASI', $missing->row_data['siasn_status'] ?? null);
        $this->assertNull($missing->siasn_pns_profile_id);
    }

    public function test_can_sync_pns_excel_to_siasn_local_units(): void
    {
        config(['services.siasn.pns_excel_path' => $this->makePnsExcel([
            ['NIP BARU', 'NAMA', 'GOL AKHIR NAMA', 'JENIS JABATAN NAMA', 'JABATAN NAMA', 'TMT JABATAN', 'UNOR (3)', 'UNOR (2)', 'UNOR (1)'],
            ['199711282020121001', 'GURU TEST', 'III/a', 'Jabatan Fungsional', 'GURU AHLI PERTAMA', '01-02-2025', '', 'SD NEGERI PEKAPURAN RAYA 1', 'DINAS PENDIDIKAN'],
            ['198001012000011001', 'PENGAWAS TEST', 'IV/a', 'Jabatan Fungsional', 'PENGAWAS SEKOLAH AHLI MADYA', '02-02-2025', '', '', 'DINAS PENDIDIKAN'],
            ['197001012000011001', 'PENJAGA TEST', 'II/a', 'Jabatan Pelaksana', 'PENJAGA SEKOLAH', '03-02-2025', '', '', 'DINAS PENDIDIKAN'],
            ['196001012000011001', 'ANALIS TEST', 'III/a', 'Jabatan Fungsional', 'ANALIS KEBIJAKAN AHLI MUDA', '04-02-2025', '', '', 'DINAS PENDIDIKAN'],
        ])]);

        $response = $this
            ->followingRedirects()
            ->post(route('cms.siasn.sync-pns-excel-siasn'));

        $response
            ->assertOk()
            ->assertSeeText('Cek Data Excel ke SIASN selesai')
            ->assertSeeText('3 pegawai diproses')
            ->assertSeeText('GURU TEST')
            ->assertSeeText('PENGAWAS TEST')
            ->assertSeeText('PENJAGA TEST')
            ->assertSeeText('DINAS PENDIDIKAN')
            ->assertDontSeeText('ANALIS TEST');

        $profile = SiasnPnsProfile::query()
            ->where('nip', '199711282020121001')
            ->firstOrFail();
        $teacher = SiasnAbsensiLocationEmployee::query()
            ->where('nip', '199711282020121001')
            ->firstOrFail();
        $supervisor = SiasnAbsensiLocationEmployee::query()
            ->where('nip', '198001012000011001')
            ->firstOrFail();

        $this->assertSame('PNS', $profile->jenis_asn);
        $this->assertSame('GURU AHLI PERTAMA', $profile->jabatan);
        $this->assertSame('SD NEGERI PEKAPURAN RAYA 1', $teacher->siasn_unit_organisasi);
        $this->assertSame('30304165', $teacher->row_data['referensi_npsn'] ?? null);
        $this->assertSame('PENGAWAS SEKOLAH', $supervisor->row_data['excel_siasn_jabatan_kategori'] ?? null);
        $this->assertStringStartsWith('excel:', $supervisor->row_data['referensi_npsn'] ?? '');
        $this->assertLessThanOrEqual(64, strlen((string) $supervisor->lokasi_id));
        $this->assertSame(3, SiasnAbsensiLocationEmployee::query()->count());
    }

    public function test_siasn_login_test_rejects_otp_codes_before_calling_api(): void
    {
        $response = $this->followingRedirects()->post(route('cms.siasn.test-login'), [
            'bearer_token' => '186678',
        ]);

        $response
            ->assertOk()
            ->assertSeeText('kode OTP/authenticator')
            ->assertSeeText('bukan token SIASN');
    }

    public function test_siasn_login_test_rejects_sso_login_urls_before_calling_api(): void
    {
        $response = $this->followingRedirects()->post(route('cms.siasn.test-login'), [
            'bearer_token' => 'https://sso-siasn.bkn.go.id/auth/realms/public-siasn/protocol/openid-connect/auth?client_id=bkn-portal&response_type=code&code_challenge=test',
        ]);

        $response
            ->assertOk()
            ->assertSeeText('URL login SSO SIASN')
            ->assertSeeText('bukan token');
    }

    public function test_siasn_login_test_can_extract_token_cookie_without_calling_api_when_nip_is_empty(): void
    {
        Http::fake();

        $token = implode('.', [
            'eyJhbGciOiJIUzI1NiJ9',
            'eyJleHAiOjQxMDI0NDQ4MDAsInBlZ2F3YWkiOnsibmlwIjoiMTk5NzExMjgyMDIwMTIxMDAxIiwibmFtYSI6IlJ1ZGluaSJ9fQ',
            'signature',
        ]);

        $response = $this->followingRedirects()->post(route('cms.siasn.test-login'), [
            'bearer_token' => implode("\n", [
                'refresh_token "eyJrefresh.refresh.refresh"',
                'sso_refresh_token "eyJsso.refresh.refresh"',
                'token "'.$token.'"',
            ]),
        ]);

        $response
            ->assertOk()
            ->assertSeeText('Token SIASN terbaca')
            ->assertSeeText('Rudini');

        Http::assertNothingSent();
    }

    public function test_siasn_login_test_falls_back_to_pppk_profile_page_endpoint_after_pns_404(): void
    {
        $token = implode('.', [
            'eyJhbGciOiJIUzI1NiJ9',
            'eyJleHAiOjQxMDI0NDQ4MDAsInBlZ2F3YWkiOnsibmlwIjoiMTk5NzExMjgyMDIwMTIxMDAxIiwibmFtYSI6IlJ1ZGluaSJ9fQ',
            'signature',
        ]);

        Http::fake([
            'https://api-siasn.bkn.go.id/profilasn/api/pns-siasn*' => Http::response([], 404),
            'https://api-siasn.bkn.go.id/profilasn/api/pppk*' => Http::response([
                'Value' => [[
                    'id' => 'pppk-1',
                    'nip_baru' => '199711282020121001',
                    'nama' => 'PEGAWAI PPPK',
                    'jenis_jabatan_id' => '4',
                    'jabatan_fungsional_umum_nama' => ['nama' => 'Guru PPPK'],
                    'unor_nama' => 'SD NEGERI PPPK',
                ]],
            ]),
            'https://api-siasn.bkn.go.id/profilasn/api/orang*' => Http::response([
                'Value' => [[
                    'id' => 'pppk-1',
                    'nip_baru' => '199711282020121001',
                    'nama' => 'PEGAWAI PPPK',
                    'unor_nama' => 'SD NEGERI PPPK',
                ]],
            ]),
        ]);

        $response = $this->followingRedirects()->post(route('cms.siasn.test-login'), [
            'nip' => '199711282020121001',
            'bearer_token' => $token,
        ]);

        $response
            ->assertOk()
            ->assertSeeText('Profil PPPK untuk PEGAWAI PPPK bisa diakses')
            ->assertSeeText('SD NEGERI PPPK');

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/profilasn/api/pns-siasn'));
        Http::assertSent(function ($request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_contains($request->url(), '/profilasn/api/pppk')
                && ($query['nip_lama'] ?? null) === ''
                && ($query['nip_baru'] ?? null) === '199711282020121001';
        });
    }

    public function test_get_siasn_test_login_redirects_to_index(): void
    {
        $response = $this->get('/cms/siasn/test-login');

        $response->assertRedirect(route('cms.siasn.index'));
    }

    public function test_siasn_page_prefills_stored_token_until_expired(): void
    {
        $token = implode('.', [
            'eyJhbGciOiJIUzI1NiJ9',
            'eyJleHAiOjQxMDI0NDQ4MDAsInBlZ2F3YWkiOnsibmlwIjoiMTk5NzExMjgyMDIwMTIxMDAxIiwibmFtYSI6IlJ1ZGluaSJ9fQ',
            'signature',
        ]);

        $response = $this
            ->withSession([
                'siasn_token' => $token,
                'siasn_token_expires_at' => 4102444800,
                'siasn_token_expires_at_text' => '2100-01-01 00:00:00',
                'siasn_token_identity' => 'Rudini',
            ])
            ->get(route('cms.siasn.index'));

        $response
            ->assertOk()
            ->assertSeeText('Token SIASN tersimpan')
            ->assertSeeText('Rudini')
            ->assertSee($token);
    }

    public function test_expired_stored_token_is_not_prefilled(): void
    {
        $response = $this
            ->withSession([
                'siasn_token' => 'expired-token',
                'siasn_token_expires_at' => 1,
                'siasn_token_expires_at_text' => '1970-01-01 00:00:01',
                'siasn_token_identity' => 'Expired',
            ])
            ->get(route('cms.siasn.index'));

        $response
            ->assertOk()
            ->assertDontSee('expired-token')
            ->assertDontSeeText('Token SIASN tersimpan')
            ->assertSessionMissing('siasn_token');
    }

    public function test_can_forget_stored_siasn_token(): void
    {
        $response = $this
            ->withSession([
                'siasn_token' => 'stored-token',
                'siasn_token_expires_at' => 4102444800,
                'siasn_token_expires_at_text' => '2100-01-01 00:00:00',
                'siasn_token_identity' => 'Rudini',
            ])
            ->post(route('cms.siasn.forget-token'));

        $response
            ->assertRedirect(route('cms.siasn.index'))
            ->assertSessionMissing('siasn_token');
    }

    private function makePnsExcel(array $rows): string
    {
        $path = storage_path('framework/testing/pns-test-'.str_replace('.', '', uniqid('', true)).'.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('xl/workbook.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="Sheet1" sheetId="1" r:id="rId1"/>
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
        $xml = ['<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'];
        $xml[] = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
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
