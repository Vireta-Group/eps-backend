<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentNotificationPref extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'parent_notification_prefs';

    protected $fillable = [
        'user_id',
        'student_id',
        'event_type',
        'sms',
        'email',
        'push',
        'status',
        'is_demo',
        'created_by',
    ];

    protected $hidden = [
        'created_by',
        'created_at',
        'updated_at',
        'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'sms' => 'boolean',
            'email' => 'boolean',
            'push' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }

    public const EVENT_ATTENDANCE = 'attendance_alert';

    public const EVENT_FEE_REMINDER = 'fee_reminder';

    public const EVENT_RESULT = 'result';

    public const EVENT_NOTICE = 'notice';

    public const EVENT_EMERGENCY = 'emergency';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
