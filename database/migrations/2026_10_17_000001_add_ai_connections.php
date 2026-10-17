<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatgpt_accounts', function (Blueprint $t) {
            $t->id();
            $t->string('client_id')->unique();
            $t->string('subject');
            $t->string('label');
            $t->text('credentials')->nullable();
            $t->string('auth_epoch');
            $t->string('model')->nullable();
            $t->timestamps();
        });
        Schema::table('writing_settings', function (Blueprint $t) {
            $t->string('provider')->nullable();
            $t->unsignedBigInteger('chatgpt_account_id')->nullable();
            $t->string('host_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('writing_settings', fn (Blueprint $t) => $t->dropColumn(['provider', 'chatgpt_account_id', 'host_id']));
        Schema::dropIfExists('chatgpt_accounts');
    }
};
