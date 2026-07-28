<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class JenisBarang extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'jenis_barang';

    protected $guarded = ['id'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nama_jenis', 'deskripsi'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function barangs()
    {
        return $this->hasMany(
            Barang::class,
            'jenis_barang_id'
        );
    }
}
