# Peta Jabatan Static

Versi statis untuk menampilkan data gabungan peta jabatan TPP dan lowongan dari Excel di Vercel.

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

Jalankan export dari root repo Laravel:

```powershell
php artisan peta-jabatan:export-static
```
