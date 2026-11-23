<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /**
     * Audit du 23/11 : aucun test n'appelle un vrai service par megarde (un appel oublie echoue au lieu de partir),
     * et aucun n'ecrit dans les journaux du poste, ni par le canal par defaut ni par les canaux nommes.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        config(['logging.default' => 'null']);

        foreach (['payments', 'signature', 'analytics'] as $channel) {
            config(["logging.channels.{$channel}" => config('logging.channels.null')]);
        }
    }
}
