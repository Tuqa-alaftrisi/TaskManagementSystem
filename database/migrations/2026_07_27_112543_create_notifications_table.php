<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->unsignedBigInteger('notification_id')->autoIncrement();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();


            $table->string('title')->nullable();

            $table->text('message');

            $table->enum('type', [
                'join_request',
                'accepted',
                'rejected',
                'invite',
                'task_assigned',
                'task_status_changed',
                'deadline_approaching',
                'points',
                'task_cancelled',
            ]);

            $table->boolean('is_read')->default(false);

            $table->string('link')->nullable(); // رابط للصفحة المرتبطة


            $table->json('data')->nullable(); // بيانات إضافية (JSON)

            $table->dateTime('created_at');

            $table->timestamp('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
