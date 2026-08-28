<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string|null $handle
 * @property string|null $discord_username
 * @property string|null $bio
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'handle', 'discord_username', 'bio', 'email'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements OAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** @return HasMany<Build, $this> */
    public function builds(): HasMany
    {
        return $this->hasMany(Build::class);
    }

    /**
     * The user's linked Grinding Gear Games account, if they have connected
     * one. Absent for everyone else — the feature is opt-in per user.
     *
     * @return HasOne<PoeAccount, $this>
     */
    public function poeAccount(): HasOne
    {
        return $this->hasOne(PoeAccount::class);
    }

    /** @return HasMany<Endorsement, $this> */
    public function endorsements(): HasMany
    {
        return $this->hasMany(Endorsement::class);
    }

    /** @return BelongsToMany<Build, $this> */
    public function bookmarkedBuilds(): BelongsToMany
    {
        return $this->belongsToMany(Build::class, 'build_bookmarks')->withTimestamps();
    }
}
