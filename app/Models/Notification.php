<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    use HasFactory;

    /**
     * اسم المفتاح الأساسي
     */
    protected $primaryKey = 'notification_id';

    /**
     * نوع المفتاح الأساسي
     */
    protected $keyType = 'int';

    /**
     * هل المفتاح الأساسي متزايد تلقائياً
     */
    public $incrementing = true;


    protected $fillable = [
        'user_id',
        'title',
        'message',
        'type',
        'is_read',
        'link',
        'data',
        'created_at',
    ];

    /**
     * الحقول المخفية عند التحويل إلى JSON/Array
     */
    protected $hidden = [
        'updated_at',
    ];


    protected $casts = [
        'is_read' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'data' => 'array', // تحويل JSON إلى Array تلقائياً
    ];


    protected $attributes = [
        'is_read' => false,
    ];

    // =============================================
    // ثوابت أنواع الإشعارات
    // =============================================
    const TYPE_JOIN_REQUEST = 'join_request';
    const TYPE_ACCEPTED = 'accepted';
    const TYPE_REJECTED = 'rejected';
    const TYPE_INVITE = 'invite';
    const TYPE_POINTS = 'points';

    /**
     * الحصول على قائمة جميع الأنواع المتاحة
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_JOIN_REQUEST,
            self::TYPE_ACCEPTED,
            self::TYPE_REJECTED,
            self::TYPE_INVITE,
            self::TYPE_POINTS,
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }


    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }


    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }


    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }


    public function scopeRecent($query, int $limit = 10)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }


    public function isRead(): bool
    {
        return $this->is_read;
    }


    public function isUnread(): bool
    {
        return !$this->is_read;
    }


    public function markAsRead(): void
    {
        $this->update([
            'is_read' => true,
        ]);
    }


    public function markAsUnread(): void
    {
        $this->update([
            'is_read' => false,
        ]);
    }


    public static function markAllAsRead(int $userId): void
    {
        self::where('user_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }


    public static function createNotification(
        int $userId,
        string $message,
        string $type,
        ?string $title = null,
        ?string $link = null,
        ?array $data = null,
        ?string $createdAt = null
    ): self {
        return self::create([
            'user_id' => $userId,
            'message' => $message,
            'type' => $type,
            'title' => $title,
            'link' => $link,
            'data' => $data,
            'created_at' => $createdAt ?? now(),
        ]);
    }


    public function getTypeLabel(): string
    {
        $labels = [
            self::TYPE_JOIN_REQUEST => 'طلب انضمام',
            self::TYPE_ACCEPTED => 'تم القبول',
            self::TYPE_REJECTED => 'تم الرفض',
            self::TYPE_INVITE => 'دعوة',
            self::TYPE_POINTS => 'نقاط',
        ];

        return $labels[$this->type] ?? $this->type;
    }


    public function getTypeColor(): string
    {
        $colors = [
            self::TYPE_JOIN_REQUEST => 'warning',    // أصفر
            self::TYPE_ACCEPTED => 'success',        // أخضر
            self::TYPE_REJECTED => 'danger',         // أحمر
            self::TYPE_INVITE => 'info',             // أزرق
            self::TYPE_POINTS => 'primary',          // أزرق غامق
        ];

        return $colors[$this->type] ?? 'secondary';
    }

    /**
     * الحصول على أيقونة نوع الإشعار (للواجهة)
     */
    public function getTypeIcon(): string
    {
        $icons = [
            self::TYPE_JOIN_REQUEST => 'fa-user-plus',
            self::TYPE_ACCEPTED => 'fa-check-circle',
            self::TYPE_REJECTED => 'fa-times-circle',
            self::TYPE_INVITE => 'fa-envelope',
            self::TYPE_POINTS => 'fa-star',
        ];

        return $icons[$this->type] ?? 'fa-bell';
    }
}
