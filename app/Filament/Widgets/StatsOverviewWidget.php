<?php

namespace App\Filament\Widgets;

use App\Models\Pembelian;
use App\Models\Penjualan;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getColumns(): int|array|null
    {
        return [
            'default' => 1,
            'sm' => 2,
            'lg' => 4,
        ];
    }

    private function formatValue(string $text): HtmlString
    {
        return new HtmlString('<span class="text-base sm:text-lg lg:text-xl font-extrabold tracking-tight whitespace-nowrap">' . e($text) . '</span>');
    }

    protected function getStats(): array
    {
        $today = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        // Penjualan Hari Ini
        $penjualanHariIni = Penjualan::whereDate('tanggal', $today)->get();
        $totalHariIni = $penjualanHariIni->sum('neto');
        $countHariIni = $penjualanHariIni->count();

        // Penjualan Bulan Ini
        $totalBulanIni = Penjualan::whereBetween('tanggal', [$startOfMonth, $endOfMonth])->sum('neto');

        // Pembelian Bulan Ini
        $totalBeliBulanIni = Pembelian::whereBetween('tanggal', [$startOfMonth, $endOfMonth])->sum('neto');

        // Total Jenis Barang dengan Stok <= 5 (Low Stock)
        $lowStockCount = DB::table('barang_gudang')
            ->where('stok', '<=', 5)
            ->count();

        return [
            Stat::make('Penjualan Hari Ini', $this->formatValue('Rp ' . number_format($totalHariIni, 0, ',', '.')))
                ->description("{$countHariIni} transaksi sukses")
                ->descriptionIcon(Heroicon::OutlinedShoppingCart)
                ->color('success'),

            Stat::make('Penjualan Bulan Ini', $this->formatValue('Rp ' . number_format($totalBulanIni, 0, ',', '.')))
                ->description(Carbon::now()->translatedFormat('F Y'))
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('primary'),

            Stat::make('Pembelian Bulan Ini', $this->formatValue('Rp ' . number_format($totalBeliBulanIni, 0, ',', '.')))
                ->description('Total modal barang masuk')
                ->descriptionIcon(Heroicon::OutlinedArrowDownTray)
                ->color('warning'),

            Stat::make('Stok Menipis (<= 5)', $this->formatValue($lowStockCount . ' item'))
                ->description($lowStockCount > 0 ? '🔍 Klik untuk lihat rincian' : 'Semua stok aman')
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color($lowStockCount > 0 ? 'danger' : 'gray')
                ->extraAttributes($lowStockCount > 0 ? [
                    'onclick' => 'window.__showLowStockAlert && window.__showLowStockAlert()',
                    'style' => 'cursor:pointer',
                ] : []),
        ];
    }
}
