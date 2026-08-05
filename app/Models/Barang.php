<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Barang extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'barang';

    protected $guarded = ['id'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['jenis_barang_id', 'nomer_seri', 'barcode', 'nama_barang', 'harga_jual', 'satuan'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected static function booted(): void
    {
        static::creating(function (Barang $barang) {
            if (empty($barang->nomer_seri) && !empty($barang->jenis_barang_id)) {
                $jenis = JenisBarang::find($barang->jenis_barang_id);
                if ($jenis) {
                    $barang->nomer_seri = $jenis->generateNextNomerSeri();
                }
            }

            if (empty($barang->barcode)) {
                $barang->barcode = $barang->nomer_seri;
            }
        });

        static::updating(function (Barang $barang) {
            if (empty($barang->nomer_seri) && !empty($barang->jenis_barang_id)) {
                $jenis = JenisBarang::find($barang->jenis_barang_id);
                if ($jenis) {
                    $barang->nomer_seri = $jenis->generateNextNomerSeri();
                }
            }

            if (empty($barang->barcode)) {
                $barang->barcode = $barang->nomer_seri;
            }
        });
    }

    public function jenisBarang()
    {
        return $this->belongsTo(JenisBarang::class, 'jenis_barang_id');
    }

    public function gudangs()
    {
        return $this->belongsToMany(Gudang::class, 'barang_gudang')
            ->withPivot('stok')
            ->withTimestamps();
    }

    /**
     * Hitung faktor konversi satuan terhadap Level 1 (Satuan Dasar) secara berantai.
     */
    public function getFaktorKonversi(?string $namaSatuan): int
    {
        if (empty($namaSatuan) || $namaSatuan === $this->satuan) {
            return 1;
        }

        $isi2 = max(1, (int) ($this->isi_satuan_2 ?? 1));
        $isi3 = max(1, (int) ($this->isi_satuan_3 ?? 1));
        $isi4 = max(1, (int) ($this->isi_satuan_4 ?? 1));

        if (!empty($this->satuan_2) && $namaSatuan === $this->satuan_2) {
            return $isi2;
        }

        if (!empty($this->satuan_3) && $namaSatuan === $this->satuan_3) {
            return $isi3 * $isi2;
        }

        if (!empty($this->satuan_4) && $namaSatuan === $this->satuan_4) {
            return $isi4 * $isi3 * $isi2;
        }

        return 1;
    }

    /**
     * Dapatkan harga jual per-satuan (khusus/grosir jika terisi, atau perkalian dari Level 1).
     */
    public function getHargaJualForSatuan(?string $namaSatuan): int
    {
        if (empty($namaSatuan) || $namaSatuan === $this->satuan) {
            return (int) $this->harga_jual;
        }

        if (!empty($this->satuan_2) && $namaSatuan === $this->satuan_2) {
            return filled($this->harga_jual_2) ? (int) $this->harga_jual_2 : (int) ($this->harga_jual * $this->getFaktorKonversi($this->satuan_2));
        }

        if (!empty($this->satuan_3) && $namaSatuan === $this->satuan_3) {
            return filled($this->harga_jual_3) ? (int) $this->harga_jual_3 : (int) ($this->harga_jual * $this->getFaktorKonversi($this->satuan_3));
        }

        if (!empty($this->satuan_4) && $namaSatuan === $this->satuan_4) {
            return filled($this->harga_jual_4) ? (int) $this->harga_jual_4 : (int) ($this->harga_jual * $this->getFaktorKonversi($this->satuan_4));
        }

        return (int) $this->harga_jual;
    }

    /**
     * Dapatkan harga beli per-satuan (khusus jika terisi, atau perkalian dari Level 1).
     */
    public function getHargaBeliForSatuan(?string $namaSatuan): int
    {
        if (empty($namaSatuan) || $namaSatuan === $this->satuan) {
            return (int) $this->harga_beli;
        }

        if (!empty($this->satuan_2) && $namaSatuan === $this->satuan_2) {
            return filled($this->harga_beli_2) ? (int) $this->harga_beli_2 : (int) ($this->harga_beli * $this->getFaktorKonversi($this->satuan_2));
        }

        if (!empty($this->satuan_3) && $namaSatuan === $this->satuan_3) {
            return filled($this->harga_beli_3) ? (int) $this->harga_beli_3 : (int) ($this->harga_beli * $this->getFaktorKonversi($this->satuan_3));
        }

        if (!empty($this->satuan_4) && $namaSatuan === $this->satuan_4) {
            return filled($this->harga_beli_4) ? (int) $this->harga_beli_4 : (int) ($this->harga_beli * $this->getFaktorKonversi($this->satuan_4));
        }

        return (int) $this->harga_beli;
    }

    /**
     * Daftar seluruh level satuan aktif yang dimiliki barang ini.
     */
    public function getAvailableUnits(): array
    {
        $units = [];

        $baseSatuan = $this->satuan ?? 'Pcs';

        $units[] = [
            'level' => 1,
            'satuan' => $baseSatuan,
            'faktor' => 1,
            'isi_info' => null,
            'harga_jual' => (int) $this->harga_jual,
            'harga_beli' => (int) $this->harga_beli,
        ];

        if (!empty($this->satuan_2)) {
            $isi2 = max(1, (int) ($this->isi_satuan_2 ?? 1));
            $units[] = [
                'level' => 2,
                'satuan' => $this->satuan_2,
                'faktor' => $this->getFaktorKonversi($this->satuan_2),
                'isi_info' => "1 {$this->satuan_2} = {$isi2} {$baseSatuan}",
                'harga_jual' => $this->getHargaJualForSatuan($this->satuan_2),
                'harga_beli' => $this->getHargaBeliForSatuan($this->satuan_2),
            ];
        }

        if (!empty($this->satuan_3)) {
            $isi3 = max(1, (int) ($this->isi_satuan_3 ?? 1));
            $faktor3 = $this->getFaktorKonversi($this->satuan_3);
            $isiStr = !empty($this->satuan_2)
                ? "1 {$this->satuan_3} = {$isi3} {$this->satuan_2} ({$faktor3} {$baseSatuan})"
                : "1 {$this->satuan_3} = {$faktor3} {$baseSatuan}";

            $units[] = [
                'level' => 3,
                'satuan' => $this->satuan_3,
                'faktor' => $faktor3,
                'isi_info' => $isiStr,
                'harga_jual' => $this->getHargaJualForSatuan($this->satuan_3),
                'harga_beli' => $this->getHargaBeliForSatuan($this->satuan_3),
            ];
        }

        if (!empty($this->satuan_4)) {
            $isi4 = max(1, (int) ($this->isi_satuan_4 ?? 1));
            $faktor4 = $this->getFaktorKonversi($this->satuan_4);
            $prevSat = $this->satuan_3 ?? $this->satuan_2 ?? $baseSatuan;
            $isiStr = "1 {$this->satuan_4} = {$isi4} {$prevSat} ({$faktor4} {$baseSatuan})";

            $units[] = [
                'level' => 4,
                'satuan' => $this->satuan_4,
                'faktor' => $faktor4,
                'isi_info' => $isiStr,
                'harga_jual' => $this->getHargaJualForSatuan($this->satuan_4),
                'harga_beli' => $this->getHargaBeliForSatuan($this->satuan_4),
            ];
        }

        return $units;
    }

    /**
     * Format jumlah stok dasar (misal Pcs) menjadi rincian satuan berantai.
     * Contoh: 1255 pcs -> "1 Bal, 2 Slof, 5 Pack, 5 Pcs"
     */
    public function formatStokBerantai(int $stok): string
    {
        $baseSatuan = $this->satuan ?? 'Pcs';
        if ($stok <= 0) {
            return "0 {$baseSatuan}";
        }

        $units = $this->getAvailableUnits();
        usort($units, fn($a, $b) => $b['faktor'] <=> $a['faktor']);

        $sisa = $stok;
        $parts = [];

        foreach ($units as $u) {
            $faktor = $u['faktor'];
            if ($faktor > 1 && $sisa >= $faktor) {
                $qty = (int) floor($sisa / $faktor);
                $sisa %= $faktor;
                $parts[] = "{$qty} {$u['satuan']}";
            } elseif ($faktor === 1 && ($sisa > 0 || empty($parts))) {
                $parts[] = "{$sisa} {$u['satuan']}";
                $sisa = 0;
            }
        }

        return empty($parts) ? "0 {$baseSatuan}" : implode(', ', $parts);
    }
}
