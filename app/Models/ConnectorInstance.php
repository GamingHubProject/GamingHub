<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One credentialed connection to an external system — "our Palworld
 * server's REST API" or "our Pelican panel." Panel (via ConnectorRegistry
 * and the ConnectorContract implementations) is what actually speaks to
 * these; Core never touches this model or a connector directly.
 */
class ConnectorInstance extends Model
{
    /** @use HasFactory<\Database\Factories\ConnectorInstanceFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'base_url',
        'test_endpoint',
        'poll_interval_seconds',
        'last_polled_at',
        'credentials',
        'status',
        'discovered_servers',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'discovered_servers' => 'array',
            'last_polled_at' => 'datetime',
        ];
    }

    public function isDueForPoll(): bool
    {
        return ! $this->last_polled_at
            || $this->last_polled_at->diffInSeconds(now()) >= $this->poll_interval_seconds;
    }
}
