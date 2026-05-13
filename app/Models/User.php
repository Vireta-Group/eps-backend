<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'user_type',
        'name_bn',
        'name_en',
        'email',
        'phone',
        'username',
        'password_hash', // SQL-এ কলামের নাম password_hash
        'email_verified_at',
        'phone_verified_at',
        'profile_photo_url',
        'language',
        'timezone',
        'last_login_at',
        'last_login_ip',
        'failed_login_count',
        'locked_until',
        'force_password_change',
        'metadata',
        'user_status',
        'status',
        'is_demo',
    ];

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
            'force_password_change' => 'boolean',
            'is_demo' => 'boolean',
            'metadata' => 'array',
            'password_hash' => 'hashed', // লারাভেল ১২-এ অটো হ্যাস কাস্টিং
        ];
    }

    /**
     * লারাভেলের ডিফল্ট 'password' কলামের বদলে 'password_hash' ব্যবহার করার জন্য।
     */
    public function getAuthPassword()
    {
        return $this->password_hash;
    }
}
