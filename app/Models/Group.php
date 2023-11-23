<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'admin_id',
    ];

    public function admin(){
        return $this->belongsTo(User::class);
    }

    public function group_file(){
        return $this ->hasMany(Group_file::class);
    }

    public function group_member(){
        return $this->hasMany(Group_member::class, 'group_id', 'id');
    }

    public function files()
    {
        return $this->belongsToMany(File::class, 'group_files') // Specify the correct pivot table name
        ->withPivot('status'); // Include additional pivot columns
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($group) {
            $group->group_member()->delete();
        });
    }


}
