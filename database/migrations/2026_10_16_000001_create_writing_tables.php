<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $t) {
            $t->id();
            $t->string('title', 160);
            $t->json('content');
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
        });
        Schema::create('writing_settings', function (Blueprint $t) {
            $t->id();
            $t->string('mode')->default('grammar');
            $t->boolean('live_analysis')->default(true);
            $t->boolean('dark_mode')->default(false);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('writing_settings');
        Schema::dropIfExists('documents');
    }
};
