<?php
namespace App\Service;

interface IOuterRequest {

	/**
	 * @desc  Request on remote server
	 * @param string $url
	 * @param array  $args if not empty, request method changes to POST
	 * @param string $referer = ''
	 * @param resource (for example curl for http) &$process = null
	 * @param bool $close_connection = true  - you can leave connection open
	 * @param is_xhr = false	if true add heeader 'X-Requested-With: XMLHttpRequest'
	 * @param string $userAgent = '' not necessarily Http!
	 * @return stdClass {string text, int status, string statusText, StdClass json} for example {'some html', 404, 'Not found', null} or {'raw json text', 200, 'ok', {count:20, items:array} }
	 **/
	public function execute($url, $args = array(), $referer = '', &$process = null, $close_connection = true, $is_xhr = false, $userAgent = '');

	/**
	 * @desc  Prepare request, but no send, use if need multi_curl
	 * @see execute params
	 **/
	public function prepare($url, $args = array(), $referer = '', &$process = null, $is_xhr = false, $userAgent = '');
	/**
	 * @desc  multy requests - execute more than one  requests
	 * @param array $resources - resources (it type @see prepare(..., $process, ... ) ) initalize with prepare
	 * @param array $close_connections - true if need close connection
	 * @return array of stdClass (@see execute() return format)
	 **/
	public function multy($resources, $close_connections = false);
	/**
	 * @desc  get use cookie as  assoc array
	 * @return assoc array [host_cookie_name => cookie_value, ...]
	 */
	public function getCookie();
	/**
	 * @desc  raw post request
	 * @param string $url
	 * @param string $data
	 * @param string $referer = ''
	 * @param resource $process (for example curl for http) &$process = null
	 * @param bool $close_connection = true  - you can leave connection open
	 * @param string $userAgent = '' not necessarily Http!
	 * @return stdClass {string text, int status, string statusText, StdClass json} for example {'some html', 404, 'Not found', null} or {'raw json text', 200, 'ok', {count:20, items:array} }
	 **/
	public function sendRawPost($url, $data, $referer = '', &$process = null, $close_connection = true, $userAgent = '');
	/**
	 * @description  Запрос DELETE на сервер c данными в формате JSON
	 * @param string $url
	 * @param string $data
	 * @param string $referer = ''
	 * @param curl &$process = null
	 * @param bool $close_connection = true можно оставить соединение окрытым
	 * @param string $userAgent = '' (default see const USER_AGENT)
	 * @return stdClass {responseText, responseStatus. responseStatusText}
	 **/
	public function sendRawDelete($url, $data, $referer = '', &$process = null, $close_connection = true, $userAgent = '');
	/**
	 * @desc  Prepare request, but no send, use if need multi_curl
	 * @see sendRawPost params
	 **/
	public function prepareRawPost($url, $data, $referer = '', &$process = null, $userAgent = '');
	/**
	 * @desc  Set cached in SESSION data timelife
	 * @param int $cacheTtl seconds
	 **/
	public function setCacheTtl($cacheTtl);
	/**
	 * @desc  Clear cached in SESSION data
	 **/
	public function clearCache();
	/**
	 * @desc  After call next execute() or sendRawPost() will rewrite cache
	 **/
	public function noCacheNextQuery();
	/**
	 * @desc  clearCahce and no cache all next queries
	 **/
	public function cacheOff();
	/**
	 * @desc  setCacheType set cache request result in session (1) or in shared storage (for example memcache)
	 * @param int $type
	 **/
	public function setCacheType($type = 2);
	/**
	 *
	 * @desc По умолчанию из каждого http запроса к апи вырезается stamp.
	 * Полученное значение используется в качестве ключа для хранения результата запроса в кеше приложения.
	 * Это делается для того, чтобы разные пользователи не делали один и тот же запрос к базе,
	 * например магазины категории одни и те же для разных пользователей, поэтому нет смысла делать запрос
	 * для каждого пользователя. Но бывают персональные данные, завязанные на идентификатор пользователя.
	 * Для таких запросов вызывается setSkipDeleteStamp(true);
	 *
	 * @param bool $v
	 */
	public function setSkipDeleteStamp($v);
}
