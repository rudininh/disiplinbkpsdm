<?php

namespace Tests\Feature;

use App\Models\SiasnAbsensiLocationEmployee;
use App\Models\SiasnPnsProfile;
use App\Services\SiasnProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

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
                'token "' . $token . '"',
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
}
