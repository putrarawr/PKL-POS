<?php

namespace App\Filament\Resources\Barangs\Schemas;

use App\Models\JenisBarang;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BarangForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Dasar Barang')
                    ->description('Data utama barang dan harga eceran dasar (Level 1)')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('jenis_barang_id')
                                ->label('Jenis Barang')
                                ->relationship('jenisBarang', 'nama_jenis')
                                ->required()
                                ->searchable()
                                ->preload(),
                            TextInput::make('nama_barang')
                                ->label('Nama Barang')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('satuan')
                                ->label('Satuan Terkecil / Dasar (Level 1)')
                                ->placeholder('Misal: Pcs, Batang, Botol, Saset')
                                ->default('Pcs')
                                ->required(),
                            TextInput::make('harga_beli')
                                ->label('Harga Beli (Level 1)')
                                ->numeric()
                                ->required()
                                ->prefix('Rp')
                                ->default(0),
                            TextInput::make('harga_jual')
                                ->label('Harga Jual Eceran (Level 1)')
                                ->numeric()
                                ->required()
                                ->prefix('Rp')
                                ->default(0),
                        ]),
                    ]),

                Section::make('Tingkatan Satuan (Multi-Level Units)')
                    ->description('Atur konversi satuan bertingkat (Pack, Slof, Dus, Bal, dll). Kosongkan jika hanya 1 level satuan.')
                    ->collapsible()
                    ->schema([
                        // Level 2
                        Grid::make(2)->schema([
                            TextInput::make('satuan_2')
                                ->label('Satuan Level 2')
                                ->placeholder('Misal: Pack, Renceng, Ikat'),
                            TextInput::make('isi_satuan_2')
                                ->label('Isi Konversi Level 2')
                                ->numeric()
                                ->placeholder('Jumlah dalam satuan Level 1 (Misal: 10)'),
                        ]),

                        // Level 3
                        Grid::make(2)->schema([
                            TextInput::make('satuan_3')
                                ->label('Satuan Level 3')
                                ->placeholder('Misal: Slof, Dus, Box'),
                            TextInput::make('isi_satuan_3')
                                ->label('Isi Konversi Level 3')
                                ->numeric()
                                ->placeholder('Jumlah dalam satuan Level 1 (Misal: 100)'),
                        ]),

                        // Level 4
                        Grid::make(2)->schema([
                            TextInput::make('satuan_4')
                                ->label('Satuan Level 4')
                                ->placeholder('Misal: Bal, Karton, Palet'),
                            TextInput::make('isi_satuan_4')
                                ->label('Isi Konversi Level 4')
                                ->numeric()
                                ->placeholder('Jumlah dalam satuan Level 1 (Misal: 1000)'),
                        ]),
                    ]),
            ]);
    }
}
