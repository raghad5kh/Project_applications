<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SwitchDatabase extends Command
{
    protected $signature = 'switch:database {connection}';

    protected $description = 'Switch the database connection dynamically';

    public function handle()
    {
        $connection = $this->argument('connection');

        // Validate that the specified connection exists in the configuration
        $validConnections = config('database.connections');
        // echo $connection ."\n". $validConnections;
        if (!array_key_exists($connection, $validConnections)) {
            $this->error("Invalid connection: $connection");
            return;
        }
        DB::purge('pgsql');
        // Set the default connection dynamically
        config(['database.default' => env('DB_CONNECTION',  $connection)]);

        // Now, perform your queries, and Laravel will use the dynamically set connection.
        $this->info("Switched to database connection: $connection");
    }
}
