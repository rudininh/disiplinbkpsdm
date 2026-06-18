<?php

namespace Tests\Feature;

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
            ->assertDontSeeText('Data Tersimpan')
            ->assertDontSeeText('Ambil Data Per NIP')
            ->assertDontSeeText('Sinkron Lokasi Dinas Pendidikan')
            ->assertDontSeeText('Database SIASN Lokal');
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
            ->assertSeeText('BKPSDM');
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

    public function test_get_siasn_test_login_redirects_to_index(): void
    {
        $response = $this->get('/cms/siasn/test-login');

        $response->assertRedirect(route('cms.siasn.index'));
    }
}
