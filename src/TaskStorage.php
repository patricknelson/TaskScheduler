<?php

/**
 * An example implementation that simply persists data to a file with contents saved as a serialized array.
 *
 * IMPORTANT: Please don't use this in production since this is a demonstration ONLY. It is intended to be framework
 * agnostic and as flexible as possible and has not been tested for security at all.
 */
class TaskStorage implements TaskStorageInterface {

	protected $filePath = "";


	/**
	 * Path to file where data will be stored.
	 *
	 * @param	string	$filePath
	 * @throws	Exception
	 */
	public function __construct($filePath) {
		if (!file_exists($filePath)) {
			// Ensure the directory is writable.
			$directory = dirname($filePath);
			if (!is_writable($directory)) throw new Exception("Cannot create file (directory '$directory' not writable): $filePath");
		} else {
			// Ensure the file itself is writable.
			if (!is_writable($filePath)) throw new Exception("Cannot write to: $filePath");
		}
		$this->filePath = $filePath;
	}


	/**
	 * Returns all currently stored settings.
	 *
	 * @return array
	 */
	protected function getSettings() {
		// Return an empty array if the file doesn't exist yet.
		if (!is_file($this->filePath)) return array();

		// Attempt to decode file data. If there's an array, return an empty array.
		$data = file_get_contents($this->filePath);
		$settings = @unserialize($data);
		if (!is_array($settings)) return array();

		return $settings;
	}

	/**
	 * Saves and overrides all settings.
	 *
	 * @param	array	$settings
	 */
	protected function saveSettings(array $settings) {
		$data = file_put_contents($this->filePath, serialize($settings));
	}


	/**
	 * Save data for the specified setting.
	 *
	 * @param    string $settingName Name of the setting to save data for.
	 * @param    mixed $data
	 * @return    void
	 */
	public function set($settingName, $data) {
		// Get all current settings, add/override specified setting name.
		$settings = $this->getSettings();
		$settings[(string) $settingName] = $data;

		// Save updated settings.
		$this->saveSettings($settings);
	}


	/**
	 * Returns data for the specified setting.
	 *
	 * @param    string $settingName Name of the setting to return data for.
	 * @param    mixed $defaultValue Optional default value to return if not set/valid.
	 * @return    mixed
	 */
	public function get($settingName, $defaultValue = false) {
		// Get all settings and look for specified setting.
		$settings = $this->getSettings();
		if (!isset($settings[$settingName])) return $defaultValue;
		return $settings[$settingName];
	}

}
