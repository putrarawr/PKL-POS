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

/**
 * Generates multi-tier unit options (Pcs, Pack, Dus, Slop, Bal, Bag, Karung) and wholesale prices for every item
 */
function getUnitsForBarang(barang) {
    if (barang.units && barang.units.length > 1) {
        return barang.units;
    }

    const units = [];
    const baseSatuan = barang.satuan ?? 'Pcs';
    const basePrice = Number(barang.harga_jual || 0);
    const nama = (barang.nama_barang || '').toLowerCase();
    const sat = baseSatuan.toLowerCase();

    units.push({
        level: 1,
        satuan: baseSatuan,
        faktor: 1,
        harga_jual: basePrice,
    });

    if (nama.includes('mild') || nama.includes('rokok') || nama.includes('signature') || sat === 'bks') {
        units.push({
            level: 2,
            satuan: 'Slop (10 bks)',
            faktor: 10,
            harga_jual: Math.floor(basePrice * 10 * 0.95),
        });
        units.push({
            level: 3,
            satuan: 'Bal (200 bks)',
            faktor: 200,
            harga_jual: Math.floor(basePrice * 200 * 0.90),
        });
    } else if (sat === 'btl' || nama.includes('botol') || nama.includes('teh') || nama.includes('kopi') || nama.includes('air')) {
        units.push({
            level: 2,
            satuan: 'Pack (6 btl)',
            faktor: 6,
            harga_jual: Math.floor(basePrice * 6 * 0.95),
        });
        units.push({
            level: 3,
            satuan: 'Karton (24 btl)',
            faktor: 24,
            harga_jual: Math.floor(basePrice * 24 * 0.90),
        });
    } else if (sat === 'kg' || nama.includes('beras') || nama.includes('gula') || nama.includes('minyak')) {
        units.push({
            level: 2,
            satuan: 'Bag (5kg)',
            faktor: 5,
            harga_jual: Math.floor(basePrice * 5 * 0.95),
        });
        units.push({
            level: 3,
            satuan: 'Karung (25kg)',
            faktor: 25,
            harga_jual: Math.floor(basePrice * 25 * 0.90),
        });
    } else {
        units.push({
            level: 2,
            satuan: 'Pack (10 pcs)',
            faktor: 10,
            harga_jual: Math.floor(basePrice * 10 * 0.95),
        });
        units.push({
            level: 3,
            satuan: 'Dus (40 pcs)',
            faktor: 40,
            harga_jual: Math.floor(basePrice * 40 * 0.88),
        });
    }

    return units;
}

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

function stokTersedia(barang) {
    const dasar = stokBarang(barang);
    const item = state.cart.find((i) => i.barang_id === barang.id);
    if (!item) return dasar;
    const units = getUnitsForBarang(barang);
    const uObj = units.find((u) => u.satuan === item.satuan);
    const faktor = uObj ? uObj.faktor : 1;
    return Math.max(0, dasar - item.jumlah * faktor);
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

    const units = getUnitsForBarang(barang);
    const existing = state.cart.find((i) => i.barang_id === barangId);
    const satuanDefault = existing ? existing.satuan : units[0].satuan;
    const unitObj = units.find((u) => u.satuan === satuanDefault) ?? units[0];
    const faktor = unitObj ? unitObj.faktor : 1;
    const hargaUnit = unitObj ? unitObj.harga_jual : barang.harga_jual;

    const tersedia = stokTersedia(barang);

    if (faktor > tersedia) {
        const stokDasar = stokBarang(barang);
        const rekomendasi = gudangStokTersedia(barang).filter((n) => n !== namaGudangSekarang());
        if (tersedia <= 0 && stokDasar > 0) {
            toast(`Semua stok ${barang.nama_barang} (${stokDasar} ${barang.satuan ?? ''}) sudah ada di keranjang`, true);
        } else if (stokDasar === 0) {
            if (rekomendasi.length > 0) {
                toast(`Stok ${barang.nama_barang} habis di ${namaGudangSekarang()}. Tersedia di: ${rekomendasi.join(', ')}`, true);
            } else {
                toast(`Stok ${barang.nama_barang} habis di semua gudang`, true);
            }
        } else {
            toast(`Stok ${barang.nama_barang} di ${namaGudangSekarang()} tinggal ${tersedia} ${barang.satuan ?? ''}`, true);
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

function ubahSatuanItem(barangId, satuanBaru) {
    const item = state.cart.find((i) => i.barang_id === barangId);
    if (!item) return;

    const barang = state.barang.find((b) => b.id === barangId);
    if (!barang) return;

    const units = getUnitsForBarang(barang);
    const unitObj = units.find((u) => u.satuan === satuanBaru);
    if (!unitObj) return;

    const tersedia = stokTersedia(barang);
    const jumlahDasarBaru = item.jumlah * unitObj.faktor;
    const jumlahDasarLama = item.jumlah * (units.find((u) => u.satuan === item.satuan)?.faktor ?? 1);

    if (jumlahDasarBaru - jumlahDasarLama > tersedia) {
        toast(`Stok ${barang.nama_barang} tidak cukup untuk ${item.jumlah} ${unitObj.satuan} (tersedia: ${tersedia} ${barang.satuan ?? 'pcs'})`, true);
        return;
    }

    item.satuan = unitObj.satuan;
    item.harga = unitObj.harga_jual;
    render();
}

function ubahJumlah(barangId, delta) {
    const item = state.cart.find((i) => i.barang_id === barangId);
    if (!item) return;

    const barang = state.barang.find((b) => b.id === barangId);
    const units = barang ? getUnitsForBarang(barang) : [];
    const unitObj = units.find((u) => u.satuan === item.satuan);
    const faktor = unitObj ? unitObj.faktor : 1;
    const baruJumlah = item.jumlah + delta;
    const tersedia = barang ? stokTersedia(barang) : Infinity;
    if (delta > 0 && delta * faktor > tersedia) {
        toast(`Stok ${barang?.nama_barang ?? ''} tinggal ${tersedia} ${barang?.satuan ?? ''}`, true);
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

function setJumlah(barangId, jumlah) {
    const item = state.cart.find((i) => i.barang_id === barangId);
    if (!item) return;

    const barang = state.barang.find((b) => b.id === barangId);
    const units = barang ? getUnitsForBarang(barang) : [];
    const unitObj = units.find((u) => u.satuan === item.satuan);
    const faktor = unitObj ? unitObj.faktor : 1;

    let parsed = Math.floor(Number(jumlah));
    if (!Number.isFinite(parsed) || parsed <= 0) {
        return;
    }
    const tersedia = barang ? stokTersedia(barang) : Infinity;
    let baru = parsed;
    if ((baru - item.jumlah) * faktor > tersedia) {
        const maksTotal = item.jumlah + Math.floor(tersedia / faktor);
        toast(`Stok maksimal ${maksTotal} ${barang?.satuan ?? ''}`, true);
        baru = maksTotal;
    }
    item.jumlah = baru;
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
    if (document.getElementById('btn-bayar')?.disabled) return;
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
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Menyimpan...';
    }

    try {
        const saved = await simpanPenjualan(payload);
        tampilkanStruk({ ...payload, nomer_nota: saved.nomer_nota, kembalian: saved.kembalian ?? payload.kembalian });
        for (const d of payload.details) {
            const b = state.barang.find((x) => x.id === d.barang_id);
            const units = b ? getUnitsForBarang(b) : [];
            const uObj = units.find((u) => u.satuan === d.satuan);
            const faktor = uObj ? uObj.faktor : 1;
            if (b && b.stok[d.gudang_id] != null) b.stok[d.gudang_id] -= (d.jumlah * faktor);
        }
        resetTransaksi();
    } catch (e) {
        toast(e.message ?? 'Gagal menyimpan transaksi', true);
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Bayar';
        }
        renderCart();
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
                <td class="py-0.5 text-right whitespace-nowrap">${d.jumlah} ${d.satuan} x ${rupiah(d.harga)}</td>
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

    const body = document.getElementById('struk-body');
    if (body) {
        body.innerHTML = `
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
    }
    document.getElementById('modal-struk')?.classList.remove('hidden');
    document.getElementById('btn-print-struk')?.focus();
}

// ------------------------- RENDER -------------------------

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
let cartIdx = -1;
let panduanPrevFocus = null;

function barangTampil() {
    const q = state.search.toLowerCase();
    return state.barang.filter((b) => {
        if (state.filterJenis && b.jenis_barang_id !== state.filterJenis) return false;
        if (q) {
            const namaMatch = b.nama_barang.toLowerCase().includes(q);
            const kodeMatch = inisial(b.nama_barang).toLowerCase() === q;
            if (!namaMatch && !kodeMatch) return false;
        }
        return true;
    });
}

function getJmlKolom() {
    const grid = document.getElementById('grid-produk');
    return grid ? getComputedStyle(grid).gridTemplateColumns.split(' ').length : 3;
}

function focusSorot() {
    const grid = document.getElementById('grid-produk');
    if (!grid) return;
    const btns = grid.querySelectorAll('[data-add]');
    if (btns[highlightedIdx]) {
        btns[highlightedIdx].focus();
        btns[highlightedIdx].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }
}

function renderProduk() {
    const grid = document.getElementById('grid-produk');
    if (!grid) return;

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

    const cartSelId = (cartIdx >= 0 && cartIdx < state.cart.length) ? state.cart[cartIdx].barang_id : null;

    grid.innerHTML = list
        .map((b, idx) => {
            const stok = stokTersedia(b);
            const habis = stok <= 0;
            const menipis = !habis && stok <= 5;
            const sorot = highlightedIdx === idx ? 'ring-2 ring-zinc-900 shadow-lg shadow-zinc-900/15' : '';
            const sorotCart = cartSelId === b.id;
            const kelasRing = sorot || (sorotCart ? 'ring-2 ring-zinc-900' : '');
            const units = getUnitsForBarang(b);
            const defaultUnit = units[0]?.satuan ?? b.satuan ?? 'Pcs';
            const jumlahDiKeranjang = state.cart.find((i) => i.barang_id === b.id)?.jumlah ?? 0;

            return `<button data-add="${b.id}" style="--i: ${Math.min(idx, 16)}"
                class="${animate ? 'anim-fade-up ' : ''}relative group text-left bg-white rounded-2xl border border-zinc-200 p-4 flex flex-col gap-3 transition duration-200
                       ${habis
                           ? 'opacity-40 cursor-not-allowed'
                           : 'cursor-pointer hover:border-zinc-900 hover:shadow-lg hover:shadow-zinc-200/50 hover:-translate-y-0.5 active:translate-y-0 active:scale-[0.98]'}
                       ${kelasRing}">
                ${jumlahDiKeranjang > 0 ? `<span class="absolute bottom-3 right-3 min-w-6 h-6 px-1.5 rounded-lg bg-zinc-900 text-white text-[11px] font-bold flex items-center justify-center tabular-nums shadow-sm">×${jumlahDiKeranjang}</span>` : ''}
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
                    <p class="font-black tracking-tight tabular-nums mt-1.5">${rupiah(b.harga_jual)} <span class="text-xs font-normal text-zinc-400">/ ${defaultUnit}</span></p>
                </div>
            </button>`;
        })
        .join('');
}

function renderCart() {
    const wrap = document.getElementById('cart-items');
    if (!wrap) return;

    if (cartIdx >= state.cart.length) cartIdx = state.cart.length - 1;
    if (state.cart.length === 0) cartIdx = -1;

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
            .map((i, idx) => {
                const barang = state.barang.find((b) => b.id === i.barang_id);
                const units = barang ? getUnitsForBarang(barang) : [{ satuan: i.satuan, harga_jual: i.harga }];

                const selectedUnitObj = units.find((u) => u.satuan === i.satuan) ?? units[0];

                const customUnitOptionsHtml = units
                    .map((u) => {
                        const active = u.satuan === i.satuan;
                        const checkIcon = `<svg class="w-3.5 h-3.5 shrink-0 text-white" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8.5l3.5 3.5L13 5"/></svg>`;
                        return `<button type="button" data-custom-unit-select="${i.barang_id}" data-unit-val="${u.satuan}"
                            class="w-full flex items-center justify-between gap-2 text-left text-xs rounded-lg px-2.5 py-1.5 transition-all cursor-pointer ${
                                active
                                    ? 'bg-zinc-900 text-white font-bold shadow-xs'
                                    : 'text-zinc-700 hover:bg-zinc-100 hover:text-zinc-900 font-semibold'
                            }">
                            <span class="truncate">${u.satuan} <span class="${active ? 'text-zinc-300 font-normal' : 'text-zinc-400 font-normal'}">(${rupiah(u.harga_jual)})</span></span>
                            ${active ? checkIcon : ''}
                        </button>`;
                    })
                    .join('');

                return `<div data-cart-row="${idx}" tabindex="-1" class="relative py-3 px-2 border-b border-zinc-100 last:border-0 space-y-2 transition-colors duration-150 ${cartIdx === idx ? 'bg-zinc-50 ring-2 ring-zinc-900 rounded-xl' : ''}">
                    ${cartIdx === idx ? '<span class="absolute left-0 top-2.5 bottom-2.5 w-1 rounded-r-full bg-zinc-900"></span>' : ''}
                    <div class="flex items-start justify-between gap-2">
                        ${cartIdx === idx ? `<span class="shrink-0 mt-0.5 text-[10px] font-bold bg-zinc-900 text-white rounded-md px-1.5 py-0.5 tabular-nums">${idx + 1}/${state.cart.length}</span>` : ''}
                        <p class="text-sm font-bold text-zinc-900 leading-snug truncate flex-1" title="${i.nama_barang}">${i.nama_barang}</p>
                        <button data-del="${i.barang_id}" type="button" class="text-zinc-300 hover:text-red-600 font-bold px-1 transition-colors cursor-pointer text-base leading-none shrink-0" title="Hapus item">×</button>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <div class="relative min-w-0 flex-1" data-unit-dropdown-wrapper="${i.barang_id}">
                            <button type="button" data-unit-dropdown-btn="${i.barang_id}"
                                class="w-full max-w-[185px] flex items-center justify-between gap-1.5 text-xs font-bold text-zinc-800 bg-zinc-100/90 hover:bg-zinc-200/70 border border-zinc-200/90 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-zinc-900/10 transition-all cursor-pointer">
                                <span class="truncate">${selectedUnitObj.satuan} <span class="text-zinc-500 font-normal">(${rupiah(selectedUnitObj.harga_jual)})</span></span>
                                <svg data-unit-dropdown-chevron="${i.barang_id}" class="w-3.5 h-3.5 text-zinc-400 shrink-0 transition-transform duration-200" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 6l4 4 4-4"/>
                                </svg>
                            </button>
                            <div data-unit-dropdown-menu="${i.barang_id}"
                                class="hidden anim-scale-in absolute left-0 top-full mt-1.5 min-w-[185px] w-max max-w-[240px] max-h-52 overflow-y-auto z-40 bg-white border border-zinc-200 rounded-xl shadow-xl shadow-zinc-950/10 p-1 space-y-0.5">
                                ${customUnitOptionsHtml}
                            </div>
                        </div>
                        <div class="flex items-center gap-0.5 bg-zinc-100 rounded-lg p-0.5 shrink-0">
                            <button data-minus="${i.barang_id}" type="button" class="w-6 h-6 rounded-md hover:bg-white hover:shadow-xs text-zinc-600 font-bold transition flex items-center justify-center cursor-pointer text-xs">−</button>
                            <input data-qty="${i.barang_id}" type="number" min="1" value="${i.jumlah}"
                                class="w-8 text-center text-xs font-bold tabular-nums bg-transparent focus:outline-none focus:bg-white focus:shadow-xs rounded-md py-0.5
                                       [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                            <button data-plus="${i.barang_id}" type="button" class="w-6 h-6 rounded-md hover:bg-white hover:shadow-xs text-zinc-600 font-bold transition flex items-center justify-center cursor-pointer text-xs">+</button>
                        </div>
                        <p class="text-xs font-bold text-zinc-900 tabular-nums text-right shrink-0 min-w-[70px]">${rupiah(subtotalItem(i))}</p>
                    </div>
                </div>`;
            })
            .join('');
    }

    const badge = document.getElementById('badge-cart-count');
    const totalItem = state.cart.reduce((n, i) => n + i.jumlah, 0);
    if (badge) {
        badge.classList.toggle('hidden', totalItem === 0);
        badge.textContent = totalItem;
    }

    const hintCart = document.getElementById('hint-cart-selected');
    const hintCartPos = document.getElementById('hint-cart-pos');
    if (hintCart) {
        const aktif = cartIdx >= 0 && state.cart.length > 0;
        hintCart.classList.toggle('hidden', !aktif);
        if (hintCartPos && aktif) hintCartPos.textContent = `${cartIdx + 1}/${state.cart.length}`;
    }

    const lblTotal = document.getElementById('lbl-total');
    const lblKembalian = document.getElementById('lbl-kembalian');
    if (lblTotal) lblTotal.textContent = rupiah(totalKotor());
    if (lblKembalian) lblKembalian.textContent = rupiah(kembalian());

    const lblNeto = document.getElementById('lbl-neto');
    if (lblNeto) {
        const netoBaru = rupiah(totalNeto());
        if (lblNeto.textContent !== netoBaru) {
            lblNeto.textContent = netoBaru;
            lblNeto.classList.remove('anim-pop');
            void lblNeto.offsetWidth;
            lblNeto.classList.add('anim-pop');
        }
    }

    const inputDiskon = document.getElementById('input-diskon');
    if (inputDiskon && document.activeElement !== inputDiskon) inputDiskon.value = state.diskonTransaksi || '';

    const inputBayar = document.getElementById('input-bayar');
    if (inputBayar && document.activeElement !== inputBayar) inputBayar.value = state.bayar ? state.bayar.toLocaleString('id-ID') : '';

    const btnUangPas = document.getElementById('btn-uang-pas');
    if (btnUangPas) btnUangPas.textContent = `Uang pas (${rupiah(totalNeto())})`;

    const rowTunai = document.getElementById('row-tunai');
    const rowQris = document.getElementById('row-qris');
    const rowTransfer = document.getElementById('row-transfer');
    if (rowTunai) rowTunai.style.display = state.jenisPembayaran === 'tunai' ? '' : 'none';
    if (rowQris) rowQris.style.display = state.jenisPembayaran === 'qris' ? '' : 'none';
    if (rowTransfer) rowTransfer.style.display = state.jenisPembayaran === 'transfer' ? '' : 'none';

    const btnBayar = document.getElementById('btn-bayar');
    if (btnBayar && !btnBayar.disabled) {
        btnBayar.textContent = state.cart.length > 0 ? `Bayar ${rupiah(totalNeto())}` : 'Bayar';
    }
}

function render() {
    renderProduk();
    renderCart();
}

function fokusCartRow() {
    if (cartIdx < 0) return;
    const rows = document.querySelectorAll('#cart-items [data-cart-row]');
    if (rows[cartIdx]) {
        rows[cartIdx].focus();
        rows[cartIdx].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }
}

function pindahCartIdx(delta) {
    if (state.cart.length === 0) {
        cartIdx = -1;
        return;
    }
    const next = cartIdx < 0
        ? (delta > 0 ? 0 : state.cart.length - 1)
        : Math.min(Math.max(cartIdx + delta, 0), state.cart.length - 1);
    cartIdx = next;
    render();
    fokusCartRow();
}

// ------------------------- TOAST -------------------------

let toastTimer;
function toast(msg, error = false) {
    const el = document.getElementById('toast');
    if (!el) return;
    el.innerHTML = error
        ? `<svg class="w-5 h-5 shrink-0 inline-block -mt-0.5 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>${msg}`
        : msg;
    el.className = `anim-toast fixed top-4 left-1/2 -translate-x-1/2 px-5 py-3 rounded-2xl text-white text-sm font-semibold shadow-2xl z-50 max-w-lg w-full text-center
        ${error ? 'bg-red-600/95 backdrop-blur-xs ring-1 ring-red-400/30' : 'bg-zinc-900/95 backdrop-blur-xs'}`;
    el.classList.remove('hidden');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.classList.add('hidden'), 4000);
}

// ------------------------- PANDUAN SHORTCUT -------------------------

function bukaPanduanShortcut() {
    const modal = document.getElementById('modal-panduan-shortcut');
    if (!modal) return;
    panduanPrevFocus = document.activeElement;
    modal.classList.remove('hidden');
    document.getElementById('btn-tutup-panduan-shortcut')?.focus();
}

function tutupPanduanShortcut() {
    document.getElementById('modal-panduan-shortcut')?.classList.add('hidden');
    const target = panduanPrevFocus && panduanPrevFocus.isConnected ? panduanPrevFocus : null;
    panduanPrevFocus = null;
    if (target) {
        target.focus();
    } else {
        document.getElementById('input-search')?.focus();
    }
}

// ------------------------- DROPDOWN CUSTOM -------------------------

function setupDropdown(rootId, items, selectedValue, onChange) {
    const root = document.getElementById(rootId);
    if (!root) return { setValue: () => {} };

    const btn = root.querySelector('[data-dd-btn]');
    const menu = root.querySelector('[data-dd-menu]');
    const lblValue = root.querySelector('[data-dd-value]');
    const chevron = root.querySelector('[data-dd-chevron]');
    if (!btn || !menu) return { setValue: () => {} };

    let current = selectedValue;

    const itemActive =
        'dd-item w-full flex items-center justify-between gap-3 text-left text-sm font-bold rounded-lg px-3 py-2 bg-zinc-900 text-white cursor-pointer shadow-xs';
    const itemIdle =
        'dd-item w-full flex items-center justify-between gap-3 text-left text-sm font-semibold text-zinc-600 rounded-lg px-3 py-2 hover:bg-zinc-100 hover:text-zinc-900 cursor-pointer transition-colors';
    const check =
        '<svg class="w-3.5 h-3.5 shrink-0 text-white" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8.5l3.5 3.5L13 5"/></svg>';

    function renderMenu() {
        if (items.length === 0) {
            menu.innerHTML = `<p class="text-xs text-zinc-400 px-3 py-2.5 whitespace-nowrap">Tidak ada data</p>`;
            if (lblValue) lblValue.textContent = '-';
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
        if (lblValue) lblValue.textContent = items.find((it) => String(it.value) === String(current))?.label ?? '';
    }

    function close() {
        menu.classList.add('hidden');
        if (chevron) chevron.classList.remove('rotate-180');
    }

    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const willOpen = menu.classList.contains('hidden');
        menu.classList.toggle('hidden', !willOpen);
        if (chevron) chevron.classList.toggle('rotate-180', willOpen);
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
        },
    };
}

document.addEventListener('click', () => {
    document.querySelectorAll('[data-dd-menu], [data-custom-dd-menu], [data-unit-dropdown-menu]').forEach((m) => m.classList.add('hidden'));
    document.querySelectorAll('[data-dd-chevron], [data-custom-dd-chevron], [data-unit-dropdown-chevron]').forEach((c) => c.classList.remove('rotate-180'));
});

function bukaModalReset() {
    const modal = document.getElementById('modal-konfirmasi-reset');
    if (!modal) return;
    modal.classList.remove('hidden');
    document.getElementById('btn-batal-reset')?.focus();
}

function tutupModalReset() {
    document.getElementById('modal-konfirmasi-reset')?.classList.add('hidden');
    document.getElementById('input-search')?.focus();
}

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

    let pendingGudangId = null;

    // dropdown gudang (custom) dengan konfirmasi jika keranjang tidak kosong
    gudangSetValue = setupDropdown(
        'dd-gudang',
        gudang.map((g) => ({ value: g.id, label: g.nama_gudang })),
        state.gudangId,
        (val) => {
            const nextGudangId = Number(val);
            if (nextGudangId === state.gudangId) return;

            if (state.cart.length > 0) {
                pendingGudangId = nextGudangId;
                const targetGudang = state.gudang.find((g) => g.id === nextGudangId);
                const nameEl = document.getElementById('target-nama-gudang');
                if (nameEl) nameEl.textContent = targetGudang?.nama_gudang ?? 'gudang baru';
                document.getElementById('modal-konfirmasi-gudang')?.classList.remove('hidden');
            } else {
                state.gudangId = nextGudangId;
                state.cart = [];
                highlightedIdx = -1;
                render();
                const input = document.getElementById('input-search');
                if (input) input.focus();
            }
        }
    ).setValue;

    // Modal Konfirmasi Gudang
    document.getElementById('btn-batal-gudang')?.addEventListener('click', () => {
        document.getElementById('modal-konfirmasi-gudang')?.classList.add('hidden');
        if (gudangSetValue) gudangSetValue(state.gudangId);
        pendingGudangId = null;
    });

    document.getElementById('btn-konfirmasi-gudang')?.addEventListener('click', () => {
        document.getElementById('modal-konfirmasi-gudang')?.classList.add('hidden');
        if (pendingGudangId) {
            state.gudangId = pendingGudangId;
            state.cart = [];
            highlightedIdx = -1;
            render();
            const targetGudang = state.gudang.find((g) => g.id === pendingGudangId);
            toast(`Berhasil pindah ke ${targetGudang?.nama_gudang ?? 'gudang terpilih'}`);
            pendingGudangId = null;
            const input = document.getElementById('input-search');
            if (input) input.focus();
        }
    });

    // filter kategori
    const wrapFilter = document.getElementById('filter-jenis');
    if (wrapFilter) {
        const chipActive = 'chip-jenis px-4 py-1.5 rounded-lg text-sm font-bold bg-white text-zinc-900 shadow-xs transition cursor-pointer';
        const chipIdle = 'chip-jenis px-4 py-1.5 rounded-lg text-sm font-semibold text-zinc-500 hover:text-zinc-900 transition cursor-pointer';
        wrapFilter.innerHTML =
            `<button data-jenis="" class="${chipActive}" type="button">Semua</button>` +
            jenis
                .map((j) => `<button data-jenis="${j.id}" class="${chipIdle}" type="button">${j.nama_jenis}</button>`)
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
    }

    // search
    const inputSearch = document.getElementById('input-search');
    const btnClearSearch = document.getElementById('btn-clear-search');

    if (inputSearch) {
        inputSearch.addEventListener('input', (e) => {
            state.search = e.target.value;
            highlightedIdx = -1;
            if (btnClearSearch) btnClearSearch.classList.toggle('hidden', state.search === '');
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
    }

    if (btnClearSearch) {
        btnClearSearch.addEventListener('click', () => {
            state.search = '';
            highlightedIdx = -1;
            if (inputSearch) {
                inputSearch.value = '';
                inputSearch.focus();
            }
            btnClearSearch.classList.add('hidden');
            renderProduk();
        });
    }

    document.addEventListener('keydown', (e) => {
        const adaModalTerbuka = ['modal-struk', 'modal-konfirmasi-reset', 'modal-konfirmasi-gudang', 'modal-panduan-shortcut'].some((id) => {
            const el = document.getElementById(id);
            return el && !el.classList.contains('hidden');
        });
        if (adaModalTerbuka) return;
        if (e.key.length !== 1 || e.ctrlKey || e.altKey || e.metaKey) return;
        const tag = document.activeElement?.tagName;
        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
        if (document.activeElement?.isContentEditable) return;
        e.preventDefault();
        const input = document.getElementById('input-search');
        const clear = document.getElementById('btn-clear-search');
        if (input) {
            input.value += e.key;
            state.search = input.value;
            highlightedIdx = -1;
            if (clear) clear.classList.toggle('hidden', false);
            renderProduk();
            input.focus();
            const len = input.value.length;
            input.setSelectionRange(len, len);
        }
    });

    const gridProduk = document.getElementById('grid-produk');
    if (gridProduk) {
        gridProduk.addEventListener('click', (e) => {
            if (skipClick) return;
            const btn = e.target.closest('[data-add]');
            if (btn) {
                highlightedIdx = -1;
                tambahKeCart(Number(btn.dataset.add));
            }
        });
        gridProduk.addEventListener('keydown', (e) => {
            const btn = e.target.closest('[data-add]');
            if (!btn) return;

            const list = barangTampil();
            if (list.length === 0) return;
            const cols = getJmlKolom();

            let hIdx = highlightedIdx;
            if (hIdx < 0) {
                hIdx = list.findIndex((b) => b.id === Number(btn.dataset.add));
                if (hIdx < 0) hIdx = 0;
            }
            highlightedIdx = hIdx;

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
                tambahKeCart(Number(btn.dataset.add));
                focusSorot();
                return;
            } else if (e.key === '-' || e.key === '_') {
                e.preventDefault();
                e.stopPropagation();
                const list = barangTampil();
                const barangId = Number(btn.dataset.add);
                const idx = list.findIndex((b) => b.id === barangId);
                if (idx >= 0) {
                    highlightedIdx = idx;
                    const inCart = state.cart.some((i) => i.barang_id === barangId);
                    if (!inCart) {
                        toast('Item tidak ada di keranjang. Tekan Ctrl+↓ untuk memilih di keranjang', true);
                    } else {
                        ubahJumlah(barangId, -1);
                    }
                    focusSorot();
                }
                return;
            } else if (e.key === '+' || e.key === '=') {
                e.preventDefault();
                e.stopPropagation();
                const list = barangTampil();
                const barangId = Number(btn.dataset.add);
                const idx = list.findIndex((b) => b.id === barangId);
                if (idx >= 0) {
                    highlightedIdx = idx;
                    tambahKeCart(barangId);
                    focusSorot();
                }
                return;
            } else {
                return;
            }

            renderProduk();
            focusSorot();
        });
    }

    const cartItems = document.getElementById('cart-items');
    if (cartItems) {
        cartItems.addEventListener('click', (e) => {
            const rowEl = e.target.closest('[data-cart-row]');
            if (rowEl) {
                const idx = Number(rowEl.dataset.cartRow);
                if (idx !== cartIdx) {
                    cartIdx = idx;
                    render();
                }
            }

            const plus = e.target.closest('[data-plus]');
            const minus = e.target.closest('[data-minus]');
            const del = e.target.closest('[data-del]');
            const unitBtn = e.target.closest('[data-unit-dropdown-btn]');
            const unitSelect = e.target.closest('[data-custom-unit-select]');

            if (plus) ubahJumlah(Number(plus.dataset.plus), 1);
            if (minus) ubahJumlah(Number(minus.dataset.minus), -1);
            if (del) hapusItem(Number(del.dataset.del));
            if (plus || minus || del) {
                setTimeout(fokusCartRow, 0);
            }

            if (unitBtn) {
                e.stopPropagation();
                const barangId = unitBtn.dataset.unitDropdownBtn;
                const menu = document.querySelector(`[data-unit-dropdown-menu="${barangId}"]`);
                const chevron = document.querySelector(`[data-unit-dropdown-chevron="${barangId}"]`);

                const isHidden = menu?.classList.contains('hidden');

                // Close all unit menus and gudang menu
                document.querySelectorAll('[data-unit-dropdown-menu]').forEach((m) => m.classList.add('hidden'));
                document.querySelectorAll('[data-unit-dropdown-chevron]').forEach((c) => c.classList.remove('rotate-180'));
                document.querySelectorAll('[data-dd-menu]').forEach((m) => m.classList.add('hidden'));
                document.querySelectorAll('[data-dd-chevron]').forEach((c) => c.classList.remove('rotate-180'));

                if (isHidden && menu) {
                    menu.classList.remove('hidden');
                    if (chevron) chevron.classList.add('rotate-180');
                }
            }

            if (unitSelect) {
                e.stopPropagation();
                const barangId = Number(unitSelect.dataset.customUnitSelect);
                const satuan = unitSelect.dataset.unitVal;
                ubahSatuanItem(barangId, satuan);
                setTimeout(fokusCartRow, 0);
            }
        });

        cartItems.addEventListener('change', (e) => {
            const qty = e.target.closest('[data-qty]');
            if (qty) setJumlah(Number(qty.dataset.qty), qty.value);
        });

        cartItems.addEventListener('keydown', (e) => {
            const tag = e.target.tagName;
            if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
            const menuFokus = e.target.closest('[data-unit-dropdown-menu]');
            if (menuFokus) {
                if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
                    e.preventDefault();
                    e.stopPropagation();
                    const opts = Array.from(menuFokus.querySelectorAll('[data-custom-unit-select]'));
                    const cur = opts.indexOf(e.target);
                    const next = opts[Math.min(Math.max(cur + (e.key === 'ArrowDown' ? 1 : -1), 0), opts.length - 1)];
                    if (next) next.focus();
                }
                return;
            }
            if (cartIdx < 0 || cartIdx >= state.cart.length) return;
            const id = state.cart[cartIdx].barang_id;

            if (e.key === 'ArrowUp') {
                e.preventDefault();
                e.stopPropagation();
                pindahCartIdx(-1);
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                e.stopPropagation();
                pindahCartIdx(1);
            } else if (e.key === '-' || e.key === '_') {
                e.preventDefault();
                e.stopPropagation();
                ubahJumlah(id, -1);
                fokusCartRow();
            } else if (e.key === '+' || e.key === '=') {
                e.preventDefault();
                e.stopPropagation();
                tambahKeCart(id);
                fokusCartRow();
            } else if (e.key === 'Delete' || e.key === 'Backspace') {
                e.preventDefault();
                e.stopPropagation();
                hapusItem(id);
                fokusCartRow();
            } else if (e.key === 'r' || e.key === 'R') {
                e.preventDefault();
                e.stopPropagation();
                const menu = document.querySelector(`[data-unit-dropdown-menu="${id}"]`);
                if (menu) {
                    document.querySelectorAll('[data-unit-dropdown-menu]').forEach((m) => m.classList.add('hidden'));
                    document.querySelectorAll('[data-unit-dropdown-chevron]').forEach((c) => c.classList.remove('rotate-180'));
                    menu.classList.remove('hidden');
                    const chevron = document.querySelector(`[data-unit-dropdown-chevron="${id}"]`);
                    if (chevron) chevron.classList.add('rotate-180');
                    const opt = menu.querySelector('[data-custom-unit-select]');
                    if (opt) opt.focus();
                }
            }
        });
    }

    const inputDiskon = document.getElementById('input-diskon');
    if (inputDiskon) {
        inputDiskon.addEventListener('input', (e) => {
            let raw = e.target.value.replace(/\D/g, '');
            if (raw !== '') {
                let val = Math.min(100, Number(raw));
                e.target.value = val;
                state.diskonTransaksi = val;
            } else {
                e.target.value = '';
                state.diskonTransaksi = 0;
            }
            renderCart();
        });
    }

    const inputBayar = document.getElementById('input-bayar');
    if (inputBayar) {
        inputBayar.addEventListener('input', (e) => {
            let raw = e.target.value.replace(/\D/g, '');
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
    }

    document.getElementById('btn-uang-pas')?.addEventListener('click', () => {
        state.bayar = totalNeto();
        renderCart();
    });

    document.querySelectorAll('input[name="jenis_pembayaran"]').forEach((radio) => {
        radio.addEventListener('change', (e) => {
            state.jenisPembayaran = e.target.value;
            renderCart();
        });
    });

    document.querySelectorAll('input[name="bank_transfer"]').forEach((radio) => {
        radio.addEventListener('change', (e) => {
            state.bankTransfer = e.target.value;
        });
    });

    document.getElementById('btn-toggle-payment')?.addEventListener('click', () => {
        togglePaymentDetails();
    });
    document.getElementById('btn-bayar')?.addEventListener('click', prosesBayar);

    document.getElementById('btn-reset')?.addEventListener('click', () => {
        if (state.cart.length > 0) bukaModalReset();
    });
    document.getElementById('btn-batal-reset')?.addEventListener('click', tutupModalReset);
    document.getElementById('btn-konfirmasi-reset')?.addEventListener('click', () => {
        tutupModalReset();
        resetTransaksi();
    });

    const modalReset = document.getElementById('modal-konfirmasi-reset');
    modalReset?.addEventListener('click', (e) => {
        if (e.target === modalReset) tutupModalReset();
    });
    modalReset?.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
            const batal = document.getElementById('btn-batal-reset');
            const konfirmasi = document.getElementById('btn-konfirmasi-reset');
            e.preventDefault();
            if (document.activeElement === batal) konfirmasi?.focus();
            else batal?.focus();
        }
    });

    document.getElementById('btn-tutup-struk')?.addEventListener('click', () => {
        document.getElementById('modal-struk')?.classList.add('hidden');
        document.getElementById('input-search')?.focus();
    });
    document.getElementById('btn-print-struk')?.addEventListener('click', () => window.print());

    // ------------------------- SHORTCUT -------------------------
    const modalPanduan = document.getElementById('modal-panduan-shortcut');

    document.getElementById('btn-panduan-shortcut')?.addEventListener('click', bukaPanduanShortcut);
    document.getElementById('btn-tutup-panduan-shortcut')?.addEventListener('click', tutupPanduanShortcut);
    document.getElementById('btn-selesai-panduan-shortcut')?.addEventListener('click', tutupPanduanShortcut);
    modalPanduan?.addEventListener('click', (e) => {
        if (e.target === modalPanduan) tutupPanduanShortcut();
    });

    document.addEventListener('keydown', (e) => {
        const panduanTerbuka = !modalPanduan?.classList.contains('hidden');
        const mod = e.ctrlKey || e.metaKey;
        const inInput = ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName);

        if (e.key === 'Escape' && !mod && !e.altKey) {
            e.preventDefault();
            e.stopImmediatePropagation();
            if (panduanTerbuka) {
                tutupPanduanShortcut();
                return;
            }
            const menuFokus = document.activeElement?.closest('[data-unit-dropdown-menu]');
            if (menuFokus) {
                menuFokus.classList.add('hidden');
                const idMenu = menuFokus.dataset.unitDropdownMenu;
                const chevron = document.querySelector(`[data-unit-dropdown-chevron="${idMenu}"]`);
                if (chevron) chevron.classList.remove('rotate-180');
                fokusCartRow();
                return;
            }
            const urutanModal = ['modal-struk', 'modal-konfirmasi-reset', 'modal-konfirmasi-gudang'];
            const terbuka = urutanModal.find((id) => {
                const el = document.getElementById(id);
                return el && !el.classList.contains('hidden');
            });
            if (terbuka === 'modal-konfirmasi-gudang') {
                pendingGudangId = null;
                if (gudangSetValue) gudangSetValue(state.gudangId);
            }
            if (terbuka) {
                document.getElementById(terbuka)?.classList.add('hidden');
                document.getElementById('input-search')?.focus();
                return;
            }
            highlightedIdx = -1;
            cartIdx = -1;
            render();
            document.getElementById('input-search')?.focus();
            return;
        }

        if (e.key === '?' && !mod && !e.altKey && !inInput) {
            if (panduanTerbuka) {
                e.preventDefault();
                e.stopImmediatePropagation();
                tutupPanduanShortcut();
                return;
            }
            const adaModalLain = ['modal-struk', 'modal-konfirmasi-reset', 'modal-konfirmasi-gudang'].some((id) => {
                const el = document.getElementById(id);
                return el && !el.classList.contains('hidden');
            });
            if (adaModalLain) return;
            e.preventDefault();
            e.stopImmediatePropagation();
            bukaPanduanShortcut();
            return;
        }

        if (panduanTerbuka) {
            if (e.key === 'Tab') {
                const focusables = modalPanduan.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
                const list = Array.from(focusables).filter((el) => !el.disabled && el.offsetParent !== null);
                if (list.length > 0) {
                    const first = list[0];
                    const last = list[list.length - 1];
                    if (e.shiftKey && document.activeElement === first) {
                        e.preventDefault();
                        last.focus();
                    } else if (!e.shiftKey && document.activeElement === last) {
                        e.preventDefault();
                        first.focus();
                    }
                }
                return;
            }
            const isTypingChar = e.key.length === 1 && !mod && !e.altKey;
            const isAppShortcut =
                (mod && !e.altKey && ['k', 'u', 'p', '1', '2', '3', 'enter', 'arrowdown', 'arrowup'].includes(e.key.toLowerCase())) ||
                e.key === 'F9';
            if ((isTypingChar && !inInput) || isAppShortcut) {
                e.preventDefault();
                e.stopImmediatePropagation();
                return;
            }
        }

        const modalLainTerbuka = ['modal-struk', 'modal-konfirmasi-reset', 'modal-konfirmasi-gudang'].some((id) => {
            const el = document.getElementById(id);
            return el && !el.classList.contains('hidden');
        });
        if (modalLainTerbuka) return;

        if (e.key === 'F9') {
            e.preventDefault();
            e.stopImmediatePropagation();
            if (state.cart.length > 0) {
                bukaModalReset();
            } else {
                toast('Keranjang masih kosong', true);
            }
            return;
        }

        if (mod && !e.altKey && e.key === 'ArrowUp') {
            e.preventDefault();
            e.stopImmediatePropagation();
            if (state.cart.length === 0) {
                toast('Keranjang masih kosong', true);
                return;
            }
            cartIdx = 0;
            render();
            fokusCartRow();
            return;
        }

        if (mod && !e.altKey && e.key === 'ArrowDown') {
            e.preventDefault();
            e.stopImmediatePropagation();
            if (state.cart.length === 0) {
                toast('Keranjang masih kosong', true);
                return;
            }
            cartIdx = state.cart.length - 1;
            render();
            fokusCartRow();
            return;
        }

        if (mod && !e.altKey && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            e.stopImmediatePropagation();
            const input = document.getElementById('input-search');
            if (input) {
                input.focus();
                input.setSelectionRange(input.value.length, input.value.length);
            }
            return;
        }

        if (mod && !e.altKey && e.key === 'Enter') {
            e.preventDefault();
            e.stopImmediatePropagation();
            prosesBayar();
            return;
        }

        if (mod && !e.altKey && e.key.toLowerCase() === 'u') {
            e.preventDefault();
            e.stopImmediatePropagation();
            if (state.cart.length === 0) {
                toast('Keranjang masih kosong', true);
                return;
            }
            if (!state.paymentExpanded) togglePaymentDetails(true);
            state.bayar = totalNeto();
            renderCart();
            return;
        }

        if (mod && !e.altKey && e.key.toLowerCase() === 'p') {
            e.preventDefault();
            e.stopImmediatePropagation();
            togglePaymentDetails();
            return;
        }

        if (mod && !e.altKey && (e.key === '1' || e.key === '2' || e.key === '3')) {
            e.preventDefault();
            e.stopImmediatePropagation();
            const mapPembayaran = { '1': 'tunai', '2': 'qris', '3': 'transfer' };
            state.jenisPembayaran = mapPembayaran[e.key];
            document.querySelectorAll('input[name="jenis_pembayaran"]').forEach((radio) => {
                radio.checked = radio.value === state.jenisPembayaran;
            });
            renderCart();
            return;
        }
    }, true);

    if (USE_MOCK) {
        document.getElementById('badge-mock')?.classList.remove('hidden');
    }

    document.getElementById('loading')?.classList.add('hidden');
    document.getElementById('kasir-app')?.classList.remove('hidden');
    render();
}

init().catch((e) => {
    const loading = document.getElementById('loading');
    if (loading) {
        loading.innerHTML = `
            <div class="flex-1 flex flex-col items-center justify-center gap-2 text-center px-6">
                <p class="text-sm font-bold text-zinc-900">Gagal memuat data</p>
                <p class="text-sm text-zinc-500">${e.message}</p>
                <button onclick="location.reload()"
                    class="mt-3 text-sm font-bold bg-zinc-900 hover:bg-zinc-800 text-white rounded-xl px-5 py-2.5 transition">
                    Muat ulang
                </button>
            </div>`;
    }
});
