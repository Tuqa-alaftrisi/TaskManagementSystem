<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $primaryKey = 'task_id';

    protected $keyType = 'int';

    public $incrementing = true;

    protected $fillable = [
        'project_id',
        'assigned_to',
        'created_by',
        'title',
        'description',
        'deadline',
        'status',
    ];

    protected $casts = [
        'deadline' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'Pending',
    ];

    const STATUS_PENDING = 'Pending';
    const STATUS_IN_PROGRESS = 'In Progress';
    const STATUS_COMPLETED = 'Completed';
    const STATUS_CANCELLED = 'Cancelled';

    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function steps()
    {
        return $this->hasMany(TaskStep::class, 'step_id');
    }

    public function completions()
    {
        return $this->hasManyThrough(
            StepCompletion::class,
            TaskStep::class,
            'task_id',    // المفتاح الأجنبي في task_steps
            'step_id',    // المفتاح الأجنبي في step_completions
            'task_id',    // المفتاح المحلي في tasks
            'step_id'     // المفتاح المحلي في task_steps
        );
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_IN_PROGRESS]);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopeOfStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeAssignedTo($query, int $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeCreatedBy($query, int $userId)
    {
        return $query->where('created_by', $userId);
    }

    //تحديد وقت للمهمة
    // public function scopeDeadlineBefore($query, $date)
    // {
    //     return $query->where('deadline', '<', $date);
    // }

    // public function scopeDeadlineAfter($query, $date)
    // {
    //     return $query->where('deadline', '>', $date);
    // }

    // /**
    //  * نطاق: المهام المتأخرة (انتهى موعدها ولم تكتمل)
    //  */
    // public function scopeOverdue($query)
    // {
    //     return $query->where('deadline', '<', now())
    //         ->whereIn('status', [self::STATUS_PENDING, self::STATUS_IN_PROGRESS]);
    // }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    // /**
    //  * التحقق إذا كانت المهمة متأخرة
    //  */
    // public function isOverdue(): bool
    // {
    //     return $this->deadline < now() && !$this->isCompleted() && !$this->isCancelled();
    // }

    public function changeStatus(string $newStatus): void
    {
        if (!in_array($newStatus, self::getStatuses())) {
            throw new \InvalidArgumentException("Invalid status: {$newStatus}");
        }
        $this->update(['status' => $newStatus]);
    }

    public function markAsCompleted(): void
    {
        $this->changeStatus(self::STATUS_COMPLETED);
    }

    public function markAsCancelled(): void
    {
        $this->changeStatus(self::STATUS_CANCELLED);
    }

    public function markAsInProgress(): void
    {
        $this->changeStatus(self::STATUS_IN_PROGRESS);
    }

    public function hasSteps(): bool
    {
        return $this->steps()->count() > 0;
    }

    public function getProgress(): int
    {
        $totalSteps = $this->steps()->count();
        if ($totalSteps === 0) {
            return $this->isCompleted() ? 100 : 0;
        }

        $completedSteps = $this->steps()
            ->whereHas('completions', function ($query) {
                $query->where('is_completed', true);
            })
            ->count();

        return round(($completedSteps / $totalSteps) * 100);
    }

    public function getStatusLabel(): string
    {
        $labels = [
            self::STATUS_PENDING => 'قيد الانتظار',
            self::STATUS_IN_PROGRESS => 'قيد التنفيذ',
            self::STATUS_COMPLETED => 'مكتملة',
            self::STATUS_CANCELLED => 'ملغاة',
        ];

        return $labels[$this->status] ?? $this->status;
    }

    public function getStatusColor(): string
    {
        $colors = [
            self::STATUS_PENDING => 'warning',    // أصفر
            self::STATUS_IN_PROGRESS => 'info',    // أزرق
            self::STATUS_COMPLETED => 'success',   // أخضر
            self::STATUS_CANCELLED => 'danger',    // أحمر
        ];

        return $colors[$this->status] ?? 'secondary';
    }

    // /**
    //  * الحصول على عدد الأيام المتبقية للانتهاء
    //  */
    // public function getDaysRemaining(): ?int
    // {
    //     if ($this->isCompleted() || $this->isCancelled()) {
    //         return null;
    //     }

    //     $now = now();
    //     $deadline = $this->deadline;

    //     if ($now > $deadline) {
    //         return 0;
    //     }

    //     return $now->diffInDays($deadline);
    // }

    /**
     * التحقق من اقتراب الموعد النهائي (مثلاً أقل من 3 أيام)
     */
    // public function isDeadlineApproaching(int $days = 3): bool
    // {
    //     if ($this->isCompleted() || $this->isCancelled()) {
    //         return false;
    //     }

    //     $remaining = $this->getDaysRemaining();
    //     return $remaining !== null && $remaining <= $days;
    // }
}
