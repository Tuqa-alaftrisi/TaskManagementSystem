<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_steps', function (Blueprint $table) {

            $table->unsignedBigInteger('step_id')->autoIncrement();

            $table->foreignId('task_id')
                ->constrained('tasks', 'task_id')
                ->cascadeOnDelete();

            $table->text('step_description');

            $table->integer('step_order');

            // عدد النقاط التي يحصل عليها العضو عند إكمال الخطوة
            $table->unsignedInteger('points')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_steps');
    }
};
