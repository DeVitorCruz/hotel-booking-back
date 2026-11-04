<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    
    public function up(): void
    {
        Schema::table('sellers', function (Blueprint $table)
        {
            $table->string('store_logo')->nullable()->after('description');
            $table->string('store_banner')->nullable()->after('store_logo');
            $table->decimal('commission_rate', 5, 2)->default(0.00)->after('store_banner');
            $table->boolean('is_marketplace')->default(false)->after('commission_rate');
            $table->string('rejection_reason')->nullable()->after('status');
        });
    }

    public function down(): void 
    {
        Schema::table('sellers', function (Blueprint $table)
        {
            $table->dropColumn([
                'store_logo', 
                'store_banner',
                'commission_rate', 
                'is_marketplace', 
                'rejection_reason'
            ]);
        });
    }
};
