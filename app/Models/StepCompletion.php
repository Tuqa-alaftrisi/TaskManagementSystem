<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StepCompletion extends Model
{
    use HasFactory;

    protected $fillable = [
        'step_id',
        'user_id',
        'is_completed',
        'completed_at',
        'points_earned',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
        'points_earned' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'is_completed' => false,
        'points_earned' => 0,
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function step()
    {
        return $this->belongsTo(TaskStep::class, 'step_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeCompleted($query)
    {
        return $query->where('is_completed', true);
    }

    public function scopeNotCompleted($query)
    {
        return $query->where('is_completed', false);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForStep($query, int $stepId)
    {
        return $query->where('step_id', $stepId);
    }

    /*
    |--------------------------------------------------------------------------
    | Completion Methods
    |--------------------------------------------------------------------------
    */

    public function markAsCompleted(int $points): void
    {
        $this->update([
            'is_completed' => true,
            'completed_at' => now(),
            'points_earned' => $points,
        ]);
    }

    public function markAsNotCompleted(): void
    {
        $this->update([
            'is_completed' => false,
            'completed_at' => null,
            'points_earned' => 0,
        ]);
    }

    public function isCompleted(): bool
    {
        return $this->is_completed;
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    public function getCompletedAtFormatted(): ?string
    {
        return $this->completed_at?->format('Y-m-d H:i:s');
    }

    public function getCompletedAtDiff(): ?string
    {
        return $this->completed_at?->diffForHumans();
    }
}
