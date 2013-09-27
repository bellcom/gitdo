<?php

namespace GitDo;

use Symfony\Component\Yaml\Yaml;

class Config
{
    protected static $parameters;

    /**
     * Get configutation setting.
     *
     * @param  string $key find parameter matching the key
     * @return mixed
     */
    public static function get($key)
    {
        if (empty(self::$parameters)) {
            self::load();
        }

        $data = self::$parameters;
        $keys = explode('.', $key);

        foreach ($keys as $test) {
            $test = trim($test);
            if (isset($data[$test])) {
                $data = $data[$test];
                continue;
            }

            return $default;
        }

        return $data;
    }


    /**
     * Load parameters from file.
     *
     * @return array
     */
    protected static function load()
    {
        self::$parameters = Yaml::parse(__DIR__.'/../../config/parameters.yaml');
    }
}
