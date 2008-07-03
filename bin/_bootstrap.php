<?php

ini_set('display_errors', true);

set_include_path( '.'
				. PATH_SEPARATOR
				. dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'php'
				. PATH_SEPARATOR
				. get_include_path()
				);

/**
 * Lazy-loading for classes at run-time.
 *
 * @param string Class name
 */
function __autoload ($class_name) {
	if (!class_exists($class_name, false)) {
		$class_file_path = str_replace('_', DIRECTORY_SEPARATOR, $class_name) . '.php';
		include($class_file_path);
	}
}

?>