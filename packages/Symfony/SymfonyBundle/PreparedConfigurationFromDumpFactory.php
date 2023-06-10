<?php

namespace Ecotone\SymfonyBundle;

use Ecotone\Messaging\Config\PreparedConfiguration;

class PreparedConfigurationFromDumpFactory
{
    public static function get(string $filename): PreparedConfiguration
    {
        if (\opcache_get_configuration() !== false) {
            return require $filename;
        } else {
            return unserialize(file_get_contents($filename.'-serialized'));
        }
    }
}