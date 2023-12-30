<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SwitchDatabase extends Command
{
    protected $signature = 'switch:database {connection} {db_name} {port} {username} {password=""}';

    protected $description = 'Switch the database connection dynamically';

    public function handle()
    {
        $connection=$this->argument('connection');
        $db_name=$this->argument('db_name');
        $port=$this->argument('port');
        $username=$this->argument('username');
        $password=$this->argument('password');

        // Validate that the specified connection exists in the configuration
        $validConnections = config('database.connections');

        if (!array_key_exists($connection, $validConnections)) {
            $this->error("Invalid connection: $connection");
            return;
        }
        // $connectionName = Crypt::encryptString(str_random(80));
        // $connectionName = config('database.default');
        // $driver = config("database.connections.{$connectionName}.driver");

        DB::purge("pgsql");

        config(['database.connections'=>  [
            'driver' => $connection,
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => $port,
            'database' => $db_name,
            'username' => env('DB_USERNAME', $username),
            'password' => env('DB_PASSWORD',$password),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ]]);
        
        // Set the default connection dynamically
        // config(['database.default' => env('DB_CONNECTION',  $connection)]);

        // Now, perform your queries, and Laravel will use the dynamically set connection.
        $this->info("Switched to database connection: $connection");
    }
}