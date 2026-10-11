<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherPayslip extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'teacher_payslips';

    protected $fillable = [
        'tenant_id',
        'teacher_id',
        'teacher_salary_id',
        'academic_year_id',
        'month',
        'year',
        'basic_pay',
        'house_rent',
        'medical_allowance',
        'transport_allowance',
        'other_allowances',
        'gross_pay',
        'deductions',
        'total_deductions',
        'net_pay',
        'attendance_summary',
        'attendance_deduction',
        'attendance_addition',
        'payment_date',
        'payment_method',
        'transaction_id',
        'status',
        'remarks',
        'is_demo',
        'created_by',
    ];

    protected $hidden = [
        'created_by',
        'updated_at',
        'is_demo',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'basic_pay' => 'decimal:2',
            'house_rent' => 'decimal:2',
            'medical_allowance' => 'decimal:2',
            'transport_allowance' => 'decimal:2',
            'other_allowances' => 'array',
            'deductions' => 'array',
            'gross_pay' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_pay' => 'decimal:2',
            'attendance_summary' => 'array',
            'attendance_deduction' => 'decimal:2',
            'attendance_addition' => 'decimal:2',
            'payment_date' => 'date',
            'month' => 'integer',
            'year' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function teacherSalary(): BelongsTo
    {
        return $this->belongsTo(TeacherSalary::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
