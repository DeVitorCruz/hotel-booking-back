 <?php
  use Illuminate\Database\Migrations\Migration;
  use Illuminate\Database\Schema\Blueprint;
  use Illuminate\Support\Facades\Schema;

  return new class extends Migration
  {
     public function up(): void
     {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')
                  ->nullable()
                  ->constrained('categories')
                  ->onDelete('cascade');
            $table->unsignedBigInteger('suggested_by');
            $table->unsignedBigInteger('approved_by')
                  ->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])
                  ->default('pending');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
      }

      public function down(): void 
      {
            Schema::dropIfExists('categories');
      }
};
