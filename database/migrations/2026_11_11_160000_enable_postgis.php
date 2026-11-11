<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // On production the extension is created by a superuser when the server is
        // provisioned; the application role usually cannot create it itself.
        $installed = DB::selectOne("select 1 as present from pg_extension where extname = 'postgis'");

        if (! $installed) {
            DB::statement('CREATE EXTENSION postgis');
        }
    }

    public function down(): void
    {
        // The extension is left in place: other database objects may depend on it.
    }
};
