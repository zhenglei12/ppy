<?php


namespace App\Http\Model;


use Illuminate\Database\Eloquent\Model;

class ManuscriptBank extends Model
{
    protected $table = 'manuscript_bank';

    public $fillable = [
        'subject',
        'word_number',
        'manuscript',
        "classify_id",
        'classify_local_id'
    ];

    protected $casts = [
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'created_at' => 'datetime:Y-m-d H:i:s',
    ];

    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function classify()
    {
        return $this->hasOne(Classify::class, "id", "classify_local_id");
    }
}
