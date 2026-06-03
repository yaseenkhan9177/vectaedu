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
        Schema::table('student_fees', function (Blueprint $table) {
            if (!Schema::hasColumn('student_fees', 'admission_fee')) {
                $table->decimal('admission_fee', 10, 2)->default(0)->after('amount');
            }
            if (!Schema::hasColumn('student_fees', 'exam_fee')) {
                $table->decimal('exam_fee', 10, 2)->default(0)->after('admission_fee');
            }
            if (!Schema::hasColumn('student_fees', 'transport_fee')) {
                $table->decimal('transport_fee', 10, 2)->default(0)->after('exam_fee');
            }
            if (!Schema::hasColumn('student_fees', 'note')) {
                $table->text('note')->nullable()->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_fees', function (Blueprint $table) {
            $cols = ['admission_fee', 'exam_fee', 'transport_fee', 'note'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('student_fees', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
