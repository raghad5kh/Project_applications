<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'path',
        'name',
        'status',
    ];

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function group_file(){
        return $this ->hasMany(Group_file::class);
    }
    public function history(){
        return $this ->hasMany(History::class);
    }

}
