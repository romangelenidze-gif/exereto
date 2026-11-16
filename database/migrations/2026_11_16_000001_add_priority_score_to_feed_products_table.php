<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feed_products', function (Blueprint $table) {
            if (!Schema::hasColumn('feed_products', 'priority_score')) {
                $table->unsignedSmallInteger('priority_score')
                    ->default(50)
                    ->after('in_stock')
                    ->index('idx_fp_priority_score');
            }
        });

        // Проставляем стартовые приоритеты уже загруженным фидам (adidas и notos)
        DB::table('feed_products')->where('feed', 'adidas')->update(['priority_score' => 200]);
        DB::table('feed_products')->where('feed', 'notos')->update(['priority_score' => 55]);
    }

    public function down(): void
    {
        Schema::table('feed_products', function (Blueprint $table) {
            if (Schema::hasColumn('feed_products', 'priority_score')) {
                $table->dropIndex('idx_fp_priority_score');
                $table->dropColumn('priority_score');
            }
        });
    }
};