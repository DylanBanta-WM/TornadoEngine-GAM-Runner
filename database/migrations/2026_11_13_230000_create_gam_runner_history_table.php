<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Who ran which command, and how it went. The output isn't kept.
        Schema::create('gam_runner_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('command');
            $table->integer('exit')->nullable();
            $table->string('error')->nullable();
            $table->unsignedInteger('seconds')->default(0);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gam_runner_history');
    }
};
