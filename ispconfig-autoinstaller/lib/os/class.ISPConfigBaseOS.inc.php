<?php
/**
 * Description of class
 *
 * @author croydon
 */
class ISPConfigBaseOS {
	private $ispc_config = false;

	private static $os_data = null;

	/**
	 * @return array
	 * @throws ISPConfigOSException
	 */
	public static function getOSVersion() {
		if(self::$os_data === null) {
			if(file_exists('/etc/os-release')) {
				$os = ISPConfigFunctions::ini_read('/etc/os-release');
				if(!isset($os['ID']) && isset($os['NAME'])) {
					$os['ID'] = strtolower(preg_replace('/^(\S*)\s*.*?$/', '$1', $os['NAME']));
				}
				if(!isset($os['ID']) || !$os['ID']) {
					throw new ISPConfigOSException('Detection of OS failed.');
				}
				if($os['ID'] !== 'debian' && $os['ID'] !== 'ubuntu') {
					throw new ISPConfigOSException($os['ID'] . ' is not a supported distribution.');
				}

				if(!isset($os['VERSION_ID']) && isset($os['VERSION'])) {
					$os['VERSION_ID'] = preg_replace('/^([0-9\.]+)(\s.*?)?$/', '$1', $os['VERSION']);
				}
				if(!isset($os['VERSION_ID']) || !$os['VERSION_ID']) {
					throw new ISPConfigOSException('Could not detect version of distribution ' . $os['ID']);
				}
				if($os['ID'] === 'debian') {
					if(!in_array($os['VERSION_ID'], array('9', '10', '11'))) {
						throw new ISPConfigOSException('Version ' . $os['VERSION_ID'] . ' is not supported for ' . $os['ID']);
					}
				} elseif($os['ID'] === 'ubuntu') {
					if(!in_array($os['VERSION_ID'], array('18.04', '20.04', '22.04'))) {
						throw new ISPConfigOSException('Version ' . $os['VERSION_ID'] . ' is not supported for ' . $os['ID']);
					}
				} else {
					// cannot be reached, but just to be on the safe side ...
					throw new ISPConfigOSException($os['ID'] . ' is not a supported distribution.');
				}

				self::$os_data = array(
					'ID' => $os['ID'],
					'VERSION' => $os['VERSION_ID'],
					'NAME' => (isset($os['PRETTY_NAME']) ? $os['PRETTY_NAME'] : ucfirst($os['ID']) . ' ' . (isset($os['VERSION']) ? $os['VERSION'] : $os['VERSION_ID']))
				);
			} else {
				throw new ISPConfigOSException('Unknown or unsupported OS.');
			}
		}

		return self::$os_data;
	}

	/**
	 * @return ISPConfigBaseOS
	 * @throws Exception
	 */
	public static function getOSInstance() {
		try {
			$os = self::getOSVersion();
			$class_name = 'ISPConfig' . ucfirst($os['ID']) . str_replace('.', '', $os['VERSION']) . 'OS';
			if(!is_file(LIB_DIR . '/os/class.' . $class_name . '.inc.php')) {
				$class_name = 'ISPConfig' . ucfirst($os['ID']) . 'OS';
			}

			return new $class_name;
		} catch(Exception $ex) {
			throw $ex;
		}
	}

	public function __construct() {

	}

	public function exec($cmd, $returncodes_ok = array(), $tries = 1, $retry_codes = null) {
		$result = false;
		while($result === false && $tries > 0) {
			$return_var = 0;
			$output = array();
			exec($cmd, $output, $return_var);
			ISPConfigLog::debug('CMD: ' . $cmd . ' returned code ' . $return_var);
			if($return_var === 0 || (!empty($returncodes_ok) && is_array($returncodes_ok) && in_array($return_var, $returncodes_ok, true))) {
				$result = implode("\n", $output);
			} elseif(is_array($retry_codes) && !empty($retry_codes) && !in_array($return_var, $retry_codes)) {
				break;
			}
			$tries--;
		}

		return $result;
	}

	public function passthru($cmd, $returncodes_ok = array(), $tries = 1, $retry_codes = null) {
		$result = false;
		while($result === false && $tries > 0) {
			$return_var = 0;
			passthru($cmd, $return_var);
			ISPConfigLog::debug('CMD: ' . $cmd . ' returned code ' . $return_var);
			if($return_var === 0 || (!empty($returncodes_ok) && is_array($returncodes_ok) && in_array($return_var, $returncodes_ok, true))) {
				$result = true;
			} elseif(is_array($retry_codes) && !empty($retry_codes) && !in_array($return_var, $retry_codes)) {
				break;
			}
			$tries--;
		}

		return $result;
	}

	protected function addLines($file, $entries, $add_if_existing = false) {
		if(!is_array($entries)) {
			$entries = array($entries);
		}

		$content = '';
		if(is_file($file)) {
			$content = file_get_contents($file);
		}

		foreach($entries as $line) {
			if($add_if_existing === true || !preg_match('/^' . preg_quote($line) . '$/m', $content)) {
				$content .= "\n" . $line . "\n";
			}
		}

		file_put_contents($file, $content);
		return;
	}

	protected function uncommentLines($file, $entries, $commenter = '#') {
		return $this->commentUncommentLines($file, $entries, true, $commenter);
	}

	protected function commentLines($file, $entries, $commenter = '#') {
		return $this->commentUncommentLines($file, $entries, false, $commenter);
	}

	private function commentUncommentLines($file, $entries, $uncomment = false, $commenter = '#') {
		if(!is_array($entries)) {
			throw new ISPConfigOSException('Invalid entries array provided.');
		}

		if(!is_file($file)) {
			throw new ISPConfigOSException('File ' . $file . ' does not exist.');
		}

		$content = file_get_contents($file);

		$active_entry = false;
		$lines = explode("\n", $content);
		$new_lines = array();
		for($l = 0; $l < count($lines); $l++) {
			$line = $lines[$l];

			if($active_entry) {
				if(!isset($active_entry['last_line']) || !$active_entry['last_line']) {
					$active_entry = false;
				} elseif(preg_match($active_entry['last_line'], $line)) {
					$active_entry = false;
					if(!isset($active_entry['skip_last_line']) || !$active_entry['skip_last_line']) {
						if($uncomment === true) {
							$line = preg_replace('/^(\s*)' . preg_quote($commenter, '/') . '+[ \t]*/', '$1', $line);
						} else {
							$line = $commenter . $line;
						}
					}
				} elseif(isset($active_entry['search']) && $active_entry['search']) {
					if(!is_array($active_entry['search'])) {
						$active_entry['search'] = array($active_entry['search']);
					}
					for($i = 0; $i < count($active_entry['search']); $i++) {
						if(preg_match($active_entry['search'][$i], $line)) {
							if($uncomment === true) {
								$line = preg_replace('/^(\s*)' . preg_quote($commenter, '/') . '+[ \t]*/', '$1', $line);
							} else {
								$line = $commenter . $line;
							}
							break;
						}
					}
				}
			}

			// not possible using "else" here because last line of active entry might be first of new
			if(!$active_entry) {
				for($i = 0; $i < count($entries); $i++) {
					$entry = $entries[$i];
					if(!isset($entry['first_line'])) {
						throw new ISPConfigOSException('Invalid entry (no last or first line).');
					}
					if(preg_match($entry['first_line'], $line)) {
						if($uncomment === true) {
							$line = preg_replace('/^(\s*)' . preg_quote($commenter, '/') . '+[ \t]*/', '$1', $line);
						} else {
							$line = $commenter . $line;
						}
						$active_entry = $entry;
						break;
					}
				}

				if($active_entry && isset($active_entry['add_lines'])) {
					$add = $active_entry['add_lines'];
					if(!is_array($add)) {
						$add = array($add);
					}
					$line .= "\n" . implode("\n", $add);
				}
			}

			$new_lines[] = $line;
		}

		$content = implode("\n", $new_lines);
		unset($new_lines);
		unset($lines);

		copy($file, $file . '~' . strftime('%Y%m%d%H%M%S', time()));
		file_put_contents($file, $content);
		return true;
	}


	/**
	 * @param string $file
	 * @param array $replacements
	 * @param boolean $add_if_missing
	 * @param string $add_to_section
	 * @return boolean
	 * @throws ISPConfigOSException
	 */
	protected function replaceContents($file, $replacements, $add_if_missing = false, $add_to_section = null) {
		if(!is_array($replacements)) {
			throw new ISPConfigOSException('Invalid replacement array provided.');
		}

		$content = '';
		$matches = array();
		if(is_file($file) == false) {
			file_put_contents($file, '');
		} else {
			$content = file_get_contents($file);
		}

		foreach($replacements as $search => $replace) {
			$if_not = false;
			if(is_array($replace)) {
				$if_not = $replace['ifnot'];
				$replace = $replace['replace'];
			}
			$need_to_add = false;
			if(preg_match('/^\/.*?\/[igmsuUS]*$/m', $search)) {
				if($add_if_missing == true && !preg_match($search, $content)) {
					$need_to_add = true;
				} else {
					if($if_not) {
						if(preg_match($search, $content, $matches)) {
							if(strpos($matches[0], $if_not) !== false) {
								// dont add!
								continue;
							}
						}
					}
					$content = preg_replace($search, $replace, $content);
				}
			} else {
				if($add_if_missing == true && !strpos($content, $search)) {
					$need_to_add = true;
				} else {
					$content = str_replace($search, $replace, $content);
				}
			}

			if($need_to_add === true) {
				if($add_to_section) {
					$section_found = false;

				 	$lines = explode("\n", $content);
					$new_lines = array();

					$in_section = false;
					for($l = 0; $l < count($lines); $l++) {
						$line = $lines[$l];
						if(preg_match('/^\[([^\]]+)?\]/', $line, $matches)) {
							if($matches[1] == $add_to_section) {
								$in_section = true;
								$section_found = true;
							} elseif($in_section === true) {
								$new_lines[] = "\n" . $replace . "\n";
								$in_section = false;
							}
						}
						$new_lines[] = $line;
					}
					$content = implode("\n", $new_lines);
					unset($lines);
					unset($new_lines);

					if($section_found === false) {
						$content .= "\n\n" . '[' . $add_to_section . ']' . "\n" . $replace . "\n";
					}
				} else {
					$content .= "\n" . $replace;
				}
			}

		}
		copy($file, $file . '~' . strftime('%Y%m%d%H%M%S', time()));
		file_put_contents($file, $content);

		return true;
	}

	protected function getInstallCommand($packages) {
		if(is_string($packages)) {
			$packages = array($packages);
		}
		$cmd = $this->getUpdateCommand('install');
		$cmd = str_replace('<PACKAGES>', implode(' ', $packages), $cmd);

		return $cmd;
	}

	protected function updatePackageList() {
		$cmd = $this->getUpdateCommand('prepare');
		return $this->exec($cmd, null, 3, array('100'));
	}

	protected function installPackages($packages) {
		$cmd = $this->getInstallCommand($packages);
		return $this->exec($cmd, null, 3);//, array('100'));
	}

	protected function restartService($service_name) {
		$cmd = $this->getRestartServiceCommand($service_name);
		return $this->exec($cmd);
	}

	protected function startService($service_name) {
		$cmd = $this->getRestartServiceCommand($service_name, 'start');
		return $this->exec($cmd);
	}

	protected function stopService($service_name) {
		$cmd = $this->getRestartServiceCommand($service_name, 'stop');
		return $this->exec($cmd);
	}

	protected function isServiceRunning($service_name) {
		$cmd = $this->getRestartServiceCommand($service_name, 'status');
		$result = $this->exec($cmd);
		if($result === false) {
			return false;
		} else {
			return true;
		}
	}

	protected function isStableSupported() {
		return true;
	}

	protected function getSystemPHPVersion() {
		return '7.3';
	}

	protected function afterPackageInstall($section = '') {
	}

	protected function beforePackageInstall($section = '') {
	}

	public function getPackageVersion($package) {
	}

	public function getPackageAlias($package) {
	}

	public function getUpdateCommand($mode = 'update') {
	}

	public function getUpdatePackageRegex() {
	}

	public function getInstallPackageRegex($mode = '') {
	}

	public function getRestartServiceCommand($service, $command = 'restart') {
	}

	public function runPerfectSetup() {
	}

}
