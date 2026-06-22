# Peta Jabatan Static

Versi statis untuk menampilkan `storage/scraping/tpp_peta_jabatan_real.json` di Vercel.

## Jalankan Lokal

```bash
cd peta-jabatan-static
npm install
npm start
```

## Deploy ke Vercel

1. Import folder `peta-jabatan-static` sebagai project Vercel.
2. Set environment variable:
   - `BASIC_AUTH_USER`
   - `BASIC_AUTH_PASSWORD`
3. Deploy.

Jika environment variable belum diisi, middleware memakai default:

```text
admin / ubah-password-ini
```

Ganti password sebelum dipakai publik.

## Update Data

Salin ulang data terbaru:

```powershell
Copy-Item ..\storage\scraping\tpp_peta_jabatan_real.json .\data\tpp_peta_jabatan_real.json -Force
```
