<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->onDelete('cascade');
            $table->enum('source', ['online', 'pos']);
            $table->enum('status', ['received', 'preparing', 'ready', 'completed'])->default('received');
            $table->timestamp('estimated_ready_at');
            $table->timestamps();
            
            $table->index(['location_id', 'status']);
            $table->index(['location_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
