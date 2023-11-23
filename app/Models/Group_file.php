<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group_file extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_id',
        'group_id',
    ];
    public function file(){
        return $this->belongsTo(File::class);
    }
    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id');
    }



}
