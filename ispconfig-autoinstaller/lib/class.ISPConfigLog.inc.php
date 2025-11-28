<?php

require_once LIB_DIR . '/libbashcolor.inc.php';

/**
 * Logging class
 *
 * @author croydon
 */
class ISPConfigLog {
	const PRIO_DEBUG = 0;
	const PRIO_INFO = 1;
	const PRIO_WARN = 2;
	const PRIO_ERROR = 3;
	
	private static $priority = self::PRIO_INFO;
	private static $logfile = 'ispconfig.log';

	const PRIORITY_TEXT = array(
		0 => 'DEBUG',
		1 => 'INFO',
		2 => 'WARN',
		3 => 'ERROR'
	);
	
	public static function setLogPriority($priority) {
		if(array_key_exists($priority, self::PRIORITY_TEXT)) {
			self::$priority = $priority;
		} else {
			throw new ISPConfigLogException('Invalid logging priority: ' . $priority);
		}
	}
	
	public static function setLogFile($filename) {
		if(strpos($filename, '/') !== false || strpos($filename, '..') !== false) {
			throw new ISPConfigLogException('Insecure log filename: ' . $filename);
		}
		
		if(!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $filename)) {
			throw new ISPConfigLogException('Invalid log filename: ' . $filename);
		}
		
		if(!$filename) {
			self::$logfile = 'ispconfig.log';
		} else {
			self::$logfile = $filename;
		}
		return true;
	}

	public static function print($message) {
		$lines = explode("\n", $message);
		
		$colw = trim(exec('tput cols'));
		if(!$colw) {
			$colw = 80;
		}
		
		$line_prefix = '';
		$match = array();
		foreach($lines as $line) {
			if(substr($line, 0, 4) === '{FW}') {
				$rep = substr($line, 4, 1);
				if($rep) {
					print str_repeat($rep, $colw);
				}
				continue;
			}
			while(preg_match('/^(.*?)\t(.*)$/', $line, $match)) {
				$sub = mb_strlen($match[1]) % 4;
				$line = $match[1] . str_repeat(' ', 4 - $sub) . $match[2];
			}
			
			$line_len = mb_strlen($line);
			if(substr($line, 0, 2) !== '->') {
				$line_prefix = '';
			} else {
				$line = substr($line, 2);
			}
			if($line_prefix == '' && preg_match('/^(\s+-[a-z0-9A-Z\-]+\s+|\s+)->(\S.*?)$/', $line, $match)) {
				$line_prefix = str_repeat(' ', mb_strlen($match[1]));
				$line = $match[1] . $match[2];
			} elseif($line_prefix == '' && $line_len > $colw) {
				preg_match('/^(\s*)\S/', $line, $match);
				if(isset($match[1])) {
					$line_prefix = $match[1];
				}
			} else {
				if(mb_strlen($line_prefix) >= $colw) {
					$line_prefix = ' ';
				}
				$line = $line_prefix . $line;
			}
			if(mb_strlen($line_prefix) >= $colw) {
				$line_prefix = ' ';
			}
			$ln = 0;
			while(mb_strlen($line) > $colw) {
				$ln++;
				$wrap_prefix = preg_replace('/^(\s+)\S.*?$/', '$1', $line);
				$wrapped = $wrap_prefix . wordwrap(substr($line, strlen($wrap_prefix)), $colw - strlen($wrap_prefix), "{###}", true);
				$tmp = explode("{###}", $wrapped, 2);
				$tmp = array_shift($tmp);
				print $tmp . "\n";
				$line = $line_prefix . trim(mb_substr($line, mb_strlen($tmp)));
				unset($tmp);
				if(trim($line) === '') {
					break;
				} elseif($ln > 1000) {
					break;
				}
			}
			print $line ."\n";
		}
	}
	
	/**
	 * @param string $message
	 * @param int $priority
	 * @param boolean $output
	 * @throws ISPConfigLogException
	 */
	private static function log($message, $priority = ISPConfigLog::PRIO_INFO, $output = false) {
		if(self::$priority > $priority) {
			return;
		}
		
		if(!@is_dir(LOG_DIR)) {
			if(!@mkdir(LOG_DIR, 0777, true)) {
				throw new ISPConfigLogException('Log path ' . LOG_DIR . ' could not be created.');
			}
		}
		
		if(!isset(self::PRIORITY_TEXT[$priority])) {
			throw new ISPConfigLogException('Invalid logging priority ' . $priority . ' provided.');
		}
		
		$log_file = LOG_DIR . '/' . self::$logfile;
		
		$caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
		array_shift($caller);
		if(count($caller) > 0) {
			$caller = array_shift($caller);
		}
		if(substr($caller['file'], 0, strlen(APP_DIR)) === APP_DIR) {
			$caller['file'] = substr($caller['file'], strlen(APP_DIR));
		}
		
		$fp = @fopen($log_file, 'a');
		if(!$fp) {
			throw new ISPConfigLogException('Could not open logfile ' . $log_file . '.');
		}
		if(!@fwrite($fp, strftime("%d.%m.%Y-%H:%M:%S", time()) . ' - ' . $caller['file'] . ':' . $caller['line'] . ': [' . self::PRIORITY_TEXT[$priority] . '] ' . PXBashColor::getCleanString($message) . "\n")) {
			throw new ISPConfigLogException('Could not write to logfile ' . $log_file . '.');
		}
		fclose($fp);
		
		if($output === true) {
			$pre = '[' . self::PRIORITY_TEXT[$priority] . ']';
			if($priority === self::PRIO_ERROR) {
				$pre = '<red>' . $pre . '</red>';
			} elseif($priority === self::PRIO_WARN) {
				$pre = '<lightred>' . $pre . '</lightred>';
			}
			$add_message = '';
			if($priority >= self::PRIO_WARN) {
				 $add_message .= ' <em>(' . $caller['file'] . ':' . $caller['line'] . ')</em>';
			}
			print PXBashColor::getString($pre . ' ' . $message . $add_message . "\n");
		}
	}
	
	/**
	 * @param string $message
	 * @param boolean $output
	 */
	public static function debug($message, $output = false) {
		self::log($message, ISPConfigLog::PRIO_DEBUG, $output);
	}
	
	/**
	 * @param string $message
	 * @param boolean $output
	 */
	public static function info($message, $output = false) {
		self::log($message, ISPConfigLog::PRIO_INFO, $output);
	}
	
	/**
	 * @param string $message
	 * @param boolean $output
	 */
	public static function warn($message, $output = false) {
		self::log($message, ISPConfigLog::PRIO_WARN, $output);
	}
	
	/**
	 * @param string $message
	 * @param boolean $output
	 */
	public static function error($message, $output = false) {
		self::log($message, ISPConfigLog::PRIO_ERROR, $output);
	}
}
