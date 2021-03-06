<?php

namespace Yarak\Migrations\FileDate;

use Yarak\Helpers\Str;
use Yarak\Config\Config;
use Yarak\Helpers\Filesystem;
use Yarak\Migrations\Migrator;
use Yarak\DB\ConnectionResolver;
use Yarak\Console\Output\Output;
use Yarak\Exceptions\FileNotFound;
use Yarak\Migrations\Repositories\MigrationRepository;

class FileDateMigrator implements Migrator
{
    use Filesystem;

    /**
     * Yarak config.
     *
     * @var Config
     */
    protected $config;

    /**
     * Database connection resolver.
     *
     * @var ConnectionResolver
     */
    protected $resolver;

    /**
     * Repository for logging migration activity.
     *
     * @var MigrationRepository
     */
    protected $repository;

    /**
     * The active database connection.
     *
     * @var \Phalcon\Db\Adapter\Pdo
     */
    protected $connection = null;

    /**
     * Output strategy.
     *
     * @var Output
     */
    protected $output;

    /**
     * Construct.
     *
     * @param ConnectionResolver  $resolver
     * @param MigrationRepository $repository
     * @param Output              $output
     */
    public function __construct(
        ConnectionResolver $resolver,
        MigrationRepository $repository,
        Output $output
    ) {
        $this->resolver = $resolver;
        $this->repository = $repository;
        $this->output = $output;

        $this->config = Config::getInstance();
    }

    /**
     * Run migrations.
     *
     * @return array
     */
    public function run()
    {
        $this->setUp();

        $pendingMigrations = $this->getPendingMigrations();

        return $this->runPending($pendingMigrations);
    }

    /**
     * Get all migration filenames that have not been run.
     *
     * @return array
     */
    protected function getPendingMigrations()
    {
        return array_diff(
            $this->getMigrationFiles(),
            $this->repository->getRanMigrations()
        );
    }

    /**
     * Get array of migration file names from directory listed in config.
     *
     * @return array
     */
    protected function getMigrationFiles()
    {
        $files = scandir($this->config->getMigrationDirectory());

        $files = array_filter($files, function ($file) {
            return strpos($file, '.php') !== false;
        });

        $files = array_map(function ($file) {
            return str_replace('.php', '', $file);
        }, $files);

        return array_values($files);
    }

    /**
     * Run pending migrations.
     *
     * @param array $migrations
     *
     * @return array
     */
    protected function runPending(array $migrations)
    {
        if (count($migrations) === 0) {
            $this->output->writeInfo('No pending migrations to run.');

            return [];
        }

        $batch = $this->repository->getNextBatchNumber();

        $this->connection->begin();

        foreach ($migrations as $migration) {
            $this->runUp($migration, $batch);
        }

        $this->connection->commit();

        return $migrations;
    }

    /**
     * Run the migration.
     *
     * @param string $migration
     * @param int    $batch
     */
    protected function runUp($migration, $batch)
    {
        if ($this->performRun($migration, 'up') === true) {
            $this->output->writeInfo("Migrated {$migration}.");

            $this->repository->insertRecord($migration, $batch);
        }
    }

    /**
     * Perform a migration run operation.
     *
     * @param string $migration
     * @param string $method
     *
     * @return bool
     */
    protected function performRun($migration, $method)
    {
        $migrationClass = $this->resolveMigrationClass($migration);

        try {
            $migrationClass->$method($this->connection);

            return true;
        } catch (\Exception $e) {
            $this->output->writeError($e->getMessage());

            return false;
        }
    }

    /**
     * Resolve the migration class from the file name.
     *
     * @param string $migration
     *
     * @throws FileNotFound
     *
     * @return Yarak\Migrations\Migration
     */
    protected function resolveMigrationClass($migration)
    {
        $migrationFile = $this->config->getMigrationDirectory().$migration.'.php';

        if (!file_exists($migrationFile)) {
            throw FileNotFound::migrationFileNotFound($migration, $migrationFile);
        }

        require_once $migrationFile;

        $class = Str::studly(implode('_', array_slice(explode('_', $migration), 4)));

        return new $class();
    }

    /**
     * Rollback migrations.
     *
     * @param int $steps
     *
     * @return array
     */
    public function rollback($steps = 1)
    {
        $this->setUp();

        $toRollback = $this->repository->getRanMigrations(null, $steps);

        return $this->runRollback($toRollback);
    }

    /**
     * Rollback given migrations.
     *
     * @param array $migrations
     *
     * @return array
     */
    protected function runRollback(array $migrations)
    {
        if (count($migrations) === 0) {
            $this->output->writeInfo('Nothing to rollback.');

            return [];
        }

        $this->connection->begin();

        foreach (array_reverse($migrations) as $migration) {
            $this->runDown($migration);
        }

        $this->connection->commit();

        return $migrations;
    }

    /**
     * Rollback the migration.
     *
     * @param string $migration
     */
    protected function runDown($migration)
    {
        if ($this->performRun($migration, 'down') === true) {
            $this->output->writeInfo("Rolled back {$migration}.");

            $this->repository->deleteRecord($migration);
        }
    }

    /**
     * Reset the database by rolling back all migrations.
     *
     * @return array
     */
    public function reset()
    {
        $this->setUp();

        $toRollback = $this->repository->getRanMigrations();

        return $this->runRollback($toRollback);
    }

    /**
     * Reset the database and run all migrations.
     *
     * @return array
     */
    public function refresh()
    {
        $this->setUp();

        $toRollback = $this->repository->getRanMigrations();

        $this->runRollback($toRollback);

        $pendingMigrations = $this->getPendingMigrations();

        return $this->runPending($pendingMigrations);
    }

    /**
     * Perform setup procedures for migrations.
     */
    protected function setUp()
    {
        if (!$this->connection) {
            $this->setConnection();
        }

        $this->createMigrationsRepository();

        $this->makeDirectoryStructure($this->config->getAllDatabaseDirectories());
    }

    /**
     * Set connection to database on object.
     *
     * @return $this
     */
    public function setConnection()
    {
        $dbConfig = $this->config->get('database')->toArray();

        $this->connection = $this->resolver->getConnection($dbConfig);

        $this->repository->setConnection($this->connection);

        return $this;
    }

    /**
     * Return the connection.
     *
     * @return \Phalcon\Db\Adapter\Pdo
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Create the migrations table if it doesn't exist.
     */
    protected function createMigrationsRepository()
    {
        if (!$this->repository->exists()) {
            $this->repository->create();
        }

        return $this;
    }
}
