<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'profile_image',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function managedTeams()
    {
        return $this->hasMany(Team::class, 'admin_id');
    }

    public function teamMemberships()
    {
        return $this->hasMany(TeamMembership::class, 'user_id');
    }

    public function createdProjects()
    {
        return $this->hasMany(Project::class, 'created_by');
    }

    public function assignedTasks()
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    public function createdTasks()
    {
        return $this->hasMany(Task::class, 'created_by');
    }

    public function stepCompletions()
    {
        return $this->hasMany(StepCompletion::class, 'user_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isMember(): bool
    {
        return $this->role === 'member';
    }
    public function teams()
    {
    return $this->belongsToMany(
        Team::class,
        'team_memberships',
        'user_id',
        'team_id'
    )->withPivot([
        'status',
        'points_earned',
        'joined_at',
    ])->withTimestamps();
    }

}
