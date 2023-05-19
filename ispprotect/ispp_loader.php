<?php
if(!function_exists('sg_load')) {

	$sg_dir = __DIR__ . '/loader';
	if(!is_dir($sg_dir)) {
		mkdir($sg_dir, 0700);
		chmod($sg_dir, 0700);
	}

	$phpver = phpversion();
	$v = explode('.', $phpver);
	$ver = $v[0] . '.' . (int)$v[1];

	$os = strtolower(substr(php_uname(), 0, 3));
	$thread_safe = (@constant('PHP_ZTS') || @constant('ZEND_THREAD_SAFE'));

	$loader_file = 'ixed.' . $ver . ($thread_safe ? 'ts' : '') . '.' . $os;

	$loader_url = 'https://www.sourceguardian.com/loaders/download.php?php_v=' . urlencode($phpver) . '&php_ts=' . ($thread_safe ? '1' : '0') . '&php_is=' . @constant('PHP_INT_SIZE') . '&os_s=' . urlencode(php_uname('s')) . '&os_r=' . urlencode(php_uname('r')) . '&os_m=' . urlencode(php_uname('m'));

	if(!file_exists($sg_dir . '/' . $loader_file . '.so')) {
		$out = null;
		$ret = null;
		exec('curl --output ' . escapeshellarg($sg_dir . '/' . $loader_file . '.so') . ' ' . escapeshellarg($loader_url) . ' >/dev/null 2>&1', $out, $ret);
		if($ret !== 0) {
			die('ERR');
		} elseif(!file_exists($sg_dir . '/' . $loader_file . '.so')) {
			die('ERR');
		}
	}

	print $sg_dir . '/' . $loader_file . '.so';
	exit;
}