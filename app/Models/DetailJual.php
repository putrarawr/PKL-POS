<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetailJual extends Model
{
    use HasFactory;

    protected $table = 'detail_jual';

    protected $fillable = [
        'penjualan_id',
        'barang_id',
        'gudang_id',
        'satuan',
        'jumlah',
        'harga',
        'hpp',
        'diskon',
        'subtotal',
    ];

    public function penjualan()
    {
        return $this->belongsTo(Penjualan::class, 'penjualan_id');
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }

    public function gudang()
    {
        return $this->belongsTo(Gudang::class, 'gudang_id');
    }
}
