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
        'credentials',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
        ];
    }
}
