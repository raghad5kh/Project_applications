<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class History extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'file_id',
        'type',
        'Upload_date',
        'reservation_date',
        'Edit_date',
        'cancellation of reservation_date'
    ];
    public function user(){
        return $this->belongsTo(User::class);
    }
    public function file(){
        return $this->belongsTo(File::class);
    }
}

