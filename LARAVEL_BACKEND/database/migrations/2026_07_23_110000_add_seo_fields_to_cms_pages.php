<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_pages', function (Blueprint $table) {
            $table->string('og_image')->nullable()->after('meta_description');
            $table->string('og_title')->nullable()->after('og_image');
            $table->text('og_description')->nullable()->after('og_title');
            $table->string('canonical_url')->nullable()->after('og_description');
            $table->string('robots', 64)->nullable()->after('canonical_url');
        });
    }

    public function down(): void
    {
        Schema::table('cms_pages', function (Blueprint $table) {
            $table->dropColumn([
                'og_image',
                'og_title',
                'og_description',
                'canonical_url',
                'robots',
            ]);
        });
    }
};
