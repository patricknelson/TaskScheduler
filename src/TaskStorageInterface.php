<?php

interface TaskStorageInterface {

	/**
	 * Save data for the specified setting.
	 *
	 * @param	string	$settingName	Name of the setting to save data for.
	 * @param	mixed	$data
	 * @return	void
	 */
	public function set($settingName, $data);


	/**
	 * Returns data for the specified setting.
	 *
	 * @param	string	$settingName	Name of the setting to return data for.
	 * @param	mixed	$defaultValue	Optional default value to return if not set/valid.
	 * @return	mixed
	 */
	public function get($settingName, $defaultValue = false);

}
