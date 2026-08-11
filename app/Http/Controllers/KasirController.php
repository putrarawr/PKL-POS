<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\DetailJual;
use App\Models\Gudang;
use App\Models\JenisBarang;
use App\Models\KartuStok;
use App\Models\Karyawan;
use App\Models\Penjualan;
use App\Services\StokService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class KasirController extends Controller
{
    /**
     * Tampilkan halaman login kasir.
     */
    /**
     * Tampilkan halaman login kasir.
     */
    public function showLogin()
    {
        if (Auth::guard('karyawan')->check() || Auth::guard('web')->check()) {
            return redirect()->route('kasir');
        }

        return view('kasir-login');
    }

    /**
     * Proses login kasir.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // 1. Coba login sebagai Karyawan
        if (Auth::guard('karyawan')->attempt(['email' => $credentials['email'], 'password' => $credentials['password']], $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended(route('kasir'));
        }

        // 2. Jika gagal, coba login sebagai Admin (User)
        $loginField = filter_var($credentials['email'], FILTER_VALIDATE_EMAIL) ? 'email' : 'name';
        if (Auth::guard('web')->attempt([$loginField => $credentials['email'], 'password' => $credentials['password']], $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended(route('kasir'));
        }

        return back()->withErrors([
            'email' => 'Email/username atau password salah.',
        ])->onlyInput('email');
    }

    /**
     * Logout kasir.
     */
    public function logout(Request $request)
    {
        Auth::guard('karyawan')->logout();
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('kasir.login');
    }

    /**
     * Halaman kasir. Data langsung disuntik ke Blade (tanpa API),
     * nanti dibaca JavaScript lewat window.KASIR_DATA.
     */
    public function index()
    {
        $karyawanInfo = null;
        if ($karyawan = Auth::guard('karyawan')->user()) {
            $karyawanInfo = [
                'id' => $karyawan->id_karyawan,
                'nama' => $karyawan->nama_karyawan,
                'email' => $karyawan->email,
                'type' => 'karyawan',
            ];
        } elseif ($user = Auth::guard('web')->user()) {
            $karyawanInfo = [
                'id' => $user->id,
                'nama' => $user->name,
                'email' => $user->email,
                'type' => 'user',
            ];
        }

        return view('kasir', [
            'kasirData' => [
                'karyawan' => $karyawanInfo,
                'barang' => Barang::with('gudangs')->get()->map(fn ($b) => [
                    'id' => $b->id,
                    'jenis_barang_id' => $b->jenis_barang_id,
                    'nama_barang' => $b->nama_barang,
                    'harga_jual' => (int) $b->harga_jual,
                    'harga_beli' => (int) $b->harga_beli,
                    'tipe_harga_bertingkat' => $b->tipe_harga_bertingkat ?? 'persen',
                    'min_qty_1' => filled($b->min_qty_1) ? (int) $b->min_qty_1 : null,
                    'nilai_tier_1' => (float) ($b->nilai_tier_1 ?? 0),
                    'min_qty_2' => filled($b->min_qty_2) ? (int) $b->min_qty_2 : null,
                    'nilai_tier_2' => (float) ($b->nilai_tier_2 ?? 0),
                    'min_qty_3' => filled($b->min_qty_3) ? (int) $b->min_qty_3 : null,
                    'nilai_tier_3' => (float) ($b->nilai_tier_3 ?? 0),
                    'satuan' => $b->satuan ?? 'Pcs',
                    'units' => $b->getAvailableUnits(),
                    // stok per gudang dari pivot barang_gudang: { gudang_id: jumlah_dasar }
                    'stok' => $b->gudangs->mapWithKeys(fn ($g) => [$g->id => (int) $g->pivot->stok]),
                ]),
                'jenisBarang' => JenisBarang::all(['id', 'nama_jenis']),
                'gudang' => Gudang::all(['id', 'nama_gudang', 'alamat']),
            ],
        ]);
    }

    /**
     * Endpoint API JSON untuk mengambil data produk & stok terbaru secara live.
     */
    public function data()
    {
        return response()->json([
            'barang' => Barang::with('gudangs')->get()->map(fn ($b) => [
                'id' => $b->id,
                'jenis_barang_id' => $b->jenis_barang_id,
                'nama_barang' => $b->nama_barang,
                'harga_jual' => (int) $b->harga_jual,
                'harga_beli' => (int) $b->harga_beli,
                'tipe_harga_bertingkat' => $b->tipe_harga_bertingkat ?? 'persen',
                'min_qty_1' => filled($b->min_qty_1) ? (int) $b->min_qty_1 : null,
                'nilai_tier_1' => (float) ($b->nilai_tier_1 ?? 0),
                'min_qty_2' => filled($b->min_qty_2) ? (int) $b->min_qty_2 : null,
                'nilai_tier_2' => (float) ($b->nilai_tier_2 ?? 0),
                'min_qty_3' => filled($b->min_qty_3) ? (int) $b->min_qty_3 : null,
                'nilai_tier_3' => (float) ($b->nilai_tier_3 ?? 0),
                'satuan' => $b->satuan ?? 'Pcs',
                'units' => $b->getAvailableUnits(),
                'stok' => $b->gudangs->mapWithKeys(fn ($g) => [$g->id => (int) $g->pivot->stok]),
            ]),
            'jenisBarang' => JenisBarang::all(['id', 'nama_jenis']),
            'gudang' => Gudang::all(['id', 'nama_gudang', 'alamat']),
        ])
        ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
        ->header('Pragma', 'no-cache')
        ->header('Expires', '0');
    }

    /**
     * Riwayat transaksi (default: hari ini). Dipakai halaman kasir
     * untuk melihat nota & cetak ulang struk.
     */
    public function riwayat(Request $request)
    {
        $tanggal = $request->query('tanggal', now()->toDateString());
        $limit = max(1, min((int) $request->query('limit', 50), 200));
        $offset = max(0, (int) $request->query('offset', 0));

        $penjualan = Penjualan::with(['details.barang', 'gudang', 'karyawan', 'user'])
            ->whereDate('tanggal', $tanggal)
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return response()->json($penjualan->map(fn ($p) => [
            'id' => $p->id,
            'nomer_nota' => $p->nomer_nota,
            'tanggal' => (string) $p->tanggal,
            'jam' => $p->created_at?->isSameDay($p->tanggal)
                ? $p->created_at->format('H:i')
                : '-',
            'total' => (int) $p->total,
            'diskon' => (int) $p->diskon,
            'neto' => (int) $p->neto,
            'jenis_pembayaran' => $p->jenis_pembayaran,
            'bayar' => (int) $p->bayar,
            'kembalian' => (int) $p->kembalian,
            'nama_kasir' => $p->getNamaKasirAttribute(),
            'gudang' => $p->gudang?->nama_gudang ?? '-',
            'jumlah_item' => $p->details->sum(
                fn ($d) => (int) $d->jumlah * (int) ($d->barang?->getFaktorKonversi($d->satuan) ?? 1)
            ),
            'details' => $p->details->map(fn ($d) => [
                'nama_barang' => $d->barang?->nama_barang ?? '-',
                'jumlah' => (int) $d->jumlah,
                'satuan' => $d->satuan,
                'harga' => (int) $d->harga,
                'diskon' => (int) $d->diskon,
                'subtotal' => (int) $d->subtotal,
            ]),
        ]));
    }

    /**
     * Simpan transaksi kasir.
     * Alurnya ngikutin pola CreatePembelian::afterCreate() punya admin Filament,
     * tapi arah stoknya keluar via StokService::kurangiStok().
     */
    public function simpan(Request $request)
    {
        $data = $request->validate([
            'gudang_id' => ['required', 'integer', 'exists:gudang,id'],
            'tanggal' => ['required', 'date'],
            'diskon' => ['required', 'integer', 'min:0'],
            'diskon_persen' => ['nullable', 'integer', 'min:0', 'max:100'],
            'jenis_pembayaran' => ['required', 'in:tunai,qris,transfer'],
            'bayar' => ['required', 'integer', 'min:0'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.barang_id' => ['required', 'integer', 'exists:barang,id'],
            'details.*.jumlah' => ['required', 'integer', 'min:1'],
            'details.*.diskon' => ['required', 'integer', 'min:0'],
            'details.*.satuan' => ['nullable', 'string'],
        ]);

        $penjualan = DB::transaction(function () use ($data) {
            $gudangId = $data['gudang_id'];

            // total & harga dihitung ulang di server (jangan percaya angka dari browser)
            $total = 0;
            $details = [];
            foreach ($data['details'] as $d) {
                // lockForUpdate biar aman kalau dua kasir jualan barang sama barengan
                $barang = Barang::lockForUpdate()->findOrFail($d['barang_id']);
                $satuan = $d['satuan'] ?? $barang->satuan;
                $hargaAsliSatuan = $barang->getHargaJualForSatuan($satuan);
                $hargaSatuan = $barang->getHargaTierForQty((int) $d['jumlah'], $satuan);
                $faktor = $barang->getFaktorKonversi($satuan);
                $jumlahDasar = $d['jumlah'] * $faktor;

                $pivot = $barang->gudangs()->where('gudang.id', $gudangId)->first();
                $stokSekarang = $pivot ? (int) $pivot->pivot->stok : 0;
                if ($stokSekarang < $jumlahDasar) {
                    abort(422, "Stok {$barang->nama_barang} di gudang ini tidak cukup (tersedia: {$stokSekarang} {$barang->satuan})");
                }

                // Potongan dari harga bertingkat (diskon otomatis barang) dihitung
                // di server, bukan percaya angka dari browser, biar gak bisa dimanipulasi.
                $diskonItem = max(0, ($hargaAsliSatuan - $hargaSatuan) * (int) $d['jumlah']);
                $subtotal = ($hargaAsliSatuan * (int) $d['jumlah']) - $diskonItem;
                $total += $subtotal;
                $hppSatuan = $barang->getHppForSatuan($satuan);
                $details[] = [
                    'barang' => $barang,
                    'jumlah' => $d['jumlah'],
                    'jumlah_dasar' => $jumlahDasar,
                    'harga' => $hargaAsliSatuan,  // harga normal per satuan (sebelum potongan)
                    'harga_aktual' => $hargaSatuan, // harga aktual setelah potongan (dipakai kartu stok)
                    'hpp' => $hppSatuan,
                    'diskon' => $diskonItem,
                    'subtotal' => $subtotal,
                    'satuan' => $satuan,
                ];
            }

            // diskon transaksi dihitung ulang dari persen agar tidak bisa dimanipulasi
            // dari browser (nominal 'diskon' yang dikirim klien diabaikan).
            $diskonNominal = (int) floor($total * (int) ($data['diskon_persen'] ?? 0) / 100);
            $neto = max(0, $total - $diskonNominal);
            if ($data['jenis_pembayaran'] === 'tunai' && $data['bayar'] < $neto) {
                abort(422, 'Uang bayar kurang dari total');
            }
            $bayar = $data['jenis_pembayaran'] === 'tunai' ? $data['bayar'] : $neto;

            // nomer nota urut dibikin server biar gak bentrok kalau kasirnya lebih dari satu.
            $urutan = Penjualan::whereDate('tanggal', $data['tanggal'])
                ->lockForUpdate()->get('id')->count() + 1;
            $nomerNota = 'PJ-' . str_replace('-', '', $data['tanggal']) . '-' . str_pad($urutan, 4, '0', STR_PAD_LEFT);

            $karyawanId = Auth::guard('karyawan')->check() ? Auth::guard('karyawan')->id() : null;
            $userId = Auth::guard('web')->check() ? Auth::guard('web')->id() : null;

            $penjualan = Penjualan::create([
                'nomer_nota' => $nomerNota,
                'karyawan_id' => $karyawanId,
                'user_id' => $userId,
                'gudang_id' => $gudangId,
                'tanggal' => $data['tanggal'],
                'total' => $total,
                'diskon' => $diskonNominal,
                'neto' => $neto,
                'jenis_pembayaran' => $data['jenis_pembayaran'],
                'bayar' => $bayar,
                'kembalian' => max(0, $bayar - $neto),
            ]);

            foreach ($details as $d) {
                DetailJual::create([
                    'penjualan_id' => $penjualan->id,
                    'barang_id' => $d['barang']->id,
                    'gudang_id' => $gudangId,
                    'satuan' => $d['satuan'],
                    'jumlah' => $d['jumlah'],
                    'harga' => $d['harga'],
                    'hpp' => $d['hpp'],
                    'diskon' => $d['diskon'],
                    'subtotal' => $d['subtotal'],
                ]);

                // === STOK & KARTU STOK via StokService (dikurangi dalam Satuan Dasar) ===
                app(StokService::class)->kurangiStok(
                    barangId: $d['barang']->id,
                    gudangId: $gudangId,
                    jumlah: $d['jumlah_dasar'],
                    konteks: [
                        'nomer_entry' => $nomerNota,
                        'tanggal' => $data['tanggal'],
                        'harga' => $d['harga_aktual'],
                        'keterangan' => "Penjualan kasir ({$d['jumlah']} {$d['satuan']})",
                        'jenis' => KartuStok::JENIS_KELUAR,
                    ],
                    validasi: false, // sudah divalidasi di atas
                );
            }

            return $penjualan;
        });

        return response()->json($penjualan->load('details'));
    }
}
