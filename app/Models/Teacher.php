<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Teacher extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'employee_id',
        'designation',
        'department',
        'employment_type',
        'mpo_status',
        'mpo_index',
        'probation_end_date',
        'confirmation_date',
        'salary_grade',
        'bank_info',
        'tin_number',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
        'is_active',
        'is_demo',
        'created_by',
    ];

    protected $hidden = [
        'created_by',
        'updated_at',
        'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'mpo_status' => 'boolean',
            'probation_end_date' => 'date',
            'confirmation_date' => 'date',
            'bank_info' => 'array',
            'is_active' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function qualifications(): HasMany
    {
        return $this->hasMany(TeacherQualification::class);
    }

    public function trainings(): HasMany
    {
        return $this->hasMany(TeacherTraining::class);
    }

    public function experiences(): HasMany
    {
        return $this->hasMany(TeacherExperience::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(TeacherDocument::class);
    }

    public function highestQualification(): HasMany
    {
        return $this->qualifications()->where('is_highest', true);
    }

    public function subjectAssignments(): HasMany
    {
        return $this->hasMany(SubjectTeacherAssignment::class);
    }

    public function classTeacherAssignments(): HasMany
    {
        return $this->hasMany(ClassTeacherAssignment::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(TeacherAttendance::class);
    }

    public function attendanceRegularizations(): HasMany
    {
        return $this->hasMany(TeacherAttendanceRegularization::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(TeacherLeaveBalance::class);
    }

    public function leaveApplications(): HasMany
    {
        return $this->hasMany(TeacherLeaveApplication::class);
    }

    public function salaries(): HasMany
    {
        return $this->hasMany(TeacherSalary::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(TeacherPayslip::class);
    }
}
