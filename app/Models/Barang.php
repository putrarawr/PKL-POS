<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Barang extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'barang';

    protected $guarded = ['id'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['jenis_barang_id', 'nama_barang', 'harga_jual', 'satuan'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function jenisBarang()
    {
        return $this->belongsTo(JenisBarang::class, 'jenis_barang_id');
    }

    public function gudangs()
    {
        return $this->belongsToMany(Gudang::class, 'barang_gudang')
            ->withPivot('stok')
            ->withTimestamps();
    }
}
