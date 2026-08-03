<?php

namespace Jeylabs\AuditLog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Jeylabs\AuditLog\Contracts\AuditLogModel;
use Jeylabs\AuditLog\Contracts\AuditLogStore;
use Jeylabs\AuditLog\Models\AuditLog;
use Jeylabs\AuditLog\Exceptions\InvalidConfiguration;
use Jeylabs\AuditLog\AuditLogController;

class AuditLogServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     */
    public function boot()
    {
        $this->publishMigrationsAndConfig();
        $this->installRoutes();
    }

    /**
     * Register the service provider.
     */
    public function register()
    {
        $this->app->singleton(AuditLogStoreManager::class, function ($app) {
            return new AuditLogStoreManager($app);
        });

        $this->app->bind(AuditLogStore::class, function ($app) {
            return $app->make(AuditLogStoreManager::class)->driver();
        });

        $this->app->bind('command.auditlog:clean', CleanAuditLogCommand::class);

        $this->commands([
            'command.auditlog:clean',
        ]);
    }

    /**
     * Resolve the Eloquent-based model class used by the CausesAudit/
     * LogsAudit traits' `activity()`/`auditLog()` relations. Only
     * meaningful for the eloquent and mongodb drivers, since dynamodb
     * has no joins to build a relation from.
     */
    public static function determineAuditLogModel(): string
    {
        $driver = config('laravel-audit-log.driver', 'eloquent');

        if ($driver === 'dynamodb') {
            throw InvalidConfiguration::modelRelationsUnsupportedForDriver($driver);
        }

        $activityModel = config("laravel-audit-log.stores.{$driver}.model")
            ?? config('laravel-audit-log.audit_log_model')
            ?? AuditLog::class;

        if (!is_a($activityModel, AuditLogModel::class, true)) {
            throw InvalidConfiguration::modelIsNotValid($activityModel);
        }

        return $activityModel;
    }

    public static function getAuditLogModelInstance(): Model
    {
        $activityModelClassName = self::determineAuditLogModel();

        return new $activityModelClassName();
    }

    protected function publishMigrationsAndConfig()
    {
        $this->publishes([
            __DIR__ . '/../config/laravel-audit-log.php' => config_path('laravel-audit-log.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__ . '/../config/laravel-audit-log.php', 'laravel-audit-log');

        $this->loadViewsFrom(__DIR__ . '/../views', 'laravel-audit-log');

        if (!$this->migrationAlreadyPublished()) {
            $timestamp = date('Y_m_d_His', time());

            $this->publishes([
                __DIR__ . '/../migrations/create_audit_log_table.php' => database_path("/migrations/{$timestamp}_create_audit_log_table.php"),
            ], 'migrations');
        }
    }

    protected function migrationAlreadyPublished(): bool
    {
        return count(glob(database_path('migrations/*_create_audit_log_table.php'))) > 0;
    }

    protected function installRoutes()
    {
        $config = [];
        $config['prefix'] = $this->app['config']->get('laravel-audit-log.route_prefix', []);
        Route::group($config, function () {
            Route::post('/push-location', [AuditLogController::class, 'pushLocation'])->name('audit.log.push.location');
        });
    }
}
