<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('quick_trip_configuration_slabs')) {
            Schema::create('quick_trip_configuration_slabs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('quick_trip_configuration_id');
                $table->unsignedInteger('max_trips')->nullable();
                $table->decimal('per_trip_charge', 12, 2);
                $table->timestamps();
            });
        }

        $foreignKeyExists = collect(Schema::getForeignKeys('quick_trip_configuration_slabs'))
            ->contains(fn (array $foreignKey) => $foreignKey['name'] === 'qt_config_slabs_config_fk');
        if (! $foreignKeyExists) {
            Schema::table('quick_trip_configuration_slabs', function (Blueprint $table) {
                $table->foreign('quick_trip_configuration_id', 'qt_config_slabs_config_fk')
                    ->references('id')
                    ->on('quick_trip_configurations')
                    ->cascadeOnDelete();
            });
        }

        $uniqueIndexExists = collect(Schema::getIndexes('quick_trip_configuration_slabs'))
            ->contains(fn (array $index) => $index['name'] === 'qt_config_slabs_limit_uq');
        if (! $uniqueIndexExists) {
            Schema::table('quick_trip_configuration_slabs', function (Blueprint $table) {
                $table->unique(
                    ['quick_trip_configuration_id', 'max_trips'],
                    'qt_config_slabs_limit_uq'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_trip_configuration_slabs');
    }
};
