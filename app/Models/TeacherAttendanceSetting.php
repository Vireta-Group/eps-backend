<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherAttendanceSetting extends Model
{
    use HasUuids;

    public const LATE_DEDUCTION_NONE = 'none';

    public const LATE_DEDUCTION_FIXED = 'fixed';

    public const LATE_DEDUCTION_HALF_DAY = 'half_day';

    public const LATE_DEDUCTION_MODES = [
        self::LATE_DEDUCTION_NONE,
        self::LATE_DEDUCTION_FIXED,
        self::LATE_DEDUCTION_HALF_DAY,
    ];

    protected $table = 'teacher_attendance_settings';

    protected $fillable = [
        'tenant_id',
        'edit_window_minutes',
        'work_start_time',
        'work_end_time',
        'late_after_time',
        'half_day_min_hours',
        'overtime_after_hours',
        'selfie_required',
        'mock_location_detection',
        'allow_geofence_override',
        'geofence_radius_meters',
        'default_status',
        'late_deduction_mode',
        'late_deduction_fixed_amount',
        'late_to_half_day_threshold',
        'overtime_enabled',
        'overtime_hourly_rate',
        'is_demo',
        'created_by',
    ];

    protected $hidden = [
        'is_demo',
    ];

    protected $attributes = [
        'edit_window_minutes' => 60,
        'selfie_required' => false,
        'mock_location_detection' => true,
        'allow_geofence_override' => true,
        'geofence_radius_meters' => 100,
        'default_status' => 'present',
        'late_deduction_mode' => self::LATE_DEDUCTION_NONE,
        'late_deduction_fixed_amount' => 0,
        'late_to_half_day_threshold' => 3,
        'overtime_enabled' => false,
        'overtime_hourly_rate' => 0,
    ];

    protected function casts(): array
    {
        return [
            'edit_window_minutes' => 'integer',
            'half_day_min_hours' => 'decimal:2',
            'overtime_after_hours' => 'decimal:2',
            'selfie_required' => 'boolean',
            'mock_location_detection' => 'boolean',
            'allow_geofence_override' => 'boolean',
            'geofence_radius_meters' => 'integer',
            'late_deduction_fixed_amount' => 'decimal:2',
            'late_to_half_day_threshold' => 'integer',
            'overtime_enabled' => 'boolean',
            'overtime_hourly_rate' => 'decimal:2',
            'is_demo' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
