<?php


namespace App\Http\Model;


class Role extends \Spatie\Permission\Models\Role
{
    public $fillable = [
        "name",
        "guard_name",
        "alias",
        "sort",
        "data_scope",
        "description",
        "status"
    ];

    protected $casts = [
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'status' => 'integer',
    ];

    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
