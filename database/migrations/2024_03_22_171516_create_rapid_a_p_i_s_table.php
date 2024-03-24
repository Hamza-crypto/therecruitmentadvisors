<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('rapid_a_p_i_s', function (Blueprint $table) {
            $table->integer('count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapid_a_p_i_s');
    }
};