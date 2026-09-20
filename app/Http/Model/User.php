<?php

namespace App\Http\Model;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable, HasRoles, SoftDeletes;

    protected $guard_name = 'admin';

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'employee_no',
        'mobile',
        'department_id',
        'position_name',
        'direct_manager_id',
        'employment_status',
        'hire_date',
        'probation_end_date',
        'regular_date',
        'resign_date',
        'emergency_contact_name',
        'emergency_contact_phone',
        'contract_start_date',
        'contract_end_date',
        'certificate_images',
        'base_salary',
        'salary_plan_version',
        'status',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'hire_date' => 'date:Y-m-d',
        'probation_end_date' => 'date:Y-m-d',
        'regular_date' => 'date:Y-m-d',
        'resign_date' => 'date:Y-m-d',
        'contract_start_date' => 'date:Y-m-d',
        'contract_end_date' => 'date:Y-m-d',
        'certificate_images' => 'array',
        'last_login_at' => 'datetime:Y-m-d H:i:s',
        'base_salary' => 'decimal:2',
        'status' => 'integer',
    ];

    public function department()
    {
        return $this->hasOne(Department::class, 'id', 'department_id');
    }

    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
