<?php

// backward compatibility
$classMap = array(
    // PHP 5.3 doesn't like leading backslash
    'PHPUnit_Framework_Exception' => 'PHPUnit\Framework\Exception',
    'PHPUnit_Framework_TestCase' => 'PHPUnit\Framework\TestCase',
    'PHPUnit_Framework_TestSuite' => 'PHPUnit\Framework\TestSuite',
);
foreach ($classMap as $old => $new) {
    if (!class_exists($new)) {
        class_alias($old, $new);
    }
}

require __DIR__.'/../vendor/autoload.php';

/*
\bdk\Debug::getInstance(array(
    'objectsExclude' => array('PHPUnit_Framework_TestSuite', 'PHPUnit\Framework\TestSuite'),
));
*/

/*
require __DIR__.'/../../PHPDebugConsole/src/Debug/Debug.php';
\bdk\Debug::getInstance();

\spl_autoload_register(function ($className) {
    // var_dump('bootstrap autoloader '.$className);
    $className = \ltrim($className, '\\'); // leading backslash _shouldn't_ have been passed
    if (!\strpos($className, '\\')) {
        // className is not namespaced
        return;
    }
    $psr4Map = array(
        'bdk\\' => __DIR__.'/../src/',
    );
    foreach ($psr4Map as $namespace => $dir) {
        if (\strpos($className, $namespace) === 0) {
            $rel = \substr($className, \strlen($namespace));
            $rel = \str_replace('\\', '/', $rel);
            $file = $dir.'/'.$rel.'.php';
            if (file_exists($file)) {
	            require $file;
			}
            return;
        }
    }
});
*/
