<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE users MODIFY wallet_balance DECIMAL(12,2) NOT NULL DEFAULT 0');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN wallet_balance SET DEFAULT 0');
        } elseif ($driver === 'sqlite') {
            // SQLite cannot easily change defaults; runtime factory/register already use 0.
        }

        // Clear leftover demo starting balances
        DB::table('users')->where('wallet_balance', 2500)->update(['wallet_balance' => 0]);
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE users MODIFY wallet_balance DECIMAL(12,2) NOT NULL DEFAULT 2500');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN wallet_balance SET DEFAULT 2500');
        }
    }
};
