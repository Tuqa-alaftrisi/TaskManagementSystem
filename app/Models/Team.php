<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'team_name',
        'join_code',
    ];

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function memberships()
    {
        return $this->hasMany(TeamMembership::class, 'team_id');
    }

    public function members()
    {
        return $this->belongsToMany(
            User::class,
            'team_memberships',
            'team_id',
            'user_id'
        )->withPivot([
            'status',
            'points_earned',
            'joined_at',
        ])->withTimestamps();
    }

    public function projects()
    {
        return $this->hasMany(Project::class, 'team_id');
    }
}
