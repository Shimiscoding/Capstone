<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, list<string>> */
    private array $encryptedColumns = [
        'violations' => ['motorist_name', 'license_number', 'location'],
    ];

    public function up(): void
    {
        $this->changeColumnsToText();

        foreach ($this->encryptedColumns as $table => $columns) {
            DB::table($table)->orderBy('id')->chunkById(100, function ($rows) use ($table, $columns): void {
                foreach ($rows as $row) {
                    $updates = [];

                    foreach ($columns as $column) {
                        if ($row->{$column} !== null) {
                            $updates[$column] = Crypt::encryptString((string) $row->{$column});
                        }
                    }

                    if ($updates !== []) {
                        DB::table($table)->where('id', $row->id)->update($updates);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->encryptedColumns as $table => $columns) {
            DB::table($table)->orderBy('id')->chunkById(100, function ($rows) use ($table, $columns): void {
                foreach ($rows as $row) {
                    $updates = [];

                    foreach ($columns as $column) {
                        if ($row->{$column} !== null) {
                            $updates[$column] = Crypt::decryptString((string) $row->{$column});
                        }
                    }

                    if ($updates !== []) {
                        DB::table($table)->where('id', $row->id)->update($updates);
                    }
                }
            });
        }

        Schema::table('violations', function (Blueprint $table): void {
            $table->string('motorist_name')->change();
            $table->string('license_number')->nullable()->change();
        });

    }

    private function changeColumnsToText(): void
    {
        Schema::table('violations', function (Blueprint $table): void {
            $table->text('motorist_name')->change();
            $table->text('license_number')->nullable()->change();
            $table->text('location')->nullable()->change();
        });

    }
};
