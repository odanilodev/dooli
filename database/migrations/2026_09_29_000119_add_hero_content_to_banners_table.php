<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->string('path')->nullable()->change();
            $table->string('eyebrow')->nullable()->after('name');
            $table->string('title')->nullable()->after('eyebrow');
            $table->string('title_highlight')->nullable()->after('title');
            $table->text('description')->nullable()->after('title_highlight');
            $table->string('quote')->nullable()->after('description');
            $table->string('cta_label')->nullable()->after('quote');
            $table->string('cta_link')->nullable()->after('cta_label');
            $table->string('secondary_label')->nullable()->after('cta_link');
            $table->string('secondary_link')->nullable()->after('secondary_label');
            $table->string('image')->nullable()->after('path');
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn([
                'eyebrow',
                'title',
                'title_highlight',
                'description',
                'quote',
                'cta_label',
                'cta_link',
                'secondary_label',
                'secondary_link',
                'image',
            ]);

            $table->string('path')->nullable(false)->change();
        });
    }
};
