<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherAttendanceEdit extends Model
{
    use HasUuids;

    protected $table = 'teacher_attendance_edits';

    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'attendance_id',
        'edited_by',
        'old_values',
        'new_values',
        'reason',
        'is_demo',
    ];

    protected $hidden = [
        'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(TeacherAttendance::class, 'attendance_id');
    }

    public function editedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}
