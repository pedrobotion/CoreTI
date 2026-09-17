<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => env('DB_CONNECTION', 'sqlite'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],

        /*
         | Base legada de auditoria de backup (MySQL 172.17.3.97), usada apenas
         | pelo comando backups:importar-legado. Depois que o histórico for
         | importado e os agentes apontarem para o CoreTI, pode ser removida.
         */
        'auditoria_backups' => [
            'driver' => 'mysql',
            'host' => env('AUDITORIA_BACKUPS_DB_HOST', '172.17.3.97'),
            'port' => env('AUDITORIA_BACKUPS_DB_PORT', '3306'),
            'database' => env('AUDITORIA_BACKUPS_DB_DATABASE', 'auditoria_backups'),
            'username' => env('AUDITORIA_BACKUPS_DB_USERNAME'),
            'password' => env('AUDITORIA_BACKUPS_DB_PASSWORD'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => false,
        ],

        /*
         | Base do COCARI Gestão TI, lida apenas pela importação do módulo de
         | Telefonia/Equipamentos.
         |
         | O gestaoTI continua em produção e é dono desses dados: nada aqui
         | pode escrever nele. A recusa não depende de disciplina de quem
         | programa — App\Services\GestaoTi\GestaoTiReadOnlyConnection
         | registra um interceptador que rejeita qualquer comando que não
         | comece com SELECT nesta conexão.
         |
         | Pode ser removida depois que a importação estiver conferida.
         */
        'gestaoti' => [
            'driver' => 'mysql',
            'host' => env('GESTAOTI_DB_HOST', '127.0.0.1'),
            'port' => env('GESTAOTI_DB_PORT', '3306'),
            'database' => env('GESTAOTI_DB_DATABASE', 'cocari_gestao_ti'),
            'username' => env('GESTAOTI_DB_USERNAME'),
            'password' => env('GESTAOTI_DB_PASSWORD'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => false,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::ATTR_TIMEOUT => env('GESTAOTI_DB_CONNECT_TIMEOUT', 5),
            ]) : [],
        ],

        'intranet_cocari' => [
            'driver' => 'mysql',
            'host' => env('INTRANET_COCARI_DB_HOST', '127.0.0.1'),
            'port' => env('INTRANET_COCARI_DB_PORT', '3306'),
            'database' => env('INTRANET_COCARI_DB_DATABASE', 'intranet_cocari'),
            'username' => env('INTRANET_COCARI_DB_USERNAME', 'root'),
            'password' => env('INTRANET_COCARI_DB_PASSWORD', ''),
            'unix_socket' => env('INTRANET_COCARI_DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'intranet' => [
            'driver' => 'mysql',
            'host' => env('INTRANET_DB_HOST', env('INTRANET_COCARI_DB_HOST', '127.0.0.1')),
            'port' => env('INTRANET_DB_PORT', env('INTRANET_COCARI_DB_PORT', '3306')),
            'database' => env('INTRANET_DB_DATABASE', env('INTRANET_COCARI_DB_DATABASE', 'intranet_cocari')),
            'username' => env('INTRANET_DB_USERNAME', env('INTRANET_COCARI_DB_USERNAME', 'root')),
            'password' => env('INTRANET_DB_PASSWORD', env('INTRANET_COCARI_DB_PASSWORD', '')),
            'unix_socket' => env('INTRANET_DB_SOCKET', env('INTRANET_COCARI_DB_SOCKET', '')),
            'charset' => env('INTRANET_DB_CHARSET', env('DB_CHARSET', 'utf8mb4')),
            'collation' => env('INTRANET_DB_COLLATION', env('DB_COLLATION', 'utf8mb4_unicode_ci')),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::ATTR_TIMEOUT => env('INTRANET_DB_CONNECT_TIMEOUT', 5),
                PDO::MYSQL_ATTR_SSL_CA => env('INTRANET_DB_SSL_CA'),
            ]) : [],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

    ],

];
