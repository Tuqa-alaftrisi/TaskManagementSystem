<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskStep extends Model
{
    use HasFactory;
       protected $primaryKey = 'step_id';
          protected $keyType = 'int';
             public $incrementing = true;

    protected $fillable = [
        'task_id',
        'step_description',
        'step_order',
    ];

     protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class,'task_id');
    }

    public function completions()
    {
        return $this->hasMany(StepCompletion::class,'step_id');
    }


    public function completedBy()
    {
        return $this->hasManyThrough(
            User::class,
            StepCompletion::class,
            'step_id',    // المفتاح الأجنبي في step_completions
            'id',         // المفتاح المحلي في users
            'step_id',    // المفتاح المحلي في task_steps
            'user_id'     // المفتاح الأجنبي في step_completions الذي يشير إلى us
        );
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('step_order', 'asc');
    }

    public function scopeForTask($query, int $taskId)
    {
        return $query->where('task_id', $taskId);
    }

    public function isCompletedByUser(int $userId): bool
    {
        return $this->completions()
            ->where('user_id', $userId)
            ->where('is_completed', true)
            ->exists();
    }

    public function getCompletedUsers()
    {
        return $this->completions()
            ->where('is_completed', true)
            ->with('user')
            ->get()
            ->pluck('user');
    }

    public function getCompletedCount(): int
    {
        return $this->completions()
            ->where('is_completed', true)
            ->count();
    }

    public function getNextStep()
    {
        return TaskStep::where('task_id', $this->task_id)
            ->where('step_order', '>', $this->step_order)
            ->orderBy('step_order', 'asc')
            ->first();
    }

    public function getPreviousStep()
    {
        return TaskStep::where('task_id', $this->task_id)
            ->where('step_order', '<', $this->step_order)
            ->orderBy('step_order', 'desc')
            ->first();
    }

    public function hasNextStep(): bool
    {
        return $this->getNextStep() !== null;
    }

    public function hasPreviousStep(): bool
    {
        return $this->getPreviousStep() !== null;
    }

}
