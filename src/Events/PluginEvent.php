<?php

namespace Blueprint\Events;

use Blueprint\Contracts\Plugin;

abstract class PluginEvent
{
    public function __construct(
        public readonly Plugin $plugin
    ) {}
} 