<?php

/**
 * Main controller class
 *
 * @author croydon
 */
class ISPConfigFunctions {
		
	/**
	 * @param string $value
	 * @return string
	 */
	public static function fromCamelCase($value) {
		$result = '';
		$prev_type = 'char';
		for($s = 0; $s < strlen($value); $s++) {
			$char = $value[$s];
			if($s === 0) {
				$result .= strtolower($char);
				continue;
			}
			$ord = ord($char);
			
			if(($ord >= 65 && $ord <= 90) || ($ord >= 48 && $ord <= 57 && $prev_type == 'char')) {
				$result .= '_';
			}
			$result .= strtolower($char);

			if($ord >= 48 && $ord <= 57) {
				$prev_type = 'number';
			} else {
				$prev_type = 'char';
			}
		}
		
		return $result;
	}
	
	/**
	 * @param string $value
	 * @return string
	 */
	public static function toCamelCase($value) {
		$result = '';
		$ucase = true;
		for($s = 0; $s < strlen($value); $s++) {
			$char = $value[$s];
			if($char === '_') {
				$ucase = true;
				continue;
			} elseif($ucase === true) {
				$result .= strtoupper($char);
			} else {
				$result .= strtolower($char);
			}
			$ucase = false;
		}
		return $result;
	}
	

	/**
	 * check if a utf8 string is valid
	 *
	 * @access public
	 * @param string $str the string to check
	 * @return bool true if it is valid utf8, false otherwise
	 */
	public static function check_utf8($str) {
		$len = strlen($str);
		for($i = 0; $i < $len; $i++) {
			$c = ord($str[$i]);
			if($c > 128) {
				if(($c > 247)) {
					return false;
				} elseif($c > 239) {
					$bytes = 4;
				} elseif($c > 223) {
					$bytes = 3;
				} elseif($c > 191) {
					$bytes = 2;
				} else {
					return false;
				}
				if(($i + $bytes) > $len) {
					return false;
				}
				while($bytes > 1) {
					$i++;
					$b = ord($str[$i]);
					if($b < 128 || $b > 191) {
						return false;
					}
					$bytes--;
				}
			}
		}
		return true;
	}
	
	/**
	 * Gzipped equivalent to file_get_contents
	 * 
	 * @param string $file_name
	 * @return boolean|string
	 */
	public static function gz_file_get_contents($file_name) {
		$fp = @gzopen($file_name, 'r');
		if(!$fp) {
			return false;
		}
		$data = '';
		while(!gzeof($fp) && ($line = gzgets($fp)) !== false) {
			$data .= $line;
		}
		gzclose($fp);
		
		return $data;
	}
	
	/**
	 * Gzipped equivalent to file_put_contents
	 * 
	 * @param string $file_name
	 * @param string $data
	 * @return boolean
	 */
	public static function gz_file_put_contents($file_name, $data) {
		$fp = @gzopen($file_name, 'w');
		if(!$fp) {
			return false;
		}
		if(!gzwrite($fp, $data)) {
			return false;
		}
		gzclose($fp);
		
		return true;
	}
	
	/**
	 * create a password (random string)
	 *
	 * Creates a random string of the chars a-z, A-Z, 1-9 in a given length
	 *
	 * @access public
	 * @param int     $length amount of chars to create
	 * @return string password/random string
	 */
	public static function generatePassword($length = 8, $use_special = false) {
		// Verfügbare Zeichen für Passwort
		$available = "abcdefghjkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ123456789";
		if($use_special == true) {
			$available .= '%_/-_&+.<>';
		}

		if($length < 1) {
			$length = 8;
		}
		$passwd = "";

		$force_special = ($use_special == true ? mt_rand(1, $length) : 0);

		for($i=1;$i<=$length;$i++) {
			// Passwort mit zufälligen Zeichen füllen bis Länge erreicht
			if($i == $force_special) {
				$passwd .= substr($available, mt_rand(57, strlen($available) - 1), 1);
			} else {
				$passwd .= substr($available, mt_rand(0, strlen($available) - 1), 1);
			}
		}

		// kreiertes Passwort zurückgeben
		return $passwd;
	}

	public static function ini_read($file, $initial_sect = '', $has_variables = false) {
		if(!is_file($file)) {
			return false;
		}
		$fp = @fopen($file, 'r');
		
		$ini = array();
		$match = array();
		$sect = $initial_sect;
		$sect_data = array();
		if(!$fp) {
			return false;
		}
		while(!feof($fp)) {
			$line = trim(fgets($fp));
			if($line == '') {
				continue;
			} elseif(substr($line, 0, 1) === '#' || substr($line, 0, 1) === ';') {
				 // comments
				continue;
			} elseif(substr($line, 0, 2) === '//') {
				// comments
				continue;
			} elseif(preg_match('/^\s*\[(\w+)\]\s*$/', $line, $match)) {
				if($sect != '') {
					$ini[$sect] = $sect_data;
				}

				$sect = $match[1];
				$sect_data = array();
				continue;
			}

			if(strpos($line, '=') === false) {
				continue;
			} // invalid setting
			list($key, $value) = explode('=', $line);
			$key = trim($key);
			$value = trim($value);
			if($has_variables == true) {
				if(substr($key, 0, 1) === '$') {
					$key = substr($key, 1);
				}
				if(preg_match('/\w+\s*\[\s*(["\'])([^\]]*?)\\1\s*\]/', $key, $match)) {
					$key = $match[2];
				}
				if(substr($key, 0, 1) === "'" || substr($key, 0, 1) === '"') {
					$key = substr($key, 1, -1);
				}
				if(!$key) {
					continue;
				} // error or unsupported type
				
				$pos = strpos($value, '//');
				if($pos !== false) {
					$value = trim(substr($value, 0, $pos));
				}
				if(substr($value, -1) === ';') {
					$value = substr($value, 0, -1);
				}
				if(substr($value, 0, 1) === "'" || substr($value, 0, 1) === '"') {
					$value = substr($value, 1, -1);
				} elseif(!preg_match('/^[0-9](?:\.[0-9]*)$/', $value)) {
					 // not string and not numeric -> unsupported
					continue;
				}
			} elseif(preg_match('/^".*"$/', $value)) {
				$value = preg_replace('/^"(.*)"$/', '$1', $value);
			}
			$sect_data[$key] = $value;
		}
		fclose($fp);
		
		if($sect != '') {
			$ini[$sect] = $sect_data;
		} elseif($initial_sect == '') {
			return $sect_data;
		}

		return $ini;
	}
	
}