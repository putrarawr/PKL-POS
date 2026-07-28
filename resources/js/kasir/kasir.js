// =====================================================================
// LOGIC KASIR (POS)
// ---------------------------------------------------------------------
// File ini cuma ngurusin tampilan + keranjang. Semua data lewat api.js,
// jadi pas backend temenmu jadi tinggal ubah USE_MOCK di api.js.
// =====================================================================

import {
    getBarang,
    getJenisBarang,
    getGudang,
    simpanPenjualan,
    USE_MOCK,
} from './api.js';

// ------------------------- STATE -------------------------

const state = {
    barang: [],
    jenisBarang: [],
    gudang: [],
    // keranjang: [{ barang_id, nama_barang, satuan, harga, jumlah, diskon }]
    cart: [],
    gudangId: null,
    filterJenis: null,
    search: '',
    diskonTransaksi: 0,
    jenisPembayaran: 'tunai',
    bankTransfer: 'BCA',
    bayar: 0,
    paymentExpanded: false,
};

const rupiah = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID');

// ------------------------- HITUNGAN -------------------------

function subtotalItem(item) {
    return item.harga * item.jumlah - item.diskon;
}

function totalKotor() {
    return state.cart.reduce((sum, item) => sum + subtotalItem(item), 0);
}

function nominalDiskon() {
    const total = totalKotor();
    return Math.floor((total * state.diskonTransaksi) / 100);
}

function totalNeto() {
    return Math.max(0, totalKotor() - nominalDiskon());
}

function kembalian() {
    return Math.max(0, state.bayar - totalNeto());
}

function stokBarang(barang) {
    if (!state.gudangId) return 0;
    return barang.stok?.[state.gudangId] ?? 0;
}

function namaGudangSekarang() {
    return state.gudang.find((g) => g.id === state.gudangId)?.nama_gudang ?? 'gudang ini';
}

function gudangStokTersedia(barang) {
    return state.gudang
        .filter((g) => (barang.stok?.[g.id] ?? 0) > 0)
        .map((g) => g.nama_gudang);
}

// ------------------------- KERANJANG -------------------------

function tambahKeCart(barangId) {
    const barang = state.barang.find((b) => b.id === barangId);
    if (!barang) return;

    const stokDasar = stokBarang(barang);
    const existing = state.cart.find((i) => i.barang_id === barangId);
    const satuanDefault = barang.units?.[0]?.satuan ?? barang.satuan ?? 'Pcs';
    const unitObj = barang.units?.find((u) => u.satuan === (existing ? existing.satuan : satuanDefault)) ?? barang.units?.[0];
    const faktor = unitObj ? unitObj.faktor : 1;
    const hargaUnit = unitObj ? unitObj.harga_jual : barang.harga_jual;

    const jumlahDasarSekarang = existing ? (existing.jumlah * faktor) : 0;

    if (jumlahDasarSekarang + faktor > stokDasar) {
        const rekomendasi = gudangStokTersedia(barang).filter((n) => n !== namaGudangSekarang());
        if (stokDasar === 0) {
            if (rekomendasi.length > 0) {
                toast(`Stok ${barang.nama_barang} habis di ${namaGudangSekarang()}. Tersedia di: ${rekomendasi.join(', ')}`, true);
            } else {
                toast(`Stok ${barang.nama_barang} habis di semua gudang`, true);
            }
        } else {
            toast(`Stok ${barang.nama_barang} di ${namaGudangSekarang()} tinggal ${stokDasar} ${barang.satuan ?? ''}`, true);
        }
        return;
    }

    if (existing) {
        existing.jumlah += 1;
    } else {
        state.cart.push({
            barang_id: barang.id,
            nama_barang: barang.nama_barang,
            satuan: satuanDefault,
            harga: hargaUnit,
            jumlah: 1,
            diskon: 0,
        });
    }
    render();
}

function ubahJumlah(barangId, delta) {
    const item = state.cart.find((i) => i.barang_id === barangId);
    if (!item) return;

    const barang = state.barang.find((b) => b.id === barangId);
    const unitObj = barang?.units?.find((u) => u.satuan === item.satuan);
    const faktor = unitObj ? unitObj.faktor : 1;
    const stokDasar = barang ? stokBarang(barang) : Infinity;

    const baruJumlah = item.jumlah + delta;
    if (baruJumlah * faktor > stokDasar) {
        toast(`Stok maksimal ${stokDasar} ${barang?.satuan ?? ''}`, true);
        return;
    }
    if (baruJumlah <= 0) {
        state.cart = state.cart.filter((i) => i.barang_id !== barangId);
    } else {
        item.jumlah = baruJumlah;
    }
    render();
}

function hapusItem(barangId) {
    state.cart = state.cart.filter((i) => i.barang_id !== barangId);
    render();
}

// jumlah diketik manual di input keranjang
function setJumlah(barangId, jumlah) {
    const item = state.cart.find((i) => i.barang_id === barangId);
    if (!item) return;

    const barang = state.barang.find((b) => b.id === barangId);
    const unitObj = barang?.units?.find((u) => u.satuan === item.satuan);
    const faktor = unitObj ? unitObj.faktor : 1;
    const stokDasar = barang ? stokBarang(barang) : Infinity;

    let baru = Math.floor(Number(jumlah) || 0);
    if (baru <= 0) {
        state.cart = state.cart.filter((i) => i.barang_id !== barangId);
    } else {
        if (baru * faktor > stokDasar) {
            toast(`Stok maksimal ${stokDasar} ${barang?.satuan ?? ''}`, true);
            baru = Math.floor(stokDasar / faktor);
        }
        item.jumlah = baru;
    }
    render();
}

function resetTransaksi() {
    state.cart = [];
    state.diskonTransaksi = 0;
    state.bayar = 0;
    state.jenisPembayaran = 'tunai';
    togglePaymentDetails(false);
    render();
}

function togglePaymentDetails(forceState) {
    const container = document.getElementById('payment-details-container');
    const chevron = document.getElementById('icon-toggle-payment');
    if (!container || !chevron) return;

    if (forceState !== undefined) {
        state.paymentExpanded = forceState;
    } else {
        state.paymentExpanded = !state.paymentExpanded;
    }

    if (state.paymentExpanded) {
        container.classList.remove('hidden');
        chevron.classList.add('rotate-180');
    } else {
        container.classList.add('hidden');
        chevron.classList.remove('rotate-180');
    }
}

// ------------------------- BAYAR -------------------------

async function prosesBayar() {
    if (state.cart.length === 0) {
        toast('Keranjang masih kosong', true);
        return;
    }
    if (!state.gudangId) {
        toast('Pilih gudang dulu', true);
        return;
    }
    if (state.jenisPembayaran === 'tunai' && state.bayar < totalNeto()) {
        if (!state.paymentExpanded) {
            togglePaymentDetails(true);
        }
        toast('Uang bayar kurang dari total', true);
        setTimeout(() => {
            const inputBayar = document.getElementById('input-bayar');
            if (inputBayar) {
                inputBayar.focus();
                inputBayar.select();
            }
        }, 150);
        return;
    }

    const payload = {
        // nomer nota dibikin server (KasirController@simpan) biar urut & gak bentrok
        gudang_id: state.gudangId,
        tanggal: new Date().toISOString().slice(0, 10),
        total: totalKotor(),
        diskon: nominalDiskon(),
        neto: totalNeto(),
        jenis_pembayaran: state.jenisPembayaran,
        bayar: state.jenisPembayaran === 'tunai' ? state.bayar : totalNeto(),
        kembalian: state.jenisPembayaran === 'tunai' ? kembalian() : 0,
        details: state.cart.map((i) => ({
            barang_id: i.barang_id,
            gudang_id: state.gudangId,
            satuan: i.satuan,
            jumlah: i.jumlah,
            harga: i.harga,
            diskon: i.diskon,
            subtotal: subtotalItem(i),
        })),
    };

    const btn = document.getElementById('btn-bayar');
    btn.disabled = true;
    btn.textContent = 'Menyimpan...';

    try {
        const saved = await simpanPenjualan(payload);
        tampilkanStruk({ ...payload, nomer_nota: saved.nomer_nota, kembalian: saved.kembalian ?? payload.kembalian });
        // kurangi stok di layar sesuai yang barusan kejual
        // (datanya disuntik sekali pas halaman kebuka, jadi gak bisa re-fetch)
        for (const d of payload.details) {
            const b = state.barang.find((x) => x.id === d.barang_id);
            if (b && b.stok[d.gudang_id] != null) b.stok[d.gudang_id] -= d.jumlah;
        }
        resetTransaksi();
    } catch (e) {
        toast(e.message ?? 'Gagal menyimpan transaksi', true);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Bayar';
        renderCart(); // sinkronkan lagi label tombol setelah enable
    }
}

// ------------------------- STRUK -------------------------

function tampilkanStruk(payload) {
    const gudang = state.gudang.find((g) => g.id === payload.gudang_id);

    const rows = payload.details
        .map((d) => {
            const nama = state.barang.find((b) => b.id === d.barang_id)?.nama_barang ?? '-';
            return `<tr>
                <td class="py-0.5 pr-2">${nama}</td>
                <td class="py-0.5 text-right whitespace-nowrap">${d.jumlah} x ${rupiah(d.harga)}</td>
                <td class="py-0.5 pl-2 text-right">${rupiah(d.subtotal)}</td>
            </tr>`;
        })
        .join('');

    const labelBayar =
        payload.jenis_pembayaran === 'transfer'
            ? `transfer ${state.bankTransfer}`
            : payload.jenis_pembayaran;

    const namaKasir = (typeof window !== 'undefined' && window.KASIR_DATA?.karyawan?.nama)
        ? window.KASIR_DATA.karyawan.nama
        : 'Kasir';

    document.getElementById('struk-body').innerHTML = `
        <div class="text-center mb-5">
            <p class="font-black text-lg tracking-tight">TOKO PKL</p>
            <p class="text-xs text-zinc-400 mt-1">${gudang?.nama_gudang ?? ''}</p>
            <p class="text-xs text-zinc-400">${payload.nomer_nota} &middot; ${payload.tanggal}</p>
            <p class="text-xs font-semibold text-zinc-600 mt-0.5">Kasir: ${namaKasir}</p>
        </div>
        <table class="w-full text-sm border-y border-dashed border-zinc-300 py-2 my-2 tabular-nums">${rows}</table>
        <div class="text-sm space-y-2 mt-4 tabular-nums">
            <div class="flex justify-between text-zinc-500"><span>Subtotal</span><span class="font-semibold text-zinc-900">${rupiah(payload.total)}</span></div>
            <div class="flex justify-between text-zinc-500"><span>Diskon ${state.diskonTransaksi > 0 ? `(${state.diskonTransaksi}%)` : ''}</span><span class="font-semibold text-zinc-900">- ${rupiah(payload.diskon)}</span></div>
            <div class="flex justify-between items-baseline border-t border-dashed border-zinc-300 pt-2.5 mt-2.5">
                <span class="font-bold">Total</span><span class="font-black text-base">${rupiah(payload.neto)}</span>
            </div>
            <div class="flex justify-between text-zinc-500"><span>Bayar (${labelBayar})</span><span class="font-semibold text-zinc-900">${rupiah(payload.bayar)}</span></div>
            <div class="flex justify-between text-zinc-500"><span>Kembalian</span><span class="font-semibold text-zinc-900">${rupiah(payload.kembalian)}</span></div>
        </div>
        <p class="text-center text-xs font-medium text-zinc-400 mt-6">Terima kasih atas kunjungan Anda</p>
    `;
    document.getElementById('modal-struk').classList.remove('hidden');
}

// ------------------------- RENDER -------------------------

// tile monogram kartu produk: warna lembut deterministik dari nama barang
const TILE_TINTS = [
    'bg-zinc-100 text-zinc-500',
    'bg-stone-100 text-stone-500',
    'bg-zinc-200/70 text-zinc-600',
    'bg-stone-200/70 text-stone-600',
];
function tileTint(nama) {
    let h = 0;
    for (const c of nama) h = (h * 31 + c.charCodeAt(0)) % 997;
    return TILE_TINTS[h % TILE_TINTS.length];
}
function inisial(nama) {
    const kata = nama.trim().split(/\s+/);
    return ((kata[0]?.[0] ?? '') + (kata[1]?.[0] ?? '')).toUpperCase();
}

let lastProdukKey = null;
let highlightedIdx = -1;
let skipClick = false;
let gudangSetValue = null;

function barangTampil() {
    const q = state.search.toLowerCase();
    return state.barang.filter((b) => {
        if (state.filterJenis && b.jenis_barang_id !== state.filterJenis) return false;
        if (q && !b.nama_barang.toLowerCase().includes(q)) return false;
        return true;
    });
}

function getJmlKolom() {
    const grid = document.getElementById('grid-produk');
    return grid ? getComputedStyle(grid).gridTemplateColumns.split(' ').length : 3;
}

function focusSorot() {
    const grid = document.getElementById('grid-produk');
    const btns = grid.querySelectorAll('[data-add]');
    if (btns[highlightedIdx]) {
        btns[highlightedIdx].focus();
        btns[highlightedIdx].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }
}

function renderProduk() {
    const grid = document.getElementById('grid-produk');
    const q = state.search.toLowerCase();
    const list = barangTampil();

    const key = `${state.gudangId}|${state.filterJenis}|${q}`;
    const animate = key !== lastProdukKey;
    lastProdukKey = key;

    if (list.length === 0) {
        grid.innerHTML = `<div class="col-span-full text-center py-20">
            <p class="text-sm font-semibold text-zinc-500">Barang tidak ditemukan</p>
            <p class="text-xs text-zinc-400 mt-1">Coba kata kunci atau kategori lain</p>
        </div>`;
        return;
    }

    grid.innerHTML = list
        .map((b, idx) => {
            const stok = stokBarang(b);
            const habis = stok <= 0;
            const menipis = !habis && stok <= 5;
            const sorot = highlightedIdx === idx ? 'ring-2 ring-zinc-900 shadow-lg shadow-zinc-900/15' : '';
            return `<button data-add="${b.id}" style="--i: ${Math.min(idx, 16)}"
                class="${animate ? 'anim-fade-up ' : ''}group text-left bg-white rounded-2xl border border-zinc-200 p-4 flex flex-col gap-3 transition duration-200
                       ${habis
                           ? 'opacity-40 cursor-not-allowed'
                           : 'cursor-pointer hover:border-zinc-900 hover:shadow-lg hover:shadow-zinc-200/50 hover:-translate-y-0.5 active:translate-y-0 active:scale-[0.98]'}
                       ${sorot}">
                <div class="flex items-start justify-between gap-2">
                    <div class="w-10 h-10 rounded-xl ${tileTint(b.nama_barang)} flex items-center justify-center text-xs font-black select-none">
                        ${inisial(b.nama_barang)}
                    </div>
                    <span class="text-[11px] font-semibold whitespace-nowrap px-2 py-0.5 rounded-full
                        ${habis ? 'bg-red-50 text-red-500' : menipis ? 'bg-amber-50 text-amber-600' : 'bg-zinc-50 text-zinc-400'}">
                        ${habis ? 'Habis' : `${stok} ${b.satuan ?? ''}`}
                    </span>
                </div>
                <div>
                    <p class="font-bold text-sm leading-snug line-clamp-2">${b.nama_barang}</p>
                    <p class="font-black tracking-tight tabular-nums mt-1.5">${rupiah(b.harga_jual)}</p>
                </div>
            </button>`;
        })
        .join('');
}

function renderCart() {
    const wrap = document.getElementById('cart-items');

    if (state.cart.length === 0) {
        wrap.innerHTML = `<div class="h-full flex flex-col items-center justify-center text-center py-16">
            <svg class="w-10 h-10 text-zinc-200 mb-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/>
                <path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/>
                <path d="M17 17h-11v-14h-2"/>
                <path d="M6 5l14 1l-1 7h-13"/>
            </svg>
            <p class="text-sm font-bold text-zinc-500">Belum ada pesanan</p>
            <p class="text-xs text-zinc-400 mt-1">Pilih produk di sebelah kiri</p>
        </div>`;
    } else {
        wrap.innerHTML = state.cart
            .map((i) => {
                const b = state.barang.find((x) => x.id === i.barang_id);
                const units = b?.units ?? [];
                let unitHtml = `<span class="text-xs text-zinc-400 tabular-nums">${rupiah(i.harga)} / ${i.satuan}</span>`;
                if (units.length > 1) {
                    const opts = units
                        .map((u) => `<option value="${u.satuan}" ${u.satuan === i.satuan ? 'selected' : ''}>${u.satuan} (${rupiah(u.harga_jual)})</option>`)
                        .join('');
                    unitHtml = `<select data-unit="${i.barang_id}" class="text-xs bg-zinc-100 border-0 rounded px-1.5 py-0.5 font-bold text-zinc-700 focus:outline-none hover:bg-zinc-200 transition cursor-pointer">${opts}</select>`;
                }
                return `<div class="flex items-center gap-3 py-2.5 border-b border-zinc-100 last:border-0">
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold truncate">${i.nama_barang}</p>
                    <div class="mt-0.5">${unitHtml}</div>
                </div>
                <div class="flex items-center gap-0.5 bg-zinc-100 rounded-lg p-0.5">
                    <button data-minus="${i.barang_id}" class="w-7 h-7 rounded-md hover:bg-white hover:shadow-sm text-zinc-500 font-bold transition">−</button>
                    <input data-qty="${i.barang_id}" type="number" min="1" value="${i.jumlah}"
                        class="w-10 text-center text-sm font-bold tabular-nums bg-transparent focus:outline-none focus:bg-white focus:shadow-sm rounded-md py-1
                               [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                    <button data-plus="${i.barang_id}" class="w-7 h-7 rounded-md hover:bg-white hover:shadow-sm text-zinc-500 font-bold transition">+</button>
                </div>
                <p class="w-24 text-right text-sm font-bold tabular-nums">${rupiah(subtotalItem(i))}</p>
                <button data-del="${i.barang_id}" class="text-zinc-300 hover:text-red-500 px-1 font-bold transition-colors" title="Hapus">×</button>
            </div>`;
            })
            .join('');
    }

    // badge jumlah item di header pesanan
    const badge = document.getElementById('badge-cart-count');
    const totalItem = state.cart.reduce((n, i) => n + i.jumlah, 0);
    badge.classList.toggle('hidden', totalItem === 0);
    badge.textContent = totalItem;

    document.getElementById('lbl-total').textContent = rupiah(totalKotor());
    document.getElementById('lbl-kembalian').textContent = rupiah(kembalian());

    // total neto: kasih "pop" halus tiap nilainya berubah
    const lblNeto = document.getElementById('lbl-neto');
    const netoBaru = rupiah(totalNeto());
    if (lblNeto.textContent !== netoBaru) {
        lblNeto.textContent = netoBaru;
        lblNeto.classList.remove('anim-pop');
        void lblNeto.offsetWidth;
        lblNeto.classList.add('anim-pop');
    }

    const inputDiskon = document.getElementById('input-diskon');
    if (document.activeElement !== inputDiskon) inputDiskon.value = state.diskonTransaksi || '';
    
    const inputBayar = document.getElementById('input-bayar');
    if (document.activeElement !== inputBayar) inputBayar.value = state.bayar ? state.bayar.toLocaleString('id-ID') : '';

    // uang pas & tombol bayar; panel per metode pembayaran
    document.getElementById('btn-uang-pas').textContent = `Uang pas (${rupiah(totalNeto())})`;
    document.getElementById('row-tunai').style.display = state.jenisPembayaran === 'tunai' ? '' : 'none';
    document.getElementById('row-qris').style.display = state.jenisPembayaran === 'qris' ? '' : 'none';
    document.getElementById('row-transfer').style.display = state.jenisPembayaran === 'transfer' ? '' : 'none';
    const btnBayar = document.getElementById('btn-bayar');
    if (!btnBayar.disabled) {
        btnBayar.textContent = state.cart.length > 0 ? `Bayar ${rupiah(totalNeto())}` : 'Bayar';
    }
}

function render() {
    renderProduk();
    renderCart();
}

// ------------------------- TOAST -------------------------

let toastTimer;
function toast(msg, error = false) {
    const el = document.getElementById('toast');
    el.innerHTML = error
        ? `<svg class="w-5 h-5 shrink-0 inline-block -mt-0.5 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>${msg}`
        : msg;
    el.className = `anim-toast fixed top-4 left-1/2 -translate-x-1/2 px-5 py-3 rounded-2xl text-white text-sm font-semibold shadow-2xl z-50 max-w-lg w-full text-center
        ${error ? 'bg-red-600/95 backdrop-blur-sm ring-1 ring-red-400/30' : 'bg-zinc-900/95 backdrop-blur-sm'}`;
    el.classList.remove('hidden');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.classList.add('hidden'), 4000);
}

// ------------------------- DROPDOWN CUSTOM -------------------------
// pengganti <select> native biar menu pilihannya bisa di-style penuh.
// items: [{ value, label }], onChange dipanggil dengan value item terpilih.

function setupDropdown(rootId, items, selectedValue, onChange) {
    const root = document.getElementById(rootId);
    const btn = root.querySelector('[data-dd-btn]');
    const menu = root.querySelector('[data-dd-menu]');
    const lblValue = root.querySelector('[data-dd-value]');
    const chevron = root.querySelector('[data-dd-chevron]');

    let current = selectedValue;

    const itemActive =
        'dd-item w-full flex items-center justify-between gap-3 text-left text-sm font-bold rounded-lg px-3 py-2 bg-zinc-100 cursor-pointer';
    const itemIdle =
        'dd-item w-full flex items-center justify-between gap-3 text-left text-sm font-semibold text-zinc-600 rounded-lg px-3 py-2 hover:bg-zinc-50 hover:text-zinc-900 cursor-pointer transition-colors';
    const check =
        '<svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8.5l3.5 3.5L13 5"/></svg>';

    function renderMenu() {
        if (items.length === 0) {
            menu.innerHTML = `<p class="text-xs text-zinc-400 px-3 py-2.5 whitespace-nowrap">Tidak ada data</p>`;
            lblValue.textContent = '-';
            return;
        }
        menu.innerHTML = items
            .map((it) => {
                const active = String(it.value) === String(current);
                return `<button type="button" data-dd-val="${it.value}" class="${active ? itemActive : itemIdle}">
                    <span class="truncate">${it.label}</span>${active ? check : '<span class="w-3.5"></span>'}
                </button>`;
            })
            .join('');
        lblValue.textContent = items.find((it) => String(it.value) === String(current))?.label ?? '';
    }

    function close() {
        menu.classList.add('hidden');
        chevron.classList.remove('rotate-180');
    }

    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        // tutup dropdown lain yang kebuka
        document.querySelectorAll('[data-dd-menu]').forEach((m) => {
            if (m !== menu) m.classList.add('hidden');
        });
        document.querySelectorAll('[data-dd-chevron]').forEach((c) => {
            if (c !== chevron) c.classList.remove('rotate-180');
        });

        const willOpen = menu.classList.contains('hidden');
        menu.classList.toggle('hidden', !willOpen);
        chevron.classList.toggle('rotate-180', willOpen);

        // re-trigger animasi scale-in tiap kali dibuka
        if (willOpen) {
            menu.classList.remove('anim-scale-in');
            void menu.offsetWidth; // paksa reflow biar animasi jalan lagi
            menu.classList.add('anim-scale-in');
        }
    });

    menu.addEventListener('click', (e) => {
        const item = e.target.closest('[data-dd-val]');
        if (!item) return;
        current = item.dataset.ddVal;
        renderMenu();
        close();
        onChange(current);
    });

    renderMenu();
    return {
        close,
        setValue: (val) => {
            current = val;
            renderMenu();
            close();
            onChange(val);
        },
    };
}

// tutup semua dropdown kalau klik di luar
document.addEventListener('click', () => {
    document.querySelectorAll('[data-dd-menu]').forEach((m) => m.classList.add('hidden'));
    document.querySelectorAll('[data-dd-chevron]').forEach((c) => c.classList.remove('rotate-180'));
});

// ------------------------- INIT -------------------------

async function init() {
    const [barang, jenis, gudang] = await Promise.all([
        getBarang(),
        getJenisBarang(),
        getGudang(),
    ]);
    state.barang = barang;
    state.jenisBarang = jenis;
    state.gudang = gudang;
    state.gudangId = gudang[0]?.id ?? null;

    // dropdown gudang (custom)
    gudangSetValue = setupDropdown(
        'dd-gudang',
        gudang.map((g) => ({ value: g.id, label: g.nama_gudang })),
        state.gudangId,
        (val) => {
            state.gudangId = Number(val);
            state.cart = [];
            highlightedIdx = -1;
            render();
            const input = document.getElementById('input-search');
            if (input) input.focus();
        }
    ).setValue;

    // filter kategori
    const wrapFilter = document.getElementById('filter-jenis');
    const chipActive = 'chip-jenis px-4 py-1.5 rounded-lg text-sm font-bold bg-white text-zinc-900 shadow-sm transition';
    const chipIdle =
        'chip-jenis px-4 py-1.5 rounded-lg text-sm font-semibold text-zinc-500 hover:text-zinc-900 transition';
    wrapFilter.innerHTML =
        `<button data-jenis="" class="${chipActive}">Semua</button>` +
        jenis
            .map((j) => `<button data-jenis="${j.id}" class="${chipIdle}">${j.nama_jenis}</button>`)
            .join('');
    wrapFilter.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-jenis]');
        if (!btn) return;
        state.filterJenis = btn.dataset.jenis ? Number(btn.dataset.jenis) : null;
        highlightedIdx = -1;
        wrapFilter.querySelectorAll('.chip-jenis').forEach((b) => {
            b.className = b === btn ? chipActive : chipIdle;
        });
        renderProduk();
    });

    // search
    const inputSearch = document.getElementById('input-search');
    const btnClearSearch = document.getElementById('btn-clear-search');
    inputSearch.addEventListener('input', (e) => {
        state.search = e.target.value;
        highlightedIdx = -1;
        btnClearSearch.classList.toggle('hidden', state.search === '');
        renderProduk();
    });
    inputSearch.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            const list = barangTampil();
            if (list.length > 0) {
                tambahKeCart(list[0].id);
            }
        } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            const list = barangTampil();
            if (list.length > 0) {
                highlightedIdx = 0;
                renderProduk();
                focusSorot();
            }
        }
    });
    btnClearSearch.addEventListener('click', () => {
        state.search = '';
        highlightedIdx = -1;
        inputSearch.value = '';
        btnClearSearch.classList.add('hidden');
        inputSearch.focus();
        renderProduk();
    });

    // ketika kasir mulai ngetik di mana saja, fokus otomatis ke search
    document.addEventListener('keydown', (e) => {
        if (e.key.length !== 1 || e.ctrlKey || e.altKey || e.metaKey) return;
        const tag = document.activeElement?.tagName;
        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
        if (document.activeElement?.isContentEditable) return;
        e.preventDefault();
        const input = document.getElementById('input-search');
        const clear = document.getElementById('btn-clear-search');
        input.value += e.key;
        state.search = input.value;
        highlightedIdx = -1;
        clear.classList.toggle('hidden', false);
        renderProduk();
        input.focus();
        const len = input.value.length;
        input.setSelectionRange(len, len);
    });

    // klik produk / tombol keranjang (event delegation)
    document.getElementById('grid-produk').addEventListener('click', (e) => {
        if (skipClick) return;
        const btn = e.target.closest('[data-add]');
        if (btn) {
            highlightedIdx = -1;
            tambahKeCart(Number(btn.dataset.add));
        }
    });
    document.getElementById('grid-produk').addEventListener('keydown', (e) => {
        const btn = e.target.closest('[data-add]');
        if (!btn) return;

        const list = barangTampil();
        if (list.length === 0) return;
        const cols = getJmlKolom();

        if (e.key === 'ArrowRight') {
            e.preventDefault();
            highlightedIdx = Math.min(highlightedIdx + 1, list.length - 1);
        } else if (e.key === 'ArrowLeft') {
            e.preventDefault();
            highlightedIdx = Math.max(highlightedIdx - 1, 0);
        } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            highlightedIdx = Math.min(highlightedIdx + cols, list.length - 1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            highlightedIdx = Math.max(highlightedIdx - cols, 0);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            skipClick = true;
            setTimeout(() => { skipClick = false; }, 0);
            const list = barangTampil();
            if (highlightedIdx >= 0 && highlightedIdx < list.length) {
                tambahKeCart(list[highlightedIdx].id);
            }
            focusSorot();
            return;
        } else if (e.key === 'Escape') {
            e.preventDefault();
            highlightedIdx = -1;
            renderProduk();
            inputSearch.focus();
            return;
        } else {
            return;
        }

        renderProduk();
        focusSorot();
    });
    document.getElementById('cart-items').addEventListener('click', (e) => {
        const plus = e.target.closest('[data-plus]');
        const minus = e.target.closest('[data-minus]');
        const del = e.target.closest('[data-del]');
        if (plus) ubahJumlah(Number(plus.dataset.plus), 1);
        if (minus) ubahJumlah(Number(minus.dataset.minus), -1);
        if (del) hapusItem(Number(del.dataset.del));
    });
    // ketik jumlah manual / ganti satuan (change = pas Enter / pindah fokus / pilih dropdown)
    document.getElementById('cart-items').addEventListener('change', (e) => {
        const qty = e.target.closest('[data-qty]');
        if (qty) setJumlah(Number(qty.dataset.qty), qty.value);
        const unitSel = e.target.closest('[data-unit]');
        if (unitSel) ubahSatuanItem(Number(unitSel.dataset.unit), unitSel.value);
    });

    // diskon & bayar
    document.getElementById('input-diskon').addEventListener('input', (e) => {
        let raw = e.target.value.replace(/\D/g, ''); // hanya angka
        if (raw !== '') {
            let val = Number(raw);
            if (val > 100) val = 100;
            e.target.value = val;
            state.diskonTransaksi = val;
        } else {
            e.target.value = '';
            state.diskonTransaksi = 0;
        }
        renderCart();
    });

    document.getElementById('input-bayar').addEventListener('input', (e) => {
        let raw = e.target.value.replace(/\D/g, ''); // hanya angka
        if (raw !== '') {
            let val = Number(raw);
            e.target.value = val.toLocaleString('id-ID');
            state.bayar = val;
        } else {
            e.target.value = '';
            state.bayar = 0;
        }
        renderCart();
    });
    document.getElementById('btn-uang-pas').addEventListener('click', () => {
        state.bayar = totalNeto();
        renderCart();
    });

    // jenis pembayaran
    document.querySelectorAll('input[name="jenis_pembayaran"]').forEach((radio) => {
        radio.addEventListener('change', (e) => {
            state.jenisPembayaran = e.target.value;
            renderCart();
        });
    });

    // bank tujuan transfer
    document.querySelectorAll('input[name="bank_transfer"]').forEach((radio) => {
        radio.addEventListener('change', (e) => {
            state.bankTransfer = e.target.value;
        });
    });

    // aksi
    document.getElementById('btn-toggle-payment').addEventListener('click', () => {
        togglePaymentDetails();
    });
    document.getElementById('btn-bayar').addEventListener('click', prosesBayar);
    document.getElementById('btn-reset').addEventListener('click', resetTransaksi);
    document.getElementById('btn-tutup-struk').addEventListener('click', () => {
        document.getElementById('modal-struk').classList.add('hidden');
    });
    document.getElementById('btn-print-struk').addEventListener('click', () => window.print());

    if (USE_MOCK) {
        document.getElementById('badge-mock').classList.remove('hidden');
    }

    document.getElementById('loading').classList.add('hidden');
    document.getElementById('kasir-app').classList.remove('hidden');
    render();
}

init().catch((e) => {
    document.getElementById('loading').innerHTML = `
        <div class="flex-1 flex flex-col items-center justify-center gap-2 text-center px-6">
            <p class="text-sm font-bold text-zinc-900">Gagal memuat data</p>
            <p class="text-sm text-zinc-500">${e.message}</p>
            <button onclick="location.reload()"
                class="mt-3 text-sm font-bold bg-zinc-900 hover:bg-zinc-800 text-white rounded-xl px-5 py-2.5 transition">
                Muat ulang
            </button>
        </div>`;
});
