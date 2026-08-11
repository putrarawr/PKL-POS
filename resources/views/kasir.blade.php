<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Kasir - {{ $kasirData['toko']['nama'] ?? 'Toko PKL' }}</title>
    <link rel="preconnect" href="https://api.fontshare.com">
    <link href="https://api.fontshare.com/v2/css?f[]=satoshi@400,500,700,900&display=swap" rel="stylesheet">
    @isset($kasirData)
        <script>window.KASIR_DATA = @json($kasirData);</script>
    @endisset
    @vite(['resources/css/app.css', 'resources/js/kasir/kasir.js'])
    <style>
        body { font-family: 'Satoshi', ui-sans-serif, system-ui, sans-serif; }
        .kbd-shortcut {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 1.75rem;
            padding: 0.15rem 0.5rem;
            background: #f4f4f5;
            border: 1px solid #e4e4e7;
            border-bottom-width: 2px;
            border-radius: 0.4rem;
            font-size: 0.7rem;
            font-weight: 700;
            color: #3f3f46;
            font-family: ui-monospace, 'Cascadia Code', Menlo, monospace;
            line-height: 1.25;
        }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-thumb { background: #d4d4d8; border-radius: 99px; }
        ::-webkit-scrollbar-track { background: transparent; }

        @media (prefers-reduced-motion: no-preference) {
            .anim-fade-up {
                animation: fade-up .45s cubic-bezier(.16, 1, .3, 1) both;
                animation-delay: calc(var(--i, 0) * 35ms);
            }
            .anim-scale-in { animation: scale-in .22s cubic-bezier(.16, 1, .3, 1) both; }
            .anim-backdrop { animation: fade .2s ease-out both; }
            .anim-toast { animation: toast-up .3s cubic-bezier(.16, 1, .3, 1) both; }
            .anim-pop { animation: pop .25s cubic-bezier(.16, 1, .3, 1); }
        }
        @keyframes fade-up { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
        @keyframes scale-in { from { opacity: 0; transform: scale(.97) translateY(-4px); } to { opacity: 1; transform: none; } }
        @keyframes fade { from { opacity: 0; } to { opacity: 1; } }
        @keyframes toast-up { from { opacity: 0; transform: translate(-50%, 12px); } to { opacity: 1; transform: translate(-50%, 0); } }
        @keyframes pop { 0% { transform: scale(1); } 40% { transform: scale(1.05); } 100% { transform: scale(1); } }

        @media print {
            body * { visibility: hidden; }
            #modal-struk, #modal-struk * { visibility: visible; }
            #modal-struk { position: absolute; inset: 0; background: white; }
            #struk-actions { display: none !important; }
        }
    </style>
</head>
<body class="bg-zinc-50 text-zinc-900 antialiased">

    {{-- LOADING SKELETON --}}
    <div id="loading" class="fixed inset-0 flex flex-col h-dvh">
        <div class="h-16 shrink-0 bg-white border-b border-zinc-200 px-6 flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-zinc-200 animate-pulse"></div>
            <div class="space-y-1.5">
                <div class="w-16 h-2.5 rounded bg-zinc-200 animate-pulse"></div>
                <div class="w-12 h-2 rounded bg-zinc-100 animate-pulse"></div>
            </div>
        </div>
        <div class="flex-1 flex overflow-hidden">
            <div class="flex-1 px-8 pt-7">
                <div class="max-w-5xl mx-auto space-y-5">
                    <div class="h-12 rounded-xl bg-zinc-200/70 animate-pulse"></div>
                    <div class="h-9 w-72 rounded-xl bg-zinc-200/50 animate-pulse"></div>
                    <div class="grid grid-cols-2 md:grid-cols-3 2xl:grid-cols-4 gap-4">
                        <div class="h-32 rounded-2xl bg-zinc-200/60 animate-pulse"></div>
                        <div class="h-32 rounded-2xl bg-zinc-200/60 animate-pulse [animation-delay:100ms]"></div>
                        <div class="h-32 rounded-2xl bg-zinc-200/60 animate-pulse [animation-delay:200ms]"></div>
                        <div class="h-32 rounded-2xl bg-zinc-200/60 animate-pulse [animation-delay:300ms] hidden 2xl:block"></div>
                    </div>
                </div>
            </div>
            <div class="w-[360px] xl:w-[420px] shrink-0 bg-white border-l border-zinc-200"></div>
        </div>
    </div>

    {{-- MAIN KASIR APP --}}
    <div id="kasir-app" class="hidden h-dvh flex flex-col">

        {{-- HEADER --}}
        <header class="anim-fade-up relative z-30 h-16 shrink-0 bg-white border-b border-zinc-200 px-6 flex items-center gap-6" style="--i: 0">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-zinc-900 text-white flex items-center justify-center font-black text-sm select-none">K</div>
                <div class="leading-tight">
                    <h1 class="text-sm font-bold tracking-tight">Kasir</h1>
                    <p class="text-[11px] font-medium text-zinc-400">@if(isset($kasirData['karyawan'])) {{ $kasirData['karyawan']['nama'] }} • @endif {{ $kasirData['toko']['nama'] ?? 'Toko PKL' }}</p>
                </div>
            </div>

            <span id="badge-mock"
                class="hidden text-[11px] font-semibold text-amber-700 bg-amber-50 border border-amber-200 px-2.5 py-1 rounded-full">
                Mode simulasi
            </span>

            <div class="flex-1"></div>

            <div class="flex items-center gap-3">
                <div id="dd-gudang" class="relative">
                    <button type="button" data-dd-btn
                        class="flex items-center gap-2 border border-zinc-200 rounded-xl bg-white pl-4 pr-3 py-2.5 hover:border-zinc-400 transition-colors cursor-pointer">
                        <span data-dd-value class="text-sm font-bold"></span>
                        <svg data-dd-chevron class="w-3.5 h-3.5 text-zinc-400 transition-transform duration-200"
                            viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 6l4 4 4-4"/>
                        </svg>
                    </button>
                    <div data-dd-menu
                        class="hidden anim-scale-in absolute right-0 top-full mt-2 min-w-full w-max max-h-72 overflow-y-auto z-30 bg-white border border-zinc-200 rounded-xl shadow-xl shadow-zinc-950/10 p-1.5">
                    </div>
                </div>

                {{-- Tombol Panduan Shortcut --}}
                <button id="btn-panduan-shortcut" type="button" title="Panduan shortcut (?)"
                    class="w-10 h-10 flex items-center justify-center rounded-xl border border-zinc-200 text-zinc-400 hover:text-zinc-900 hover:border-zinc-400 hover:bg-zinc-50 transition-all duration-200 cursor-pointer">
                    <svg class="w-4.5 h-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="6" width="20" height="12" rx="2"/>
                        <path d="M6 10h.01M10 10h.01M14 10h.01M18 10h.01M6 14h.01M18 14h.01M9 14h6"/>
                    </svg>
                </button>

                {{-- Tombol Riwayat Transaksi --}}
                <button id="btn-riwayat" type="button" title="Riwayat transaksi"
                    class="w-10 h-10 flex items-center justify-center rounded-xl border border-zinc-200 text-zinc-400 hover:text-zinc-900 hover:border-zinc-400 hover:bg-zinc-50 transition-all duration-200 cursor-pointer">
                    <svg class="w-4.5 h-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 12a9 9 0 1 0 9 -9a9.75 9.75 0 0 0 -6.74 2.74l-2.26 -2"/>
                        <path d="M3 3v5h5"/>
                        <path d="M12 7v5l3 3"/>
                    </svg>
                </button>

                {{-- Tombol Logout --}}
                <form method="POST" action="{{ route('kasir.logout') }}">
                    @csrf
                    <button type="submit" title="Logout"
                        class="w-10 h-10 flex items-center justify-center rounded-xl border border-zinc-200 text-zinc-400 hover:text-red-600 hover:border-red-200 hover:bg-red-50 transition-all duration-200 cursor-pointer">
                        <svg class="w-4.5 h-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 8v-2a2 2 0 0 0 -2 -2h-7a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2 -2v-2"/>
                            <path d="M9 12h12l-3 -3"/>
                            <path d="M18 15l3 -3"/>
                        </svg>
                    </button>
                </form>
            </div>
        </header>

        <div class="flex-1 flex overflow-hidden">

            {{-- KIRI: DAFTAR PRODUK --}}
            <main class="flex-1 flex flex-col min-w-0 px-8 pt-7 overflow-hidden">
                <div class="anim-fade-up max-w-5xl w-full mx-auto flex flex-col flex-1 overflow-hidden" style="--i: 1">
                    <div class="relative">
                        <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-4.5 h-4.5 text-zinc-400 pointer-events-none"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0"/><path d="M21 21l-6 -6"/>
                        </svg>
                        <input id="input-search" type="text" placeholder="Cari nama atau kode barang (cth: WT)"
                            class="w-full h-12 border border-zinc-200 rounded-xl pl-11 pr-10 text-sm font-medium bg-white placeholder:text-zinc-400 placeholder:font-normal shadow-xs shadow-zinc-100 focus:outline-none focus:border-zinc-900 transition-colors">
                        <button id="btn-clear-search" class="absolute right-3 top-1/2 -translate-y-1/2 w-7 h-7 text-zinc-400 hover:text-zinc-900 hidden flex items-center justify-center transition-colors">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                        </button>
                    </div>

                    <div id="filter-jenis" class="inline-flex flex-wrap w-fit gap-1 bg-zinc-200/60 rounded-xl p-1 mt-4 mb-4"></div>
                    <div id="label-gudang-aktif" class="hidden mb-6 text-[11px] font-semibold text-zinc-400 flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-zinc-400"></span>
                        Stok dari: <span class="font-bold text-zinc-600" id="label-gudang-aktif-nama"></span>
                    </div>

                    <div id="grid-produk"
                        class="flex-1 overflow-y-auto grid grid-cols-2 md:grid-cols-3 2xl:grid-cols-4 gap-4 content-start pt-3 pb-8 px-2"></div>
                </div>
            </main>

            {{-- KANAN: KERANJANG --}}
            <aside class="anim-fade-up w-[360px] xl:w-[420px] shrink-0 bg-white border-l border-zinc-200 flex flex-col" style="--i: 2">
                <div class="h-16 shrink-0 px-6 border-b border-zinc-100 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <h2 class="font-bold tracking-tight">Pesanan</h2>
                        <span id="badge-cart-count"
                            class="hidden min-w-6 h-6 px-1.5 rounded-full bg-zinc-900 text-white text-xs font-bold flex items-center justify-center tabular-nums"></span>
                        <span id="lbl-item-jenis" class="hidden text-xs font-semibold text-zinc-400"></span>
                    </div>
                    <button id="btn-reset"
                        class="text-xs font-semibold text-zinc-400 hover:text-red-600 transition-colors cursor-pointer">Kosongkan</button>
                </div>

                <p id="hint-cart-selected" class="hidden shrink-0 bg-zinc-900 text-white text-[11px] font-semibold px-6 py-2 flex items-center gap-1.5">
                    <span class="tabular-nums font-bold" id="hint-cart-pos"></span>
                    <span class="text-zinc-300 font-normal">·</span>
                    <span>↑↓ pindah · +/− qty · R satuan · Del hapus · Esc batal</span>
                </p>

                <div id="cart-items" class="flex-1 overflow-y-auto px-6 pt-4 pb-20 space-y-3"></div>

                <div class="shrink-0 border-t border-zinc-200 bg-zinc-50/80 px-6 pt-5 pb-6 space-y-4">

                    <div class="space-y-2.5 text-sm">
                        <div class="flex justify-between items-center">
                            <span class="text-zinc-500">Subtotal</span>
                            <span id="lbl-total" class="font-bold tabular-nums">Rp 0</span>
                        </div>
                        <div class="flex justify-between items-center gap-3">
                            <span class="text-zinc-500">Diskon (%)</span>
                            <input id="input-diskon" type="text" inputmode="numeric" placeholder="0"
                                class="w-20 text-right text-sm font-semibold bg-white border border-zinc-200 rounded-lg px-3 py-1.5 tabular-nums placeholder:font-normal placeholder:text-zinc-300 focus:outline-none focus:border-zinc-900 transition-colors">
                        </div>
                        <div class="flex justify-between items-baseline border-t border-zinc-100 pt-3">
                            <span class="font-bold">Total</span>
                            <span id="lbl-neto" class="inline-block origin-right text-2xl font-black tracking-tight tabular-nums">Rp 0</span>
                        </div>
                        <p id="rangkuman-bayaran" class="hidden flex justify-between items-center text-sm tabular-nums"></p>
                    </div>

                    <button id="btn-toggle-payment" type="button"
                        class="w-full flex items-center justify-between text-[11px] font-bold text-zinc-400 hover:text-zinc-600 transition-colors py-1.5 cursor-pointer select-none border-t border-zinc-100 pt-3.5">
                        <span>PILIHAN & RINCIAN PEMBAYARAN</span>
                        <svg id="icon-toggle-payment" class="w-4 h-4 transition-transform duration-200" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 6l4 4 4-4"/>
                        </svg>
                    </button>

                    <div id="payment-details-container" class="space-y-4 hidden">
                        <div class="flex gap-1 bg-zinc-200/60 rounded-xl p-1">
                            @foreach (['tunai' => 'Tunai', 'qris' => 'QRIS', 'transfer' => 'Transfer'] as $val => $label)
                                <label class="flex-1 text-center text-sm font-semibold text-zinc-500 rounded-lg py-2 cursor-pointer transition
                                              has-checked:bg-white has-checked:text-zinc-900 has-checked:shadow-xs">
                                    <input type="radio" name="jenis_pembayaran" value="{{ $val }}"
                                        class="hidden" {{ $val === 'tunai' ? 'checked' : '' }}>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>

                        <div id="row-tunai" class="space-y-2.5 text-sm">
                            <div class="flex justify-between items-center">
                                <span class="text-zinc-500">Uang diterima</span>
                                <input id="input-bayar" type="text" inputmode="numeric" placeholder="Rp 0"
                                    class="w-32 text-right text-sm font-semibold bg-white border border-zinc-200 rounded-lg px-3 py-1.5 tabular-nums placeholder:font-normal placeholder:text-zinc-300 focus:outline-none focus:border-zinc-900 transition-colors">
                            </div>
                            <button id="btn-uang-pas" type="button"
                                class="w-full text-xs font-semibold text-zinc-600 bg-white border border-zinc-200 hover:border-zinc-900 hover:text-zinc-900 rounded-lg py-2 tabular-nums transition-colors cursor-pointer">Uang pas</button>
                            <div class="flex justify-between items-center">
                                <span class="text-zinc-500">Kembalian</span>
                                <span id="lbl-kembalian" class="font-bold tabular-nums">Rp 0</span>
                            </div>
                        </div>

                        {{-- QRIS --}}
                        <div id="row-qris" style="display:none">
                            <div class="bg-white border border-zinc-200 rounded-xl p-4 flex items-center gap-4">
                                <img src="{{ asset('img/pay/qris-dummy.png') }}" alt="Kode QRIS"
                                    class="w-24 h-24 rounded-lg border border-zinc-100 [image-rendering:pixelated]">
                                <div class="min-w-0">
                                    <img src="{{ asset('img/pay/qris.svg') }}" alt="QRIS" class="h-5 mb-1.5">
                                    <p class="text-xs font-semibold text-zinc-900">Scan untuk membayar</p>
                                    <p class="text-xs text-zinc-400 mt-0.5">Kode contoh, bukan pembayaran sungguhan</p>
                                </div>
                            </div>
                        </div>

                        {{-- Transfer --}}
                        <div id="row-transfer" style="display:none" class="grid grid-cols-2 gap-2">
                            @foreach (['bca' => 'BCA', 'mandiri' => 'Mandiri', 'bri' => 'BRI', 'bni' => 'BNI'] as $kode => $nama)
                                <label class="bank-opt bg-white border border-zinc-200 rounded-xl px-3 py-2.5 flex items-center justify-center cursor-pointer transition
                                              hover:border-zinc-400 has-checked:border-zinc-900 has-checked:ring-1 has-checked:ring-zinc-900">
                                    <input type="radio" name="bank_transfer" value="{{ $nama }}"
                                        class="hidden" {{ $kode === 'bca' ? 'checked' : '' }}>
                                    <img src="{{ asset('img/pay/' . $kode . '.svg') }}" alt="{{ $nama }}" class="h-5 max-w-full object-contain">
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex gap-2.5">
                        <button id="btn-preview-struk" type="button"
                            class="flex-1 border border-zinc-200 hover:border-zinc-900 hover:bg-zinc-50 active:scale-[0.99] text-zinc-700 font-bold rounded-xl py-3.5 text-sm transition-colors cursor-pointer">Preview Struk</button>
                        <button id="btn-bayar" type="button"
                            class="flex-1 bg-zinc-900 hover:bg-zinc-800 active:scale-[0.99] disabled:opacity-40 disabled:pointer-events-none text-white text-sm font-black rounded-xl py-3.5 tabular-nums transition cursor-pointer shadow-lg shadow-zinc-900/20 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-zinc-900/25">
                        Bayar
                    </button>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    {{-- MODAL STRUK --}}
    <div id="modal-struk" class="hidden anim-backdrop fixed inset-0 bg-zinc-950/50 backdrop-blur-xs flex items-center justify-center z-40 p-4">
        <div class="anim-scale-in bg-white rounded-2xl w-full max-w-sm p-7 max-h-[90dvh] overflow-y-auto shadow-2xl">
            <div id="struk-body"></div>
            <div id="struk-actions" class="flex gap-2.5 mt-7">
                <button id="btn-print-struk" type="button"
                    class="flex-1 bg-zinc-900 hover:bg-zinc-800 active:scale-[0.99] text-white font-bold rounded-xl py-3 text-sm transition cursor-pointer">Cetak Struk</button>
                <button id="btn-tutup-struk" type="button"
                    class="flex-1 border border-zinc-200 hover:border-zinc-900 active:scale-[0.99] text-zinc-700 font-bold rounded-xl py-3 text-sm transition-colors cursor-pointer">Transaksi Baru</button>
            </div>
        </div>
    </div>

    {{-- MODAL KONFIRMASI KOSONGKAN --}}
    <div id="modal-konfirmasi-reset" class="hidden anim-backdrop fixed inset-0 bg-zinc-950/50 backdrop-blur-xs flex items-center justify-center z-40 p-4">
        <div class="anim-scale-in bg-white rounded-2xl w-full max-w-sm p-7 shadow-2xl">
            <div class="text-center">
                <div class="w-12 h-12 mx-auto mb-4 rounded-full bg-zinc-100 flex items-center justify-center">
                    <svg class="w-6 h-6 text-zinc-900" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 6h18"/><path d="M8 6v-2a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2h-8a2 2 0 0 1-2-2l-1-14"/><path d="M10 11v6"/><path d="M14 11v6"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold tracking-tight">Kosongkan Pesanan?</h3>
                <p class="text-sm text-zinc-500 mt-2"><span id="label-reset-count" class="font-bold text-zinc-900"></span> di keranjang akan dihapus. Tindakan ini tidak bisa dibatalkan.</p>
            </div>
            <div class="flex gap-2.5 mt-7">
                <button id="btn-batal-reset" type="button"
                    class="flex-1 border border-zinc-200 hover:border-zinc-900 active:scale-[0.99] text-zinc-700 font-bold rounded-xl py-3 text-sm transition-colors cursor-pointer">Batal</button>
                <button id="btn-konfirmasi-reset" type="button"
                    class="flex-1 bg-zinc-900 hover:bg-zinc-800 active:scale-[0.99] text-white font-bold rounded-xl py-3 text-sm transition cursor-pointer">Ya, Kosongkan</button>
            </div>
        </div>
    </div>

    {{-- MODAL KONFIRMASI HAPUS ITEM --}}
    <div id="modal-konfirmasi-hapus" class="hidden anim-backdrop fixed inset-0 bg-zinc-950/40 backdrop-blur-sm flex items-center justify-center z-50 p-4">
        <div class="anim-scale-in bg-white rounded-2xl w-full max-w-sm p-7 shadow-2xl">
            <div class="text-center">
                <div class="w-12 h-12 mx-auto mb-4 rounded-full bg-zinc-100 flex items-center justify-center">
                    <svg class="w-6 h-6 text-zinc-900" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 6h18"/><path d="M8 6v-2a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2h-8a2 2 0 0 1-2-2l-1-14"/><path d="M10 11v6"/><path d="M14 11v6"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold tracking-tight">Hapus Item Ini?</h3>
                <p class="text-sm text-zinc-500 mt-2"><span id="label-hapus-item" class="font-bold text-zinc-900"></span> akan dihapus dari pesanan.</p>
            </div>
            <div class="flex gap-2.5 mt-7">
                <button id="btn-batal-hapus" type="button"
                    class="flex-1 border border-zinc-200 hover:border-zinc-900 active:scale-[0.99] text-zinc-700 font-bold rounded-xl py-3 text-sm transition-colors cursor-pointer">Batal</button>
                <button id="btn-konfirmasi-hapus" type="button"
                    class="flex-1 bg-zinc-900 hover:bg-zinc-800 active:scale-[0.99] text-white font-bold rounded-xl py-3 text-sm transition cursor-pointer">Ya, Hapus</button>
            </div>
        </div>
    </div>

    {{-- MODAL KONFIRMASI PINDAH GUDANG --}}
    <div id="modal-konfirmasi-gudang" class="hidden anim-backdrop fixed inset-0 bg-zinc-950/50 backdrop-blur-xs flex items-center justify-center z-50 p-4">
        <div class="anim-scale-in bg-white rounded-2xl w-full max-w-sm p-7 shadow-2xl">
            <div class="text-center">
                <div class="w-12 h-12 mx-auto mb-4 rounded-full bg-zinc-100 flex items-center justify-center">
                    <svg class="w-6 h-6 text-zinc-900" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 9v4"/><path d="M12 17h.01"/><path d="M5 19h14a2 2 0 0 0 1.84-2.75L13.74 4.15a2 2 0 0 0-3.48 0L3.16 16.25A2 2 0 0 0 5 19z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold tracking-tight">Pindah Gudang Penyimpanan?</h3>
                <p class="text-sm text-zinc-500 mt-2">
                    Ada item di keranjang belanja. Jika Anda pindah ke <span id="target-nama-gudang" class="font-bold text-zinc-900">gudang ini</span>, pesanan saat ini akan dikosongkan.
                </p>
            </div>
            <div class="flex gap-2.5 mt-7">
                <button id="btn-batal-gudang" type="button"
                    class="flex-1 border border-zinc-200 hover:border-zinc-900 active:scale-[0.99] text-zinc-700 font-bold rounded-xl py-3 text-sm transition-colors cursor-pointer">Batal</button>
                <button id="btn-konfirmasi-gudang" type="button"
                    class="flex-1 bg-zinc-900 hover:bg-zinc-800 active:scale-[0.99] text-white font-bold rounded-xl py-3 text-sm transition cursor-pointer">Ya, Pindah Gudang</button>
            </div>
        </div>
    </div>

    {{-- MODAL PANDUAN SHORTCUT --}}
    <div id="modal-panduan-shortcut" class="hidden anim-backdrop fixed inset-0 bg-zinc-950/40 backdrop-blur-sm flex items-center justify-center z-50 p-4">
        <div class="anim-scale-in bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-zinc-100">
                <h3 class="text-sm font-bold tracking-tight">Shortcut</h3>
                <button id="btn-tutup-panduan-shortcut" type="button"
                    class="w-8 h-8 flex items-center justify-center rounded-lg text-zinc-400 hover:text-zinc-900 hover:bg-zinc-100 transition-colors cursor-pointer">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                        <line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>

            <div class="px-6 py-5 divide-y divide-zinc-100 max-h-[60dvh] overflow-y-auto">
                <div class="flex items-center justify-between gap-4 py-2.5 first:pt-0">
                    <span class="text-sm text-zinc-600">Buka panduan ini</span>
                    <kbd class="kbd-shortcut">?</kbd>
                </div>
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <span class="text-sm text-zinc-600">Cari barang</span>
                    <kbd class="kbd-shortcut">F2</kbd>
                </div>
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <span class="text-sm text-zinc-600">Buka riwayat transaksi</span>
                    <kbd class="kbd-shortcut">F3</kbd>
                </div>
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <span class="text-sm text-zinc-600">Siklus metode pembayaran</span>
                    <kbd class="kbd-shortcut">F4</kbd>
                </div>
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <span class="text-sm text-zinc-600">Navigasi daftar barang</span>
                    <span class="flex items-center gap-1"><kbd class="kbd-shortcut">←</kbd><span class="text-xs text-zinc-300 font-semibold">/</span><kbd class="kbd-shortcut">→</kbd><span class="text-xs text-zinc-300 font-semibold">/</span><kbd class="kbd-shortcut">↑</kbd><span class="text-xs text-zinc-300 font-semibold">/</span><kbd class="kbd-shortcut">↓</kbd></span>
                </div>
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <span class="text-sm text-zinc-600">Kurangi jumlah barang</span>
                    <kbd class="kbd-shortcut">-</kbd>
                </div>
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <span class="text-sm text-zinc-600">Tambah jumlah barang</span>
                    <span class="flex items-center gap-1"><kbd class="kbd-shortcut">+</kbd><span class="text-xs text-zinc-300 font-semibold">/</span><kbd class="kbd-shortcut">Enter</kbd></span>
                </div>
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <span class="text-sm text-zinc-600">Pilih barang keranjang terakhir</span>
                    <span class="flex items-center gap-1"><kbd class="kbd-shortcut">Ctrl</kbd><span class="text-xs text-zinc-300 font-semibold">+</span><kbd class="kbd-shortcut">↓</kbd></span>
                </div>
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <span class="text-sm text-zinc-600">Pilih barang keranjang pertama</span>
                    <span class="flex items-center gap-1"><kbd class="kbd-shortcut">Ctrl</kbd><span class="text-xs text-zinc-300 font-semibold">+</span><kbd class="kbd-shortcut">↑</kbd></span>
                </div>
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <span class="text-sm text-zinc-600">Pindah antar barang</span>
                    <span class="flex items-center gap-1"><kbd class="kbd-shortcut">↑</kbd><span class="text-xs text-zinc-300 font-semibold">/</span><kbd class="kbd-shortcut">↓</kbd></span>
                </div>
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <span class="text-sm text-zinc-600">Hapus barang terpilih</span>
                    <span class="flex items-center gap-1"><kbd class="kbd-shortcut">Del</kbd><span class="text-xs text-zinc-300 font-semibold">/</span><kbd class="kbd-shortcut">Backspace</kbd></span>
                </div>
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <span class="text-sm text-zinc-600">Ganti satuan (barang terpilih)</span>
                    <span class="flex items-center gap-1"><kbd class="kbd-shortcut">R</kbd><span class="text-xs text-zinc-300 font-semibold">lalu</span><kbd class="kbd-shortcut">↑↓</kbd><span class="text-xs text-zinc-300 font-semibold">+</span><kbd class="kbd-shortcut">Enter</kbd></span>
                </div>
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <span class="text-sm text-zinc-600">Bayar</span>
                    <span class="flex items-center gap-1"><kbd class="kbd-shortcut">Ctrl</kbd><span class="text-xs text-zinc-300 font-semibold">+</span><kbd class="kbd-shortcut">Enter</kbd></span>
                </div>
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <span class="text-sm text-zinc-600">Uang pas</span>
                    <kbd class="kbd-shortcut">F7</kbd>
                </div>
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <span class="text-sm text-zinc-600">Rincian pembayaran</span>
                    <kbd class="kbd-shortcut">F6</kbd>
                </div>
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <span class="text-sm text-zinc-600">Muat ulang data & stok</span>
                    <kbd class="kbd-shortcut">F8</kbd>
                </div>
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <span class="text-sm text-zinc-600">Kosongkan pesanan</span>
                    <kbd class="kbd-shortcut">F9</kbd>
                </div>
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <span class="text-sm text-zinc-600">Tutup modal</span>
                    <kbd class="kbd-shortcut">Esc</kbd>
                </div>
            </div>

            <div class="px-6 py-4 border-t border-zinc-100">
                <button id="btn-selesai-panduan-shortcut" type="button"
                    class="w-full bg-zinc-900 hover:bg-zinc-800 active:scale-[0.99] text-white text-sm font-bold rounded-xl py-3 transition cursor-pointer">Tutup</button>
            </div>
        </div>
    </div>

    {{-- MODAL RIWAYAT TRANSAKSI --}}
    <div id="modal-riwayat" class="hidden anim-backdrop fixed inset-0 bg-zinc-950/40 backdrop-blur-sm flex items-center justify-center z-50 p-4">
        <div class="anim-scale-in bg-white rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden flex flex-col max-h-[85dvh]">
            <div class="flex items-center justify-between px-6 py-4 border-b border-zinc-100">
                <div>
                    <h3 class="text-sm font-bold tracking-tight">Riwayat Transaksi</h3>
                    <p class="text-[11px] text-zinc-400 mt-0.5" id="riwayat-tanggal-label">Transaksi hari ini</p>
                </div>
                <button id="btn-tutup-riwayat" type="button"
                    class="w-8 h-8 flex items-center justify-center rounded-lg text-zinc-400 hover:text-zinc-900 hover:bg-zinc-100 transition-colors cursor-pointer">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                        <line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="shrink-0 flex items-center gap-2 px-6 py-3 border-b border-zinc-100">
                <input id="riwayat-tanggal" type="date"
                    class="flex-1 border border-zinc-200 rounded-lg px-3 py-2 text-sm font-semibold text-zinc-700 bg-white focus:outline-none focus:border-zinc-900 transition-colors cursor-pointer tabular-nums">
                <button id="btn-riwayat-hari-ini" type="button"
                    class="shrink-0 px-4 py-2 rounded-lg border border-zinc-200 text-xs font-bold text-zinc-700 hover:border-zinc-900 hover:bg-zinc-50 transition-colors cursor-pointer">Hari Ini</button>
            </div>
            <div id="riwayat-summary" class="hidden shrink-0 flex items-center justify-between px-6 py-2.5 border-b border-zinc-100 text-xs">
                <span id="riwayat-summary-jumlah" class="font-bold text-zinc-500"></span>
                <span id="riwayat-summary-total" class="font-black text-zinc-900 tabular-nums"></span>
            </div>
            <div id="riwayat-pemuatan" class="px-6 py-12 text-center text-sm font-semibold text-zinc-400">Memuat riwayat…</div>
            <div id="riwayat-list" class="hidden flex-1 overflow-y-auto px-4 py-3 divide-y divide-zinc-100"></div>
            <div id="riwayat-kosong" class="hidden px-6 py-12 text-center">
                <svg class="w-10 h-10 text-zinc-200 mx-auto mb-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="4" y="3" width="16" height="18" rx="2"></rect>
                    <path d="M8 8h8M8 12h8M8 16h5"></path>
                </svg>
                <p class="text-sm font-bold text-zinc-500">Belum ada transaksi pada tanggal ini</p>
            </div>
            <div class="px-4 py-3">
                <button id="btn-riwayat-lebih" type="button"
                    class="hidden w-full border border-zinc-200 hover:border-zinc-900 hover:bg-zinc-50 active:scale-[0.99] text-zinc-700 font-bold rounded-xl py-2.5 text-xs transition-all cursor-pointer">Muat lebih banyak</button>
            </div>
            <div class="px-6 py-4 border-t border-zinc-100">
                <button id="btn-tutup-riwayat-bawah" type="button"
                    class="w-full border border-zinc-200 hover:border-zinc-900 active:scale-[0.99] text-zinc-700 font-bold rounded-xl py-3 text-sm transition-colors cursor-pointer">Tutup</button>
            </div>
        </div>
    </div>

    <div id="toast" class="hidden"></div>
</body>
</html>
