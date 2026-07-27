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
        Schema::create('step_completions', function (Blueprint $table) {

            $table->id();

            $table->foreignId('step_id')
                ->constrained('task_steps','step_id')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->boolean('is_completed')->default(false);

            $table->dateTime('completed_at')->nullable();

            // منع تكرار نفس المستخدم لنفس الخطوة
            $table->unique(['step_id', 'user_id']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('step_completions');
    }
};
