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
    ];

     protected $casts = [
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

     protected $attributes = [
        'is_completed' => false,
    ];

    public function step()
    {
        return $this->belongsTo(TaskStep::class, 'step_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class,'user_id');
    }

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

    public function markAsCompleted(): void
    {
        $this->update([
            'is_completed' => true,
            'completed_at' => now(),
        ]);
    }

    public function markAsNotCompleted(): void
    {
        $this->update([
            'is_completed' => false,
            'completed_at' => null,
        ]);
    }

    public function isCompleted(): bool
    {
        return $this->is_completed;
    }

    public static function completeStep(int $stepId, int $userId): self
    {
        // استخدام firstOrCreate مع unique constraint
        return self::firstOrCreate(
            ['step_id' => $stepId, 'user_id' => $userId],
            ['is_completed' => true, 'completed_at' => now()]
        );
    }

    public function getCompletedAtFormatted(): ?string
    {
        return $this->completed_at?->format('Y-m-d H:i:s');
    }

    public function getCompletedAtDiff(): ?string
    {
        return $this->completed_at?->diffForHumans();
    }

}
