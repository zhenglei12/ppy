<?php


namespace App\Http\Model;


use Illuminate\Database\Eloquent\Model;

class Classify extends Model
{
    protected $table = 'classify';

    public $fillable = [
        'id',
        'name',
        'sort',
        "parent_id"
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
