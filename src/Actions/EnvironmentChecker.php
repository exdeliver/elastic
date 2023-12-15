<?php

namespace Exdeliver\Elastic\Actions;

final class EnvironmentChecker
{
    public static function is(array $environment = ['develop', 'production']): bool
    {
        $environment = app()->environment($environment);

        return $environment;
    }

    public static function isNot(array $environment = ['develop', 'production']): bool
    {
        return !self::is($environment);
    }
}
