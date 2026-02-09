<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('egresos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('culto_id')->constrained('cultos')->onDelete('cascade');
            $table->decimal('monto', 15, 2);
            $table->text('descripcion')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('egresos');
    }
};
