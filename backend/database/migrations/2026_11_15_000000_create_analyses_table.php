<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analyses', function (Blueprint $t) {
            $t->id();
            $t->string('language', 30);
            $t->text('error_message')->nullable();
            $t->longText('code');
            $t->json('result');
            $t->unsignedInteger('duration_ms')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analyses');
    }
};
