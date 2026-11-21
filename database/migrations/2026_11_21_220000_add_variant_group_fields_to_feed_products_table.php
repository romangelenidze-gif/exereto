<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feed_products', function (Blueprint $table) {
            if (!Schema::hasColumn('feed_products', 'variant_label')) {
                $table->string('variant_label', 120)->nullable()->after('colour');
            }
            if (!Schema::hasColumn('feed_products', 'group_min_price')) {
                $table->decimal('group_min_price', 10, 2)->nullable()->after('price');
            }
            if (!Schema::hasColumn('feed_products', 'variants_count')) {
                $table->unsignedSmallInteger('variants_count')->default(1)->after('group_min_price');
            }
            if (!Schema::hasColumn('feed_products', 'has_price_range')) {
                $table->boolean('has_price_range')->default(false)->after('variants_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('feed_products', function (Blueprint $table) {
            foreach (['has_price_range', 'variants_count', 'group_min_price', 'variant_label'] as $col) {
                if (Schema::hasColumn('feed_products', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};