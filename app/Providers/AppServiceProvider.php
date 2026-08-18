<?php

namespace App\Providers;

use App\Capabilities\CapabilityGateway;
use App\Capabilities\Providers\ConnectorBackedProvider;
use App\Connectors\ConnectorRegistry;
use App\Connectors\CurlHttpRequester;
use App\Connectors\HttpRequestContract;
use App\Connectors\RestConnector;
use App\Manager\CurlHttpClient;
use App\Manager\HttpClientContract;
use App\Manager\PackageLoader;
use App\Models\ConnectorInstance;
use App\Models\ServerGroup;
use App\Models\SiteOption;
use App\Models\User;
use App\Observers\AdminAuditObserver;
use App\Observers\GameObserver;
use App\Observers\ServerGroupObserver;
use App\Observers\ServerObserver;
use GamingHub\Core\Capabilities\CapabilityRegistry;
use GamingHub\Core\Capabilities\CapabilityRouter;
use GamingHub\Core\Capabilities\Providers\ManualProvider;
use GamingHub\Core\Models\Game;
use GamingHub\Core\Models\Provider;
use GamingHub\Core\Models\Server;
use GamingHub\Core\Normalizers\FieldMappingNormalizer;
use GamingHub\Core\Normalizers\NormalizerRegistry;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CapabilityRegistry::class);
        $this->app->singleton(CapabilityRouter::class);
        $this->app->singleton(CapabilityGateway::class);
        $this->app->singleton(ConnectorRegistry::class);
        $this->app->singleton(NormalizerRegistry::class);
        $this->app->bind(HttpClientContract::class, CurlHttpClient::class);
        $this->app->bind(HttpRequestContract::class, CurlHttpRequester::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->applySiteOptions();

        $connectors = $this->app->make(ConnectorRegistry::class);
        $connectors->register(RestConnector::class);

        // Pelican is no longer hardcoded here — it's registered as a
        // Connector package (storage/app/packages/pelican-connector/
        // connector.json) and loaded by PackageLoader instead, the same
        // way any other installed Connector would be. Deliberately NOT
        // called eagerly here: boot() runs on every artisan command,
        // including `migrate` on a brand-new database before
        // installed_packages exists yet — PackageLoader::loadConnectorPackages()
        // queries that table, so calling it unconditionally at boot would
        // break a fresh install. ConnectorRegistry::get() calls it lazily
        // on a cache miss instead, which only ever happens once real
        // capability resolution is underway (well after migrations).

        // 'field-mapping' is Core's generic, always-available normalizer for
        // a REST connector against any game — registered eagerly since it
        // has no owning package to gate on. A package-provided normalizer
        // (e.g. one shipped alongside a specific Connector extension) isn't
        // registered here at all: NormalizerRegistry's onMiss() hook below
        // lazily runs PackageLoader on a cache miss, the same way
        // ConnectorRegistry::get() already does for connector classes —
        // being *registered* isn't the same as being *usable* either way:
        // ConnectorBackedProvider still checks the owning InstalledPackage's
        // enabled status at resolution time (PACKAGE_OWNED_NORMALIZERS),
        // since a lazily-loaded registration is never un-registered if the
        // package gets disabled again within the same process.
        $normalizers = $this->app->make(NormalizerRegistry::class);
        $normalizers->register('field-mapping', new FieldMappingNormalizer);
        $normalizers->onMiss(fn () => $this->app->make(PackageLoader::class)->loadConnectorPackages());

        $capabilityRouter = $this->app->make(CapabilityRouter::class);
        $capabilityRouter->registerProvider(new ManualProvider);
        $capabilityRouter->registerProvider($this->app->make(ConnectorBackedProvider::class));

        // Context Subjects a capability binding can be scoped to.
        Relation::morphMap([
            'game' => Game::class,
            'server' => Server::class,
        ]);

        // Auto-generates the 4 scoped permissions (settings/page/news/player)
        // for every Game, ServerGroup, and Server — see ScopedPermissionGenerator.
        // Rows that already existed before this was wired up are covered by
        // `artisan permissions:sync-scoped`, not by these observers.
        Game::observe(GameObserver::class);
        ServerGroup::observe(ServerGroupObserver::class);
        Server::observe(ServerObserver::class);

        // Admin audit trail — plain-CRUD models only. Role/permission
        // changes go through pivot syncs that bypass Eloquent events
        // entirely, so those are logged separately (see UserResource's
        // and RoleResource's own Edit/Create pages).
        User::observe(AdminAuditObserver::class);
        Server::observe(AdminAuditObserver::class);
        Provider::observe(AdminAuditObserver::class);
        ConnectorInstance::observe(AdminAuditObserver::class);
        ServerGroup::observe(AdminAuditObserver::class);
        SiteOption::observe(AdminAuditObserver::class);

        // Admin is unconditionally omniscient — returning null (not false)
        // for everyone else so normal ability checks still run for them.
        // Only covers Laravel's own Gate/can()/@can/policies; Spatie's
        // hasPermissionTo() doesn't consult Gate at all, so
        // ScopedPermissionChecker has its own matching Admin short-circuit.
        Gate::before(fn ($user) => $user->hasRole('Admin') ? true : null);
    }

    /**
     * Options page settings become the actual runtime config, not just
     * values sitting in the database — layouts already read
     * config('app.name') for <title>, so that alone is enough for
     * site_name. Timezone needs one extra step: Laravel sets PHP's actual
     * default timezone from config('app.timezone') once, very early in
     * bootstrap, before any ServiceProvider runs — updating the config
     * value here changes what config('app.timezone') *returns* from this
     * point on, but not the already-set PHP default, so date_default_timezone_set()
     * is called explicitly too. Wrapped in try/catch the same way
     * PackageLoader's DB reads are: boot() runs on every request/command,
     * including the very first `migrate` on a brand-new database before
     * site_options exists — this must never be why that command fails.
     */
    protected function applySiteOptions(): void
    {
        try {
            $option = SiteOption::current();
        } catch (Throwable) {
            return;
        }

        if ($name = $option->values['site_name'] ?? null) {
            config(['app.name' => $name]);
        }

        if ($timezone = $option->values['timezone'] ?? null) {
            config(['app.timezone' => $timezone]);
            date_default_timezone_set($timezone);
        }
    }
}
