<?php

namespace GamingHubFixtures\WidgetStatus;

use App\Connectors\ConnectorContract;
use App\Models\ConnectorInstance;

/**
 * A minimal Connector that is deliberately NOT anywhere Composer's own
 * autoloader reaches — this namespace/path only exists here, under
 * tests/fixtures. It exists solely to prove PackageLoader's own
 * spl_autoload_register path actually pulls in a class from an installed
 * package directory, as opposed to the Pelican package (App\Connectors\
 * PelicanConnector), whose class happens to already be autoloadable via
 * Platform's own PSR-4 map and so never exercises that code path.
 */
class WidgetStatusConnector implements ConnectorContract
{
    public static function type(): string
    {
        return 'widget-status-fixture';
    }

    public function fetch(ConnectorInstance $instance, array $callConfig): array
    {
        return ['online' => true, 'source' => 'fixture'];
    }
}
