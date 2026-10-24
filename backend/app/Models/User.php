<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN        = 'admin';
    public const ROLE_TEACHER      = 'teacher';
    public const ROLE_ACCOUNTANT   = 'accountant';
    public const ROLE_RECEPTIONIST = 'receptionist';

    public const ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_TEACHER,
        self::ROLE_ACCOUNTANT,
        self::ROLE_RECEPTIONIST,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'role',
        'is_active',
        'password',
    ];

    // Naye user ka default role (sab se kam permission)
    // Naye user ka default: sab se kam permission wala role, aur account chalu
    protected $attributes = ['role' => self::ROLE_TEACHER, 'is_active' => true];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    // ---- Role / permission helpers ----

    /** config/roles.php ke mutabiq kya is user ko ye permission hai? */
    public function hasPermission(string $ability): bool
    {
        $permissions = config('roles.permissions.' . $this->role, []);

        return in_array('*', $permissions, true) || in_array($ability, $permissions, true);
    }

    public function getRoleLabelAttribute(): string
    {
        return config('roles.labels.' . $this->role, ucfirst((string) $this->role));
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function teacher()
    {
        return $this->hasOne(Teacher::class);
    }
}
