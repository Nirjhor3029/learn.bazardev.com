<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Commercial metadata per course, admin-editable at runtime. Course *content*
     * (title, chapters, folder) stays in config/course.php exactly as before -
     * this table only adds the money/access layer on top. A course with no row
     * here yet is treated as not-free with no price set (see CoursePricing model),
     * so nothing becomes payable or free by omission.
     */
    public function up(): void
    {
        Schema::create('course_pricing', function (Blueprint $table) {
            $table->string('course_slug', 60)->primary();
            $table->boolean('is_free')->default(false);
            $table->unsignedInteger('price_amount')->default(0); // whole BDT
            $table->string('currency', 3)->default('BDT');
            $table->boolean('subscription_eligible')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_pricing');
    }
};
