<?php

namespace Blueprint\Events;

use Blueprint\Contracts\Plugin;

class PluginDiscovered extends PluginEvent
{
    public function __construct(
        Plugin $plugin,
        public readonly string $discoveryMethod
    ) {
        parent::__construct($plugin);
    }
} 