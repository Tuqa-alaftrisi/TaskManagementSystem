<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_memberships', function (Blueprint $table) {
            $table->id();

            $table->foreignId('team_id')
                ->constrained('teams')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->enum('status', [
                'active',
                'pending',
                'rejected',
            ])->default('active');

            $table->unsignedInteger('points_earned')
                ->default(0);

            $table->timestamp('joined_at')
                ->nullable();

            $table->timestamps();

            // المستخدم لا يمكنه الانضمام للفريق نفسه مرتين
            $table->unique(['team_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_memberships');
    }
};
