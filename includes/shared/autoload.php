<?php

declare(strict_types=1);

/**
 * Register a PSR-4 style autoloader for plugin classes. This loads the classes.
 *
 * @param string $class Fully-qualified class name.
 */
spl_autoload_register(static function (string $class): void {
	$prefix = 'mvbplugins\\';

	if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
		return;
	}

	$relativeClass = substr($class, strlen($prefix));
	$file = __DIR__ . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';

	if (is_file($file)) {
		require_once $file;
	}
});

