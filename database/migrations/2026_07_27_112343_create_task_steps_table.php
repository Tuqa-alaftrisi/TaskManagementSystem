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
        Schema::create('task_steps', function (Blueprint $table) {

            $table->unsignedBigInteger('step_id')->primary();
            
            $table->foreignId('task_id')
                ->constrained('tasks', 'task_id')
                ->cascadeOnDelete();

            $table->text('step_description');

            $table->integer('step_order');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_steps');
    }
};
