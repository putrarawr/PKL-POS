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
                $hargaSatuan = $barang->getHargaJualForSatuan($satuan);
                $faktor = $barang->getFaktorKonversi($satuan);
                $jumlahDasar = $d['jumlah'] * $faktor;

                $pivot = $barang->gudangs()->where('gudang.id', $gudangId)->first();
                $stokSekarang = $pivot ? (int) $pivot->pivot->stok : 0;
                if ($stokSekarang < $jumlahDasar) {
                    abort(422, "Stok {$barang->nama_barang} di gudang ini tidak cukup (tersedia: {$stokSekarang} {$barang->satuan})");
                }

                $subtotal = ($hargaSatuan * $d['jumlah']) - $d['diskon'];
                $total += $subtotal;
                $details[] = [
                    'barang' => $barang,
                    'jumlah' => $d['jumlah'],
                    'jumlah_dasar' => $jumlahDasar,
                    'harga' => $hargaSatuan,
                    'diskon' => $d['diskon'],
                    'subtotal' => $subtotal,
                    'satuan' => $satuan,
                ];
            }

            $neto = max(0, $total - $data['diskon']);
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
                'diskon' => $data['diskon'],
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
                        'harga' => $d['harga'],
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
