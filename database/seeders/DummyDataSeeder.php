<?php

namespace Database\Seeders;

use App\Models\Barang;
use App\Models\Gudang;
use App\Models\JenisBarang;
use App\Models\Karyawan;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DummyDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed 5 Gudang
        $gudangs = [
            ['nama_gudang' => 'Gudang Utama (Pusat)', 'alamat' => 'Jl. Raya Industri No. 12, Surabaya'],
            ['nama_gudang' => 'Gudang Cabang Barat', 'alamat' => 'Jl. Darmo Permai No. 45, Surabaya'],
            ['nama_gudang' => 'Gudang Transit Logistik', 'alamat' => 'Jl. Ahmad Yani No. 88, Sidoarjo'],
            ['nama_gudang' => 'Gudang Retur & Garansi', 'alamat' => 'Jl. Gatot Subroto No. 15, Malang'],
            ['nama_gudang' => 'Gudang Depo Timur', 'alamat' => 'Jl. Pemuda No. 30, Pasuruan'],
        ];

        foreach ($gudangs as $g) {
            Gudang::firstOrCreate(['nama_gudang' => $g['nama_gudang']], $g);
        }

        // 2. Seed 5 Jenis Barang & 5 Barang per Jenis (Total 25 Barang, tanpa stok)
        $categories = [
            [
                'nama_jenis' => 'Rokok',
                'kode_jenis' => 'ROK',
                'deskripsi' => 'Segala jenis produk rokok kretek dan filter',
                'items' => [
                    ['nama_barang' => 'Garam Gudang Filter 12', 'satuan' => 'Pack', 'harga_beli' => 22000, 'harga_jual' => 24000],
                    ['nama_barang' => 'Sampoerna Mild 16', 'satuan' => 'Pack', 'harga_beli' => 28000, 'harga_jual' => 30000],
                    ['nama_barang' => 'Djarum Super 12', 'satuan' => 'Pack', 'harga_beli' => 21000, 'harga_jual' => 23000],
                    ['nama_barang' => 'Marlboro Red 20', 'satuan' => 'Pack', 'harga_beli' => 38000, 'harga_jual' => 41000],
                    ['nama_barang' => 'Surya Professional 16', 'satuan' => 'Pack', 'harga_beli' => 27000, 'harga_jual' => 29000],
                ],
            ],
            [
                'nama_jenis' => 'Minuman Kemasan',
                'kode_jenis' => 'MNM',
                'deskripsi' => 'Minuman bersoda, jus, dan air mineral',
                'items' => [
                    ['nama_barang' => 'Teh Botol Sosro 450ml', 'satuan' => 'Botol', 'harga_beli' => 4000, 'harga_jual' => 5500],
                    ['nama_barang' => 'Le Minerale 600ml', 'satuan' => 'Botol', 'harga_beli' => 2500, 'harga_jual' => 3500],
                    ['nama_barang' => 'Coca Cola 390ml', 'satuan' => 'Botol', 'harga_beli' => 4500, 'harga_jual' => 6000],
                    ['nama_barang' => 'Pocari Sweat 500ml', 'satuan' => 'Botol', 'harga_beli' => 6000, 'harga_jual' => 8000],
                    ['nama_barang' => 'Ultra Milk Cokelat 250ml', 'satuan' => 'Kotak', 'harga_beli' => 5500, 'harga_jual' => 7000],
                ],
            ],
            [
                'nama_jenis' => 'Makanan Ringan',
                'kode_jenis' => 'MKN',
                'deskripsi' => 'Snack, biskuit, dan kripik kemasan',
                'items' => [
                    ['nama_barang' => 'Chitato Sapi Panggang 68g', 'satuan' => 'Bungkus', 'harga_beli' => 9500, 'harga_jual' => 11500],
                    ['nama_barang' => 'Oreo Original 119.6g', 'satuan' => 'Bungkus', 'harga_beli' => 8000, 'harga_jual' => 10000],
                    ['nama_barang' => 'Indomie Goreng Original 85g', 'satuan' => 'Bungkus', 'harga_beli' => 2800, 'harga_jual' => 3500],
                    ['nama_barang' => 'Silverqueen Milk Chocolate 58g', 'satuan' => 'Batang', 'harga_beli' => 13500, 'harga_jual' => 16000],
                    ['nama_barang' => 'Taro Net Rumput Laut 36g', 'satuan' => 'Bungkus', 'harga_beli' => 4000, 'harga_jual' => 5000],
                ],
            ],
            [
                'nama_jenis' => 'Bumbu & Sembako',
                'kode_jenis' => 'SMB',
                'deskripsi' => 'Bumbu dapur, minyak goreng, dan tepung',
                'items' => [
                    ['nama_barang' => 'Minyak Goreng Bimoli 2 Liter', 'satuan' => 'Pouch', 'harga_beli' => 34000, 'harga_jual' => 38000],
                    ['nama_barang' => 'Gula Pasir Gulaku 1kg', 'satuan' => 'Bungkus', 'harga_beli' => 15500, 'harga_jual' => 17500],
                    ['nama_barang' => 'Beras Setra Ramos 5kg', 'satuan' => 'Karung', 'harga_beli' => 68000, 'harga_jual' => 74000],
                    ['nama_barang' => 'Garam Cap Kapal 500g', 'satuan' => 'Bungkus', 'harga_beli' => 3000, 'harga_jual' => 4000],
                    ['nama_barang' => 'Kecap Manis Bango 520ml', 'satuan' => 'Pouch', 'harga_beli' => 21000, 'harga_jual' => 24500],
                ],
            ],
            [
                'nama_jenis' => 'Perawatan Pribadi',
                'kode_jenis' => 'PRW',
                'deskripsi' => 'Sabun, sampo, pasta gigi, dan pembersih',
                'items' => [
                    ['nama_barang' => 'Sabun Lifebuoy Red 110g', 'satuan' => 'Batang', 'harga_beli' => 4000, 'harga_jual' => 5000],
                    ['nama_barang' => 'Sampo Sunsilk Black 160ml', 'satuan' => 'Botol', 'harga_beli' => 18000, 'harga_jual' => 21000],
                    ['nama_barang' => 'Pasta Gigi Pepsodent 190g', 'satuan' => 'Tube', 'harga_beli' => 11000, 'harga_jual' => 13500],
                    ['nama_barang' => 'Sabun Cuci Tangan Dettol 245ml', 'satuan' => 'Botol', 'harga_beli' => 22000, 'harga_jual' => 26000],
                    ['nama_barang' => 'Tisu Paseo 250 Sheets', 'satuan' => 'Pack', 'harga_beli' => 14000, 'harga_jual' => 17000],
                ],
            ],
        ];

        foreach ($categories as $cat) {
            $jenis = JenisBarang::firstOrCreate(
                ['nama_jenis' => $cat['nama_jenis']],
                [
                    'kode_jenis' => $cat['kode_jenis'],
                    'deskripsi' => $cat['deskripsi'],
                ]
            );

            $allGudangs = Gudang::all();
            foreach ($cat['items'] as $item) {
                $barang = Barang::firstOrCreate(
                    [
                        'jenis_barang_id' => $jenis->id,
                        'nama_barang' => $item['nama_barang'],
                    ],
                    [
                        'satuan' => $item['satuan'],
                        'harga_beli' => $item['harga_beli'],
                        'harga_jual' => $item['harga_jual'],
                    ]
                );

                foreach ($allGudangs as $g) {
                    $barang->gudangs()->syncWithoutDetaching([$g->id => ['stok' => 100]]);
                }
            }
        }

        // 3. Seed 5 Supplier
        $suppliers = [
            ['nama_supplier' => 'PT. Gudang Garam Tbk', 'no_telepon' => '081234567801', 'alamat' => 'Kediri, Jawa Timur', 'status' => 'aktif'],
            ['nama_supplier' => 'PT. Indofood Sukses Makmur', 'no_telepon' => '081234567802', 'alamat' => 'Jakarta Selatan', 'status' => 'aktif'],
            ['nama_supplier' => 'PT. Mayora Indah Tbk', 'no_telepon' => '081234567803', 'alamat' => 'Tangerang, Banten', 'status' => 'aktif'],
            ['nama_supplier' => 'PT. Unilever Indonesia Tbk', 'no_telepon' => '081234567804', 'alamat' => 'Cikarang, Bekasi', 'status' => 'aktif'],
            ['nama_supplier' => 'CV. Sumber Makmur Sejahtera', 'no_telepon' => '081234567805', 'alamat' => 'Surabaya, Jawa Timur', 'status' => 'aktif'],
        ];

        foreach ($suppliers as $s) {
            Supplier::firstOrCreate(['nama_supplier' => $s['nama_supplier']], $s);
        }

        // 4. Seed 3 Karyawan
        $karyawans = [
            ['nama_karyawan' => 'Budi Santoso', 'email' => 'budi@pkl.com', 'password' => Hash::make('password'), 'no_telp' => '081987654321', 'alamat' => 'Jl. Pahlawan No. 10, Surabaya'],
            ['nama_karyawan' => 'Siti Aminah', 'email' => 'siti@pkl.com', 'password' => Hash::make('password'), 'no_telp' => '081987654322', 'alamat' => 'Jl. Diponegoro No. 25, Sidoarjo'],
            ['nama_karyawan' => 'Rudi Hermawan', 'email' => 'rudi@pkl.com', 'password' => Hash::make('password'), 'no_telp' => '081987654323', 'alamat' => 'Jl. Veteran No. 5, Malang'],
        ];

        foreach ($karyawans as $k) {
            Karyawan::firstOrCreate(['email' => $k['email']], $k);
        }
    }
}
