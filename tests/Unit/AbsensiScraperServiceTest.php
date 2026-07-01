<?php

namespace Tests\Unit;

use App\Services\AbsensiScraperService;
use PHPUnit\Framework\TestCase;

class AbsensiScraperServiceTest extends TestCase
{
    public function test_extract_skpd_login_action_prefers_login_link_over_other_actions(): void
    {
        $service = new class extends AbsensiScraperService {
            public function exposeExtractSkpdLoginAction(string $html, int $skpdId): array
            {
                return $this->extractSkpdLoginAction($html, $skpdId);
            }
        };

        $html = <<<'HTML'
            <table>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td>1.01.01.</td>
                        <td>Dinas Pendidikan</td>
                        <td>Y</td>
                        <td>
                            <a href="/superadmin/skpd/reset/10">Reset Pass</a>
                            <a href="/superadmin/skpd/detail/10">Detail</a>
                            <a href="/superadmin/skpd/login/10">Login</a>
                        </td>
                    </tr>
                </tbody>
            </table>
        HTML;

        $action = $service->exposeExtractSkpdLoginAction($html, 1);

        $this->assertSame('GET', $action['method']);
        $this->assertSame('/superadmin/skpd/login/10', $action['url']);
        $this->assertSame('login_link', $action['source']);
    }

    public function test_extract_skpd_login_action_prefers_login_form_over_other_forms(): void
    {
        $service = new class extends AbsensiScraperService {
            public function exposeExtractSkpdLoginAction(string $html, int $skpdId): array
            {
                return $this->extractSkpdLoginAction($html, $skpdId);
            }
        };

        $html = <<<'HTML'
            <table>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td>1.01.01.</td>
                        <td>Dinas Pendidikan</td>
                        <td>Y</td>
                        <td>
                            <form method="POST" action="/superadmin/skpd/reset/10">
                                <input type="hidden" name="_token" value="abc">
                                <button type="submit">Reset Pass</button>
                            </form>
                            <form method="POST" action="/superadmin/skpd/login/10">
                                <input type="hidden" name="_token" value="abc">
                                <button type="submit" name="login" value="1">Login</button>
                            </form>
                        </td>
                    </tr>
                </tbody>
            </table>
        HTML;

        $action = $service->exposeExtractSkpdLoginAction($html, 1);

        $this->assertSame('POST', $action['method']);
        $this->assertSame('/superadmin/skpd/login/10', $action['url']);
        $this->assertSame('login_form', $action['source']);
        $this->assertSame(['_token' => 'abc', 'login' => '1'], $action['form_params']);
    }

    public function test_skpd_listing_page_is_not_treated_as_cuti_data(): void
    {
        $service = new class extends AbsensiScraperService {
            public function exposeIsSkpdListingPage(string $html): bool
            {
                return $this->isSkpdListingPage($html);
            }
        };

        $html = <<<'HTML'
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Kode SKPD</th>
                        <th>Nama SKPD</th>
                        <th>WFH</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td>1.01.01.</td>
                        <td>Dinas Pendidikan</td>
                        <td>Y</td>
                        <td>Reset Pass Detail Login</td>
                    </tr>
                </tbody>
            </table>
        HTML;

        $this->assertTrue($service->exposeIsSkpdListingPage($html));
    }

    public function test_skpd_login_fallback_uses_login_path(): void
    {
        $service = new class extends AbsensiScraperService {
            public function exposeExtractSkpdLoginAction(string $html, int $skpdId): array
            {
                return $this->extractSkpdLoginAction($html, $skpdId);
            }
        };

        $action = $service->exposeExtractSkpdLoginAction('<table><tbody></tbody></table>', 1);

        $this->assertSame('GET', $action['method']);
        $this->assertSame('/superadmin/skpd/1/login', $action['url']);
        $this->assertSame('fallback', $action['source']);
    }

    public function test_parse_daily_report_reads_presensi_hari_besar_column(): void
    {
        $service = new class extends AbsensiScraperService {
            public function exposeParseDailyReportHtml(string $html, int $skpdId, string $date): array
            {
                return $this->parseDailyReportHtml($html, $skpdId, $date);
            }
        };

        $html = <<<'HTML'
            <p><strong>DAFTAR HADIR PEGAWAI NEGERI SIPIL</strong></p>
            <table>
                <tr><td>HARI</td><td>: SENIN</td></tr>
                <tr><td>TANGGAL</td><td>: 29 JUNI 2026</td></tr>
            </table>
            <table>
                <tr>
                    <th>NO</th>
                    <th>NAMA / NIP</th>
                    <th>PANGKAT</th>
                    <th>JABATAN</th>
                    <th>PAGI</th>
                    <th>PULANG</th>
                    <th>APEL</th>
                    <th>PRESENSI HARI BESAR</th>
                </tr>
                <tr>
                    <td>1</td>
                    <td>Rahmasari, S.Pi<br>196811132007012013</td>
                    <td>Pembina<br>(IV/A)</td>
                    <td>Sekretaris</td>
                    <td>07:47:29</td>
                    <td>00:00:00</td>
                    <td>-</td>
                    <td>07:50:57</td>
                </tr>
            </table>
        HTML;

        $parsed = $service->exposeParseDailyReportHtml($html, 24, '2026-06-29');

        $this->assertSame(1, $parsed['row_count']);
        $this->assertSame('Rahmasari, S.Pi', $parsed['rows'][0]['nama_pegawai']);
        $this->assertSame('196811132007012013', $parsed['rows'][0]['nip']);
        $this->assertSame('-', $parsed['rows'][0]['apel']);
        $this->assertSame('07:50:57', $parsed['rows'][0]['apel_hari_besar']);
    }

    public function test_parse_lokasi_pegawai_reads_separate_name_and_nip_columns(): void
    {
        $service = new class extends AbsensiScraperService {
            public function exposeParseLokasiPegawaiHtml(string $html, array $location): array
            {
                return $this->parseLokasiPegawaiHtml($html, $location);
            }
        };

        $html = <<<'HTML'
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama</th>
                        <th>NIP</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td>JAHRAH</td>
                        <td>196905222022212001</td>
                        <td>-</td>
                    </tr>
                </tbody>
            </table>
        HTML;

        $parsed = $service->exposeParseLokasiPegawaiHtml($html, [
            'lokasi_id' => '1777',
            'nama' => 'SDN SURGI MUFTI 4',
            'alamat' => 'Jl. Surgi Mufti',
            'lat' => '-3.3',
            'long' => '114.6',
        ]);

        $this->assertSame(1, $parsed['row_count']);
        $this->assertSame('196905222022212001', $parsed['rows'][0]['nip']);
        $this->assertSame('JAHRAH', $parsed['rows'][0]['nama']);
        $this->assertSame('1777', $parsed['rows'][0]['lokasi_id']);
        $this->assertSame('SDN SURGI MUFTI 4', $parsed['rows'][0]['lokasi_nama']);
    }

    public function test_parse_lokasi_pegawai_reads_combined_employee_column(): void
    {
        $service = new class extends AbsensiScraperService {
            public function exposeParseLokasiPegawaiHtml(string $html, array $location): array
            {
                return $this->parseLokasiPegawaiHtml($html, $location);
            }
        };

        $html = <<<'HTML'
            <table>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td>JAHRAH<br>196905222022212001</td>
                    </tr>
                </tbody>
            </table>
        HTML;

        $parsed = $service->exposeParseLokasiPegawaiHtml($html, [
            'lokasi_id' => '1777',
            'nama' => 'SDN SURGI MUFTI 4',
        ]);

        $this->assertSame(1, $parsed['row_count']);
        $this->assertSame('196905222022212001', $parsed['rows'][0]['nip']);
        $this->assertSame('JAHRAH', $parsed['rows'][0]['nama']);
    }

    public function test_parse_admin_pegawai_reads_pppk_paruh_waktu_rows(): void
    {
        $service = new class extends AbsensiScraperService {
            public function exposeParseAdminPegawaiPppkParuhWaktuHtml(string $html, int $skpdId): array
            {
                return $this->parseAdminPegawaiPppkParuhWaktuHtml($html, $skpdId);
            }
        };

        $html = <<<'HTML'
            <table>
                <tbody>
                    <tr>
                        <td>61</td>
                        <td>MUHAMMAD THOYIB, S.P.<br>197110262025211024<br>PENATA LAYANAN OPERASIONAL</td>
                        <td></td>
                        <td>01-07-2026</td>
                        <td>5 Hari Kerja <a href="/admin/pegawai/61/presensi">Presensi</a></td>
                        <td><a href="/admin/pegawai/61/edit">Edit</a></td>
                        <td>PPPK PARUH WAKTU</td>
                    </tr>
                    <tr>
                        <td>69</td>
                        <td>Drs. ZAINAL HAKIM<br>196411071992031012</td>
                        <td>Pembina Tingkat 1<br>IV/B</td>
                        <td>07-11-1964</td>
                        <td>5 Hari Kerja</td>
                        <td>STATUS : PENSIUN</td>
                        <td>PNS</td>
                    </tr>
                </tbody>
            </table>
        HTML;

        $parsed = $service->exposeParseAdminPegawaiPppkParuhWaktuHtml($html, 6);

        $this->assertSame(1, $parsed['row_count']);
        $this->assertSame('197110262025211024', $parsed['rows'][0]['nip']);
        $this->assertSame('MUHAMMAD THOYIB, S.P.', $parsed['rows'][0]['nama']);
        $this->assertSame('PENATA LAYANAN OPERASIONAL', $parsed['rows'][0]['jabatan']);
        $this->assertSame('PPPK PARUH WAKTU', $parsed['rows'][0]['status_asn']);
        $this->assertSame('pegawai:61', $parsed['rows'][0]['pppk_id']);
        $this->assertSame('/admin/pegawai/61/presensi', $parsed['rows'][0]['presensi_url']);
    }

    public function test_parse_admin_pegawai_reads_pns_and_pppk_rows(): void
    {
        $service = new class extends AbsensiScraperService {
            public function exposeParseAdminPegawaiHtml(string $html, int $skpdId): array
            {
                return $this->parseAdminPegawaiHtml($html, $skpdId);
            }
        };

        $html = <<<'HTML'
            <table>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td>ASN PNS<br>198001012006041001<br>PERAWAT AHLI MUDA</td>
                        <td>Penata<br>III/C</td>
                        <td>01-01-1980</td>
                        <td>Shift <a href="/admin/pegawai/100/presensi">Presensi</a></td>
                        <td><a href="/admin/pegawai/100/edit">Edit</a></td>
                        <td>PNS</td>
                    </tr>
                    <tr>
                        <td>2</td>
                        <td>ASN PPPK<br>199001012025212001<br>PENATA LAYANAN OPERASIONAL</td>
                        <td></td>
                        <td>01-01-1990</td>
                        <td>5 Hari Kerja <a href="/admin/pegawai/101/presensi">Presensi</a></td>
                        <td><a href="/admin/pegawai/101/edit">Edit</a></td>
                        <td>PPPK PARUH WAKTU</td>
                    </tr>
                    <tr>
                        <td>3</td>
                        <td>ASN PENSIUN<br>196001012000041001<br>DOKTER</td>
                        <td>Pembina</td>
                        <td>01-01-1960</td>
                        <td>5 Hari Kerja</td>
                        <td>STATUS : PENSIUN</td>
                        <td>PNS</td>
                    </tr>
                </tbody>
            </table>
        HTML;

        $parsed = $service->exposeParseAdminPegawaiHtml($html, 3208);

        $this->assertSame(2, $parsed['row_count']);
        $this->assertSame('198001012006041001', $parsed['rows'][0]['nip']);
        $this->assertSame('PNS', $parsed['rows'][0]['status_asn']);
        $this->assertSame('199001012025212001', $parsed['rows'][1]['nip']);
        $this->assertSame('PPPK PARUH WAKTU', $parsed['rows'][1]['status_asn']);
    }

    public function test_pppk_presensi_path_uses_admin_pegawai_for_paruh_waktu(): void
    {
        $service = new class extends AbsensiScraperService {
            public function exposePppkPresensiPath(array $person, string $month, string $year): ?string
            {
                return $this->pppkPresensiPath($person, $month, $year);
            }
        };

        $path = $service->exposePppkPresensiPath([
            'pppk_id' => 'pegawai:61',
            'presensi_url' => '/admin/pegawai/61/presensi',
        ], '7', '2026');

        $this->assertSame('/admin/pegawai/61/presensi/07/2026', $path);
    }

    public function test_extract_puskesmas_login_action_finds_masuk_link(): void
    {
        $service = new class extends AbsensiScraperService {
            public function exposeExtractPuskesmasLoginAction(string $html, array $unit): array
            {
                return $this->extractPuskesmasLoginAction($html, $unit);
            }
        };

        $html = <<<'HTML'
            <table>
                <tbody>
                    <tr>
                        <td>3</td>
                        <td>1.02.01.8</td>
                        <td>Rumah Sakit Sultan Suriansyah</td>
                        <td>
                            <a href="/admin/puskesmas/3/reset">Reset Pass</a>
                            <a href="/admin/puskesmas/3/password">Ganti Pass</a>
                            <a href="/admin/puskesmas/3/masuk">Masuk</a>
                        </td>
                    </tr>
                </tbody>
            </table>
        HTML;

        $action = $service->exposeExtractPuskesmasLoginAction($html, [
            'kode' => '1.02.01.8',
            'nama' => 'Rumah Sakit Sultan Suriansyah',
        ]);

        $this->assertSame('GET', $action['method']);
        $this->assertSame('/admin/puskesmas/3/masuk', $action['url']);
        $this->assertSame('puskesmas_link', $action['source']);
    }
}
