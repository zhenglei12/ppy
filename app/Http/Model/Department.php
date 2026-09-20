<?php

namespace App\Http\Model;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{

    protected $table = 'department';

    public $fillable = [
        'id',
        'name',
        "parent_id",
        "alias",
        "level"
    ];

    protected $casts = [
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'created_at' => 'datetime:Y-m-d H:i:s',
    ];

    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function child()
    {
        return  $this->hasMany(self::class, 'parent_id', 'id');
    }

    public function children()
    {
        return $this->child()->with('children');
    }
}
