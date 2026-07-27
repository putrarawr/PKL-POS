<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Penjualan extends Model
{
    use HasFactory;

    protected $table = 'penjualan';

    protected $fillable = [
        'nomer_nota',
        'karyawan_id',
        'gudang_id',
        'tanggal',
        'total',
        'diskon',
        'neto',
        'jenis_pembayaran',
        'bayar',
        'kembalian',
    ];

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class, 'karyawan_id', 'id_karyawan');
    }

    public function gudang()
    {
        return $this->belongsTo(Gudang::class, 'gudang_id');
    }

    public function details()
    {
        return $this->hasMany(DetailJual::class, 'penjualan_id');
    }
}
