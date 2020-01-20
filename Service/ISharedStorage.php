<?php
namespace App\Service;

interface ISharedStorage {
	/**
	 * @desc store data with key
	 * @param string $key
	 * @param mixed  $data
	 **/
	public function set($key, $data);

	/**
	 * @desc get data by key
	 * @param string $key
	 * @param mixed  $default = null
	 **/
	public function get($key, $default = null);
	/**
	 * @desc remove data by key
	 * @param string $key
	 **/
	public function remove($key);
	/**
	 * @desc delete all data from storage
	 **/
	public function clear();
	/**
	 * @desc Clear cache if stored timestamp different with $timestamp
	 */
	public function clearByTimestamp($timestamp);
	/**
	 * @desc Set custom cache folder
	 */
	public function setCacheFolder($path);
}
