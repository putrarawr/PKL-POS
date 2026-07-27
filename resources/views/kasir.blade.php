<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Kasir - Toko PKL</title>
    <link rel="preconnect" href="https://api.fontshare.com">
    <link href="https://api.fontshare.com/v2/css?f[]=satoshi@400,500,700,900&display=swap" rel="stylesheet">
    {{-- data dari database disuntik ke JS di sini (tanpa API). --}}
    {{-- $kasirData dikirim KasirController@index; @isset biar view tetep bisa --}}
    {{-- dibuka manual tanpa controller (jatuh ke mode mock otomatis) --}}
    @isset($kasirData)
        <script>window.KASIR_DATA = @json($kasirData);</script>
    @endisset
    @vite(['resources/css/app.css', 'resources/js/kasir/kasir.js'])
    <style>
        body { font-family: 'Satoshi', ui-sans-serif, system-ui, sans-serif; }
        /* scrollbar tipis biar gak ganggu layout */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-thumb { background: #d4d4d8; border-radius: 99px; }
        ::-webkit-scrollbar-track { background: transparent; }

        /* ---------- animasi (semua di-skip kalau user set reduced motion) ---------- */
        @media (prefers-reduced-motion: no-preference) {
            /* reveal awal + kartu produk (stagger via --i) */
            .anim-fade-up {
                animation: fade-up .45s cubic-bezier(.16, 1, .3, 1) both;
                animation-delay: calc(var(--i, 0) * 35ms);
            }
            /* modal & dropdown */
            .anim-scale-in { animation: scale-in .22s cubic-bezier(.16, 1, .3, 1) both; }
            .anim-backdrop { animation: fade .2s ease-out both; }
            /* toast */
            .anim-toast { animation: toast-up .3s cubic-bezier(.16, 1, .3, 1) both; }
            /* angka total "pop" halus pas berubah */
            .anim-pop { animation: pop .25s cubic-bezier(.16, 1, .3, 1); }
        }
        @keyframes fade-up { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
        @keyframes scale-in { from { opacity: 0; transform: scale(.97) translateY(-4px); } to { opacity: 1; transform: none; } }
        @keyframes fade { from { opacity: 0; } to { opacity: 1; } }
        @keyframes toast-up { from { opacity: 0; transform: translate(-50%, 12px); } to { opacity: 1; transform: translate(-50%, 0); } }
        @keyframes pop { 0% { transform: scale(1); } 40% { transform: scale(1.05); } 100% { transform: scale(1); } }

        /* pas print, cuma struk yang keluar */
        @media print {
            body * { visibility: hidden; }
            #modal-struk, #modal-struk * { visibility: visible; }
            #modal-struk { position: absolute; inset: 0; background: white; }
            #struk-actions { display: none !important; }
        }
    </style>
</head>
<body class="bg-zinc-50 text-zinc-900 antialiased">

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

    <div id="kasir-app" class="hidden h-dvh flex flex-col">

        {{-- HEADER --}}
        <header class="anim-fade-up relative z-30 h-16 shrink-0 bg-white border-b border-zinc-200 px-6 flex items-center gap-6" style="--i: 0">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-zinc-900 text-white flex items-center justify-center font-black text-sm select-none">K</div>
                <div class="leading-tight">
                    <h1 class="text-sm font-bold tracking-tight">Kasir</h1>
                    <p class="text-[11px] font-medium text-zinc-400">@if(isset($kasirData['karyawan'])) {{ $kasirData['karyawan']['nama'] }} • @endif Toko PKL</p>
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
                        {{-- ikon: Tabler "search" --}}
                        <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-4.5 h-4.5 text-zinc-400 pointer-events-none"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0"/><path d="M21 21l-6 -6"/>
                        </svg>
                        <input id="input-search" type="text" placeholder="Cari nama barang"
                            class="w-full h-12 border border-zinc-200 rounded-xl pl-11 pr-10 text-sm font-medium bg-white placeholder:text-zinc-400 placeholder:font-normal shadow-sm shadow-zinc-100 focus:outline-none focus:border-zinc-900 transition-colors">
                        <button id="btn-clear-search" class="absolute right-3 top-1/2 -translate-y-1/2 w-7 h-7 text-zinc-400 hover:text-zinc-900 hidden flex items-center justify-center transition-colors">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                        </button>
                    </div>

                    <div id="filter-jenis" class="inline-flex flex-wrap w-fit gap-1 bg-zinc-200/60 rounded-xl p-1 mt-4 mb-6"></div>

                    <div id="grid-produk"
                        class="flex-1 overflow-y-auto grid grid-cols-2 md:grid-cols-3 2xl:grid-cols-4 gap-4 content-start pb-8 pr-1"></div>
                </div>
            </main>

            {{-- KANAN: KERANJANG --}}
            <aside class="anim-fade-up w-[360px] xl:w-[420px] shrink-0 bg-white border-l border-zinc-200 flex flex-col" style="--i: 2">
                <div class="h-16 shrink-0 px-6 border-b border-zinc-100 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <h2 class="font-bold tracking-tight">Pesanan</h2>
                        <span id="badge-cart-count"
                            class="hidden min-w-6 h-6 px-1.5 rounded-full bg-zinc-900 text-white text-xs font-bold flex items-center justify-center tabular-nums"></span>
                    </div>
                    <button id="btn-reset"
                        class="text-xs font-semibold text-zinc-400 hover:text-red-600 transition-colors">Kosongkan</button>
                </div>

                <div id="cart-items" class="flex-1 overflow-y-auto px-6"></div>

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
                                              has-checked:bg-white has-checked:text-zinc-900 has-checked:shadow-sm">
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
                            <button id="btn-uang-pas"
                                class="w-full text-xs font-semibold text-zinc-600 bg-white border border-zinc-200 hover:border-zinc-900 hover:text-zinc-900 rounded-lg py-2 tabular-nums transition-colors">Uang pas</button>
                            <div class="flex justify-between items-center">
                                <span class="text-zinc-500">Kembalian</span>
                                <span id="lbl-kembalian" class="font-bold tabular-nums">Rp 0</span>
                            </div>
                        </div>

                        {{-- QRIS: QR dummy buat scan --}}
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

                        {{-- Transfer: pilih bank tujuan --}}
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

                    <button id="btn-bayar"
                        class="w-full h-13 bg-zinc-900 hover:bg-zinc-800 active:scale-[0.99] disabled:opacity-40 disabled:pointer-events-none text-white text-sm font-bold rounded-xl py-4 tabular-nums transition">
                        Bayar
                    </button>
                </div>
            </aside>
        </div>
    </div>

    {{-- MODAL STRUK --}}
    <div id="modal-struk" class="hidden anim-backdrop fixed inset-0 bg-zinc-950/50 backdrop-blur-sm flex items-center justify-center z-40 p-4">
        <div class="anim-scale-in bg-white rounded-2xl w-full max-w-sm p-7 max-h-[90dvh] overflow-y-auto shadow-2xl">
            <div id="struk-body"></div>
            <div id="struk-actions" class="flex gap-2.5 mt-7">
                <button id="btn-print-struk"
                    class="flex-1 bg-zinc-900 hover:bg-zinc-800 active:scale-[0.99] text-white font-bold rounded-xl py-3 text-sm transition">Cetak Struk</button>
                <button id="btn-tutup-struk"
                    class="flex-1 border border-zinc-200 hover:border-zinc-900 active:scale-[0.99] text-zinc-700 font-bold rounded-xl py-3 text-sm transition-colors">Transaksi Baru</button>
            </div>
        </div>
    </div>

    <div id="toast" class="hidden"></div>
</body>
</html>
