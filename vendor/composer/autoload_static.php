<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInite6e38bb1f38de737d646ffc5b2f51677
{
    public static $prefixLengthsPsr4 = array (
        'C' => 
        array (
            'Coroowicaksono\\ChartJsIntegration\\Api\\' => 38,
            'Coroowicaksono\\ChartJsIntegration\\' => 34,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Coroowicaksono\\ChartJsIntegration\\Api\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src/api',
        ),
        'Coroowicaksono\\ChartJsIntegration\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInite6e38bb1f38de737d646ffc5b2f51677::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInite6e38bb1f38de737d646ffc5b2f51677::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
