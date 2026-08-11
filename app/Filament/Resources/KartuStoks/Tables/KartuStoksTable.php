<?php

namespace App\Filament\Resources\KartuStoks\Tables;

use App\Models\KartuStok;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\View\View;

class KartuStoksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query, $livewire) {
                $filters = $livewire->tableFilters ?? [];
                $search = $livewire->tableSearch ?? null;

                $hasActiveFilter = false;

                if (! empty($search)) {
                    $hasActiveFilter = true;
                } else {
                    foreach ($filters as $filterData) {
                        if (is_array($filterData)) {
                            foreach ($filterData as $value) {
                                if ($value !== null && $value !== '' && $value !== []) {
                                    $hasActiveFilter = true;
                                    break 2;
                                }
                            }
                        } elseif ($filterData !== null && $filterData !== '') {
                            $hasActiveFilter = true;
                            break;
                        }
                    }
                }

                if (! $hasActiveFilter) {
                    $query->whereRaw('1 = 0');
                }
            })
            ->emptyStateHeading('Pilih Filter Kartu Stok')
            ->emptyStateDescription('Silakan pilih filter barang, gudang, atau jenis transaksi terlebih dahulu untuk melihat data kartu stok.')
            ->emptyStateIcon(Heroicon::OutlinedFunnel)
            ->columns([
                TextColumn::make('barang.nomer_seri')
                    ->label('Kode Seri')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('barang.nama_barang')
                    ->label('Barang')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('gudang.nama_gudang')
                    ->label('Gudang')
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('nomer_entry')
                    ->label('No. Entry')
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label('Tanggal & Jam')
                    ->dateTime('d M Y H:i')
                    ->timezone('Asia/Jakarta')
                    ->sortable(),

                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->limit(30)
                    ->tooltip(fn (KartuStok $record): string => $record->keterangan ?? ''),

                TextColumn::make('jenis_transaksi')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => strtoupper(str_replace('_', ' ', $state)))
                    ->color(fn (string $state): string => match ($state) {
                        'masuk', 'pindah_masuk' => 'success',
                        'keluar', 'pindah_keluar' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('jumlah')
                    ->label('Jumlah')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('harga')
                    ->label('Harga')
                    ->money('IDR'),

                TextColumn::make('saldo')
                    ->label('Saldo')
                    ->sortable()
                    ->badge()
                    ->color('info'),
            ])
            ->defaultSort('created_at', 'asc')
            ->recordAction('view_detail')
            ->recordActions([
                Action::make('view_detail')
                    ->extraAttributes(['class' => 'hidden', 'style' => 'display:none'])
                    ->modalHeading(fn (KartuStok $record) => "Detail Mutasi Stok #" . ($record->nomer_entry ?? $record->id))
                    ->modalContent(fn (KartuStok $record): View => view(
                        'filament.resources.kartu-stok.detail-modal',
                        ['record' => $record->load('barang', 'gudang')]
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
            ])
            ->filters([
                SelectFilter::make('barang_id')
                    ->label('Barang')
                    ->relationship('barang', 'nama_barang'),

                SelectFilter::make('gudang_id')
                    ->label('Gudang')
                    ->relationship('gudang', 'nama_gudang'),

                SelectFilter::make('jenis_transaksi')
                    ->label('Jenis Transaksi')
                    ->options([
                        'masuk' => 'Masuk',
                        'keluar' => 'Keluar',
                        'pindah_masuk' => 'Pindah Masuk',
                        'pindah_keluar' => 'Pindah Keluar',
                    ]),
            ]);
    }
}
