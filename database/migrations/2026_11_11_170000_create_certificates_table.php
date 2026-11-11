<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One certificate per reader per course, issued when they pass the course's final exam.
     * `name` is the name printed on it (copied from the account when issued); the public
     * page /certificate/{code} is what anyone can open to check it is real.
     */
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('course', 60);
            $table->string('code', 14)->unique();
            $table->string('name', 120);
            $table->unsignedSmallInteger('score');
            $table->unsignedSmallInteger('total');
            $table->timestamps();

            $table->unique(['user_id', 'course']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
