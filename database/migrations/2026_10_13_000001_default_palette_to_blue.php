<?php

use App\Models\UserPreference;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // 'sakura' came from the old app. The new app has no such accent, so
        // a new account showed no accent as chosen.
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->string('palette', 30)->default('blue')->change();
        });

        DB::table('user_preferences')->whereNotIn('palette', UserPreference::ACCENTS)->update(['palette' => 'blue']);
    }

    public function down(): void {
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->string('palette', 30)->default('sakura')->change();
        });
    }
};
