<?php
if (!function_exists('ams_env')) {
    function ams_env(string $key, $default = null)
    {
        if ($key === '') {
            return $default;
        }

        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }

        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }

        $value = getenv($key);
        return $value !== false ? $value : $default;
    }
}
?>
