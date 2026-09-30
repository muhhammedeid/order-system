<?php

namespace Tests\Production;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class DatabaseGuard
{
    public const DATABASE = 'wholesale_rel_b1_verify_20260930';

    public static function verify(): void
    {
        if (env('REL_B1_DATABASE_VERIFICATION') !== '1'
            || DB::connection()->getDriverName() !== 'mysql'
            || DB::connection()->getDatabaseName() !== self::DATABASE
            || DB::selectOne('SELECT DATABASE() AS name')->name !== self::DATABASE) {
            throw new RuntimeException('Refusing verification outside the explicit temporary database.');
        }
    }
}
