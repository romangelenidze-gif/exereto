<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feed_products', function (Blueprint $table) {
            $table->string('ean', 14)->nullable()->after('model_code')->index('idx_fp_ean');
            $table->boolean('cascade_hidden')->default(false)->after('is_active')->index('idx_fp_cascade_hidden');
        });
    }

    public function down(): void
    {
        Schema::table('feed_products', function (Blueprint $table) {
            $table->dropIndex('idx_fp_ean');
            $table->dropIndex('idx_fp_cascade_hidden');
            $table->dropColumn(['ean', 'cascade_hidden']);
        });
    }
};