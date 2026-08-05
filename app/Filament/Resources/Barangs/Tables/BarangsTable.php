<?php

namespace App\Filament\Resources\Barangs\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Support\HtmlString;

class BarangsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('jenisBarang.nama_jenis')
                    ->label('Jenis Barang')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('nomer_seri')
                    ->label('Nomor Seri')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->badge()
                    ->color('gray'),
                TextColumn::make('nama_barang')
                    ->label('Nama Barang')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('barcode')
                    ->label('Barcode')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('harga_beli')
                    ->label('Harga Beli')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('harga_jual')
                    ->label('Harga Jual')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('total_stok')
                    ->label('Total Stok')
                    ->state(fn($record): int => (int) $record->gudangs()->sum('stok'))
                    ->formatStateUsing(fn(int $state, $record): string => number_format($state, 0, ',', '.') . ' ' . ($record->satuan ?? 'Pcs'))
                    ->badge()
                    ->color(fn(int $state): string => match (true) {
                        $state <= 0 => 'danger',
                        $state < 10 => 'warning',
                        default => 'success',
                    })
                    ->tooltip(function ($record): HtmlString {
                        $allGudangs = \App\Models\Gudang::all();
                        if ($allGudangs->isEmpty()) {
                            return new HtmlString('Belum ada gudang terdaftar');
                        }
                        $record->loadMissing('gudangs');
                        $stokMap = $record->gudangs->pluck('pivot.stok', 'id');
                        $satuan = e($record->satuan ?? 'Pcs');

                        $html = '<div style="display:grid;grid-template-columns:1fr auto;gap:16px;min-width:260px;font-size:12px;line-height:1.6;text-align:left">';
                        foreach ($allGudangs as $gudang) {
                            $stok = (int) ($stokMap[$gudang->id] ?? 0);
                            $namaGudang = e($gudang->nama_gudang);
                            $stokText = number_format($stok, 0, ',', '.') . " {$satuan}";
                            $stokColor = $stok > 0 ? '#38bdf8' : '#71717a';
                            $html .= "<div style=\"color:#fafafa\">• {$namaGudang}</div>";
                            $html .= "<div style=\"font-weight:700;text-align:right;color:{$stokColor}\">{$stokText}</div>";
                        }
                        $html .= '</div>';

                        return new HtmlString($html);
                    }),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('jenis_barang_id')
                    ->label('Jenis Barang')
                    ->relationship('jenisBarang', 'nama_jenis'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
