<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Things readers send in for a chapter: a resource link, a tip, or a mistake report.
     * Nothing is public until an admin approves it.
     */
    public function up(): void
    {
        Schema::create('contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('course', 60);
            $table->string('chapter', 120);
            $table->string('type', 20);                 // resource | tip | correction
            $table->string('title', 150)->nullable();
            $table->string('url', 500)->nullable();
            $table->text('body')->nullable();
            $table->string('status', 20)->default('pending'); // pending | approved | rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('admin_note', 500)->nullable();
            $table->timestamps();

            $table->index(['course', 'chapter', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contributions');
    }
};
