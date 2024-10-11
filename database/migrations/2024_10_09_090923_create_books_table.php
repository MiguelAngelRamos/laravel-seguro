<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBooksTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();  // Identificador único del libro
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Relacionar con el usuario
            $table->string('title'); // Título del libro
            $table->text('secret'); // Campo secreto solo visible para el dueño
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('books');
    }
}
