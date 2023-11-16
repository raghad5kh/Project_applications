<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'fk_admin_id',
    ];

    public function admin(){
        return $this->belongsTo(User::class);
    }

    public function group_file(){
        return $this ->hasMany(Group_file::class);
    }

    public function group_member(){
        return $this ->hasMany(Group_member::class);
    }
}
