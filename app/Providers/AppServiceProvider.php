<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Authorization\Repositories\AuthorizationRepository;
use App\Domain\Card\Repositories\CardLimitsRepository;
use App\Domain\Card\Repositories\CardRepository;
use App\Domain\Company\Repositories\CompanyBalanceRepository;
use App\Domain\Event\Repositories\EventRepository;
use App\Domain\Transaction\Repositories\TransactionRepository;
use App\Domain\User\Repositories\UserRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\AuthorizationEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\CardEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\CardLimitsEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\CompanyEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EventEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\TransactionEloquentRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\UserEloquentRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerTelescope();
        $this->registerDebugbar();

        $this->app->bind(
            CardRepository::class,
            CardEloquentRepository::class,
        );

        $this->app->bind(
            UserRepository::class,
            UserEloquentRepository::class,
        );

        $this->app->bind(
            CompanyBalanceRepository::class,
            CompanyEloquentRepository::class,
        );

        $this->app->bind(
            CardLimitsRepository::class,
            CardLimitsEloquentRepository::class,
        );
        $this->app->bind(
            TransactionRepository::class,
            TransactionEloquentRepository::class,
        );

        $this->app->bind(
            AuthorizationRepository::class,
            AuthorizationEloquentRepository::class,
        );
        $this->app->bind(
            EventRepository::class,
            EventEloquentRepository::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureCommands();
        $this->configureDatabase();
        $this->configureDates();
        $this->configureVite();
        $this->configureUrl();
    }

    /**
     * Configure the application's commands.
     */
    private function configureCommands(): void
    {
        DB::prohibitDestructiveCommands($this->app->isProduction());
    }

    /**
     * Configure the application's models.
     *
     * Strict mode throws on lazy loading, silently discarded attributes and
     * access to missing attributes. Keep it on: it surfaces N+1 early.
     */
    private function configureDatabase(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
    }

    /**
     * Configure the application's Vite.
     */
    private function configureVite(): void
    {
        Vite::useAggressivePrefetching();
    }

    /**
     * Configure the dates.
     */
    private function configureDates(): void
    {
        Date::use(CarbonImmutable::class);
    }

    /**
     * Configure the application's URL.
     */
    private function configureUrl(): void
    {
        URL::forceHttps($this->app->isProduction());
    }

    private function registerTelescope(): void
    {
        if ($this->app->isLocal() && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    private function registerDebugbar(): void
    {
        if ($this->app->isLocal()
            && $this->app->hasDebugModeEnabled()
            && class_exists(\Fruitcake\LaravelDebugbar\ServiceProvider::class)
            && config('debugbar.enabled')
        ) {
            $this->app->register(\Fruitcake\LaravelDebugbar\ServiceProvider::class);
        }
    }
}
