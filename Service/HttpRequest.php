<?php
namespace App\Service;

use App\Entity\PhdPayTransaction;
use Psr\Log\LoggerInterface;
use \Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\HttpFoundation\Response;
use App\Service\IOuterRequest;

/*Кэширование результатов пока недоступно, надо будет создать ISharedStorage, некоторые его методы вызываются в коде, но только в том случае, если
*  $_sharedCache определён*/
//use App\Service\ISharedStorage;

/**
 * Обёртка вокруг curl. Кэширование результатов пока недоступно, надо будет создать ISharedStorage, некоторые его методы вызываются в коде, но только в том случае, если
 *  $_sharedCache определён
*/

class HttpRequest implements IOuterRequest {
	/** @const user agent*/
	const USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64; rv:38.0) Gecko/20100101 Firefox/38.0';

	/** @const session key */
	const SESSION_KEY = 'HttpRequestSession';

	/** @const int cache type session */
	const CACHE_TYPE_SESSION = 1;

	/** @const int cache type storage (memcached) */
	const CACHE_TYPE_STORAGE = 2;

	/** @property true если надо учитывать GET параметр stamp в запросе. По умолчанию из каждого http запроса к апи вырезается stamp. Полученное значение используется для хранения результата запроса в кеше приложения. Это делается для того, чтобы разные пользователи нре делали один и тот же запрос к базе, например магазины категории одни и те же для разных пользователей, поэтому нет смысла делать запрос для каждого пользователя. Но бывают персональные жданные, завязанные на идентиыикатор пользователя.
	 *  Для таких запросов вызывается setSkipDeleteStamp(true);
	 */
	private $_skipDeleteStamp = false;

	/** @property string _cookie_file path to curl cookie file*/
	private $_cookie_file;

	/** @property int _cacheTtl cache timelife*/
	private $_cacheTtl;

	/** @property bool _skipCache if set true one query ignore cache data */
	private $_skipCache = false;

	/** @property bool _cacheOff if set true  queries ignore cache data */
	private $_cacheOff	 = false;

	/** @property int _cacheType if _TYPE_SESSION stored data in session, if _TYPE_STORAGE stored data in memcached */
	private $_cacheType	 = self::CACHE_TYPE_STORAGE;

	/** @property ISharedCache cache object ISharedStorage support   */
	private $_sharedCache;

	/** @property _useCheckpoints есои true то в логе указывается время выполнения запросов к api */
	private $_useCheckpoints = false;


	/** @property array Array of HTTP response statuses */
	private $_codes = array(0=>'Domain Not Found',
		100=>'Continue',
		101=>'Switching Protocols',
		200=>'OK',
		201=>'Created',
		202=>'Accepted',
		203=>'Non-Authoritative Information',
		204=>'No Content',
		205=>'Reset Content',
		206=>'Partial Content',
		300=>'Multiple Choices',
		301=>'Moved Permanently',
		302=>'Found',
		303=>'See Other',
		304=>'Not Modified',
		305=>'Use Proxy',
		307=>'Temporary Redirect',
		400=>'Bad Request',
		401=>'Unauthorized',
		402=>'Payment Required',
		403=>'Forbidden',
		404=>'Not Found',
		405=>'Method Not Allowed',
		406=>'Not Acceptable',
		407=>'Proxy Authentication Required',
		408=>'Request Timeout',
		409=>'Conflict',
		410=>'Gone',
		411=>'Length Required',
		412=>'Precondition Failed',
		413=>'Request Entity Too Large',
		414=>'Request-URI Too Long',
		415=>'Unsupported Media Type',
		416=>'Requested Range Not Satisfiable',
		417=>'Expectation Failed',
		429=>'Too Many Requests',
		500=>'Internal Server Error',
		501=>'Not Implemented',
		502=>'Bad Gateway',
		503=>'Service Unavailable',
		504=>'Gateway Timeout',
		505=>'HTTP Version Not Supported');

	/** @property bool true if need encode url params */
	public $urlEncoded = false;

	public function __construct(ContainerInterface $container, $clear_cookie = true)
	{
		$this->_oContainer = $container;
		$this->oTranslator = $container->get('translator');
		//$this->_cookie_file = storage_path('framework/cache/or_cookie');
		$this->_cookie_file = $file =  $container->getParameter('kernel.cache_dir') . '/app_http_request_cookie';
		if ($clear_cookie) {
			file_put_contents($this->_cookie_file, '');
		}
		$this->_cacheTtl = $container->getParameter('app.http_request_cache_ttl', 0);
		$this->_sharedCache = null; //TODOClass::getISharedStorage();
	}

	public function execute($url, $args = array(), $referer = '', &$process = null, $close_connection = true, $is_xhr = false, $userAgent = '')
	{
		$obj = $this->_searchInCache($url, $args);
		$this->_skipCache = false;
		if ($obj) {
			return $obj;
		}
		$this->prepare($url, $args, $referer, $process, $is_xhr, $userAgent);
		$response = curl_exec($process);
		$httpCode = curl_getinfo($process, CURLINFO_HTTP_CODE);
		if ($close_connection) {
			curl_close($process);
		}
		$obj = new \StdClass();
		$obj->text = $response;
		$obj->status = $httpCode;
		$obj->statusText = isset($this->_codes[$httpCode]) ? $this->_codes[$httpCode] : '';
		$obj->json = json_decode($obj->text);

		$this->_cache($url, $args, $obj);
		$this->_log($obj, $url);
		return $obj;
	}
	public function prepare($url, $args = array(), $referer = '', &$process = null, $is_xhr = false, $userAgent = '') {
		if (!$process) {
			$process = curl_init($url);
		} else {
			curl_setopt($process, CURLOPT_URL, $url);
		}
		curl_setopt($process, CURLOPT_HEADER, 0);
		if(count($args) > 0) {
			curl_setopt($process, CURLOPT_POST, 1);
			curl_setopt($process, CURLOPT_POSTFIELDS, http_build_query($args));
		}
		$headers = [
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3'
			//'Accept-Encoding: gzip, deflate',
			//'Content-Type: application/x-www-form-urlencoded'
		];

		if ($this->urlEncoded) {
			$headers[] = 'Content-Type: application/x-www-form-urlencoded';
		}
		if ($is_xhr) {
			$headers[] = 'X-Requested-With: XMLHttpRequest';
		}
		curl_setopt($process, CURLOPT_HTTPHEADER, $headers);

		if ($referer) {
			curl_setopt($process, CURLOPT_REFERER, $referer);
		}
		curl_setopt($process, CURLOPT_RETURNTRANSFER, 1);
		if (strpos($url, 'https') === 0) {
			curl_setopt($process, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($process, CURLOPT_SSL_VERIFYPEER, false);
		}
		curl_setopt($process, CURLOPT_COOKIEFILE, $this->_cookie_file);
		curl_setopt($process, CURLOPT_COOKIEJAR, $this->_cookie_file);
		if (!$userAgent) {
			$userAgent = self::USER_AGENT;
		}
		curl_setopt($process, CURLOPT_USERAGENT, $userAgent);
		@curl_setopt($process, CURLOPT_FOLLOWLOCATION, 1);
		return $process;
	}

	public function multy($resources, $close_connections = false) {
		$h = curl_multi_init();
		foreach ($resources as $process) {
			curl_multi_add_handle($h, $process);
		}
		$running = null;
		do {
			sleep( 1 );
			curl_multi_exec( $h, $running );
		} while( $running > 0 );
		$results = array();
		foreach ($resources as $process) {
			$obj = new StdClass();
			$obj->responseText = curl_multi_getcontent($process);
			$obj->responseStatus = curl_getinfo($process, CURLINFO_HTTP_CODE);
			$obj->responseStatusText = isset($this->_codes[$obj->responseStatus]) ? $this->_codes[$obj->responseStatus] : '';
			if ($close_connections) {
				curl_close($process);
			}
			$results[] = $obj;
		}
		return $results;
	}

	public function getCookie() {
		$file = dirname(__FILE__) . '/cache/cookie';
		if (file_exists($file)) {
			$ls = explode("\n", file_get_contents($file) );
			$cookies = array();
			foreach ($ls as $s) {
				if (strpos($s, "\t") === false) {
					continue;
				}
				$offset = -1;
				$first_tab = 0;
				for ($i = 0; $i < 5; $i++) {
					$offset = strpos($s, "\t", $offset + 1);
					if ($i == 0) {
						$first_tab = $offset;
					}
				}
				$end_name_offset = strpos($s, "\t", $offset + 1);
				$cookie_name = trim(substr($s, $offset + 1, $end_name_offset - $offset));
				$cookie_value = trim(substr($s, $end_name_offset + 1));
				$host_name = substr($s, 0, $first_tab);
				$host_name = str_replace('#HttpOnly_', '', $host_name);
				$host_name = preg_replace("#^\.#", '', $host_name);
				$cookies[ "{$host_name}_{$cookie_name}" ] = $cookie_value;
			}
			return $cookies;
		} else {
			throw new Exception("cookie file not found!");
		}
	}
	public function sendRawPost($url, $data, $referer = '', &$process = null, $close_connection = true, $userAgent = '', array $aHeaders = [])
	{
		$obj = $this->_searchInCache($url, $data);
		if ($obj) {
			return $obj;
		}
		$this->prepareRawPost($url, $data, $referer, $process, $userAgent, $aHeaders);
		$response = curl_exec($process);
		$httpCode = curl_getinfo($process, CURLINFO_HTTP_CODE);
		if ($close_connection) {
			curl_close($process);
		}
		$obj = new \StdClass();
		$obj->text = $response;
		$obj->status = $httpCode;
		$obj->statusText = isset($this->_codes[$httpCode]) ? $this->_codes[$httpCode] : '';
		$obj->json = json_decode($obj->text);
		$this->_cache($url, $data, $obj);
		$obj->sendData = $data;
		$this->_log($obj, $url);
		return $obj;
	}
	private function _getCacheKey($url, $data = []) {
		if (is_array($data)) {
			if ($this->_cacheType == self::CACHE_TYPE_STORAGE) {
				$url = $this->_deleteStamp($url);
				if ($this->_skipDeleteStamp != true) {
					unset($data['stamp']);
				}
			}
			return md5($url . md5( join(',', $data) ));
		}
		if ($this->_cacheType == self::CACHE_TYPE_STORAGE) {
			$url = $this->_deleteStamp($url);
		}
		return md5($url . $data);
	}
	/**
	 * @description  Запрос DELETE на сервер c данными в формате JSON
	 * @param string $url
	 * @param string $data
	 * @param string $referer = ''
	 * @param curl &$process = null
	 * @param bool $close_connection = true можно оставить соединение окрытым
	 * @param string $userAgent = '' (default see const USER_AGENT)
	 * @param array $aHeaders = []
	 * @return StdClass {responseText, responseStatus. responseStatusText}
	 **/
	public function sendRawDelete($url, $data, $referer = '', &$process = null, $close_connection = true, $userAgent = '', array $aHeaders = [])
	{
		return $this->_sendRawRequest('DELETE', $url, $data, $referer, $process, $close_connection, $userAgent, $aHeaders);
	}
	/**
	 * @description  Запрос PUT на сервер c данными в формате JSON
	 * @param string $url
	 * @param string $data
	 * @param string $referer = ''
	 * @param curl &$process = null
	 * @param bool $close_connection = true можно оставить соединение окрытым
	 * @param string $userAgent = '' (default see const USER_AGENT)
	 * @param array $aHeaders = []
	 * @return stdClass {responseText, responseStatus. responseStatusText}
	 **/
	public function sendRawPut($url, $data, $referer = '', &$process = null, $close_connection = true, $userAgent = '', array $aHeaders = [])
	{
		return $this->_sendRawRequest('PUT', $url, $data, $referer, $process, $close_connection, $userAgent, $aHeaders);
	}

	private function _sendRawRequest($sMethod, $url, $data, $referer = '', &$process = null, $close_connection = true, $userAgent = '', array $aHeaders = [])
	{
		$process = $this->prepareRawPost($url, $data, $referer, $process, $userAgent, $aHeaders);
		curl_setopt($process, CURLOPT_CUSTOMREQUEST, $sMethod);
		$response = curl_exec($process);
		$httpCode = curl_getinfo($process, CURLINFO_HTTP_CODE);
		if ($close_connection) {
			curl_close($process);
		}
		$obj = new \StdClass();
		$obj->text = $response;
		$obj->status = $httpCode;
		$obj->statusText = isset($this->_codes[$httpCode]) ? $this->_codes[$httpCode] : '';
		$obj->json = json_decode($obj->text);
		$obj->sendData = $data;
		$this->_log($obj, $url);
		return $obj;
	}

	private function _deleteStamp($url) {
		if ($this->_skipDeleteStamp == true) {
			return $url;
		}
		$url = preg_replace("#stamp=[^&]+&?#", '', $url);
		$url = preg_replace("#&$#", '', $url);
		return $url;
	}
	/**
	 * @description Ищет данные толоько в кэше. Параметры аналогичны первым двум параметрам метода execute.
	 */
	public function searchInCache($url, $data)
	{
		return $this->_searchInCache($url, $data);
	}
	private function _searchInCache($url, $data) {
		if ($this->_useCheckpoints) {
			$this->_initTimelog();
		}
		if ($this->_cacheTtl > 0) {
			if ($this->_skipCache || $this->_cacheOff) {
				if ($this->_skipCache) {
					$this->_skipCache = false;
				}
				return null;
			}


			$key = $this->_getCacheKey($url, $data);

			if ($this->_cacheType == self::CACHE_TYPE_STORAGE && $this->_sharedCache) {
				return $this->_sharedCache->get($key); //TODO
			}

			@session_start();
			$srcData = $data;
			if (!isset($_SESSION[self::SESSION_KEY])) {
				$_SESSION[self::SESSION_KEY] = [];
			}
			if (isset($_SESSION[self::SESSION_KEY][$key]['data']) && isset($_SESSION[self::SESSION_KEY][$key]['time'])) {
				if (time() - $_SESSION[self::SESSION_KEY][$key]['time'] < $this->_cacheTtl) {
					$data = $_SESSION[self::SESSION_KEY][$key]['data'];
					if ($data->status != 200) {
						return null;
					}
					return $data;
				}
			}
		}
		return null;
	}

	private function _cache($url, $data, $result) {
		if ($result->status != 200) {
			return;
		}
		if ($this->_cacheTtl > 0) {
			@session_start();
			$key = $this->_getCacheKey($url, $data);
			if ($this->_cacheType == self::CACHE_TYPE_STORAGE && $this->_sharedCache) {
				$this->_sharedCache->set($key, $result);
				return;
			}

			if (!isset($_SESSION[self::SESSION_KEY])) {
				$_SESSION[self::SESSION_KEY] = [];
			}
			$_SESSION[self::SESSION_KEY][$key] = [];
			$_SESSION[self::SESSION_KEY][$key]['data'] = $result;
			$_SESSION[self::SESSION_KEY][$key]['time'] = time();
		}
	}

	public function prepareRawPost($url, $data, $referer = '', &$process = null, $userAgent = '', array $aHeaders = [])
	{
		if (!$process) {
			$process = curl_init($url);
		} else {
			curl_setopt($process, CURLOPT_URL, $url);
		}
		curl_setopt($process, CURLOPT_HEADER, 0);
		if(trim($data)) {
			curl_setopt($process, CURLOPT_POST, 1);
			curl_setopt($process, CURLOPT_POSTFIELDS, $data);
		}
		$headers = [
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Content-Type: text/plain'
		];

		if ($aHeaders) {
			$headers = $aHeaders;
		}

		curl_setopt($process, CURLOPT_HTTPHEADER, $headers);

		if ($referer) {
			curl_setopt($process, CURLOPT_REFERER, $referer);
		}
		curl_setopt($process, CURLOPT_RETURNTRANSFER, 1);
		if (strpos($url, 'https') === 0) {
			curl_setopt($process, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($process, CURLOPT_SSL_VERIFYPEER, false);
		}
		curl_setopt($process, CURLOPT_COOKIEFILE, dirname(__FILE__) . $this->_cookie_file);
		curl_setopt($process, CURLOPT_COOKIEJAR, dirname(__FILE__) . $this->_cookie_file);
		if (!$userAgent) {
			$userAgent = self::USER_AGENT;
		}
		curl_setopt($process, CURLOPT_USERAGENT, $userAgent);
		@curl_setopt($process, CURLOPT_FOLLOWLOCATION, 1);
		return $process;
	}
	public function setCacheTtl($cacheTtl) {
		$this->_cacheTtl = $cacheTtl;
	}

	public function clearCache($url = null, $data = array()) {
		if ($url) {
			$key = $this->_getCacheKey($url, $data);
			if ($this->_cacheType == self::CACHE_TYPE_STORAGE && $this->_sharedCache) {
				$this->_sharedCache->remove($key);
				return;
			}
			@session_start();
			$_SESSION[self::SESSION_KEY][$key] = [];
		} else {
			if ($this->_cacheType == self::CACHE_TYPE_STORAGE  && $this->_sharedCache) {
				$this->_sharedCache->clear();
				return;
			}
			@session_start();
			$_SESSION[self::SESSION_KEY] = [];
		}
	}
	public function noCacheNextQuery() {
		$this->_skipCache = true;
	}
	public function cacheOff() {
		$this->_cacheOff = true;
	}

	/**
	 * @param int $type = self::CACHE_TYPE_SESSION || self::CACHE_TYPE_STORAGE
	 */
	public function setCacheType($type = self::CACHE_TYPE_STORAGE) {
		$this->_cacheType = $type;
	}

	/**
	 * @desc true если надо учитывать GET параметр stamp в запросе.
	 * По умолчанию из каждого http запроса к апи вырезается stamp.
	 * Полученное значение используется для хранения результата запроса в кеше приложения.
	 * Это делается для того, чтобы разные пользователи не делали один и тот же запрос к базе,
	 * например магазины категории одни и те же для разных пользователей, поэтому нет смысла делать запрос
	 * для каждого пользователя. Но бывают персональные данные, завязанные на идентификатор пользователя.
	 * Для таких запросов вызывается setSkipDeleteStamp(true);
	 *
	 * @param bool $v
	 */
	public function setSkipDeleteStamp($v) {
		$this->_skipDeleteStamp = $v;
	}

	/**
	 * @description Конвертирует домен в url из кириллицы в xn-
	 */
	private function _idna($sUrl) {
		$sUrl = trim($sUrl);
		if (strpos($sUrl, 'http') !== 0) {
			$sUrl = 'http://' . $sUrl;
		}
		$aUrl = parse_url($sUrl);
		if (!isset($aUrl['scheme'])) {
			$aUrl['scheme'] = 'http';
		}
		if (!isset($aUrl['host'])) {
			return $sUrl;
		}
		$domain = $aUrl['host'];
		$idn = new IdnaConvert(array('idn_version'=>2008));
		$punycode = (stripos($domain, 'xn--') !== false) ? $idn->decode($domain) : $idn->encode($domain);
		$aUrl['host'] = $punycode;
		$sResult = $aUrl['scheme'] . '://' . $aUrl['host'] . (isset($aUrl['path']) ? $aUrl['path'] : '')  . (isset($aUrl['query']) ? '?' . $aUrl['query'] : '');
		return $sResult;
	}

	/**
	 * @description Конвертирует домен в url из кириллицы в xn-
	 */
	public function idna($sUrl) {
		return $this->_idna($sUrl);
	}
	public function _cpoint($msg = '', $cmd = '', $noprint = false)
	{
		if (!isset($_GET['ladebug']) && $noprint === false) {
			$noprint = true;
		}
		global $dbg_start_time, $dbg_total_start_time, $dbg_time_report;
		$ret_obj = new \StdClass();
		$ret_obj->time = null;
		$ret_obj->total_time = null;
		if ($cmd == 'set') {
			if (!$dbg_total_start_time) {
				$dbg_total_start_time = microtime(true);
			}
			$dbg_start_time = microtime(true);
			if ($msg) {
				if (!$noprint) {
					print "{$msg}\n";
				}
			}
			$dbg_time_report[] = $msg . "<br>\n";
		} else {
			$time = microtime(true) - $dbg_start_time;
			if (!$noprint) {
				print $msg . ': ' . "{$time}<br>\n";
			}
			$ret_obj->time = $time;
			if ($cmd == 'total') {
				$time = microtime(true) - $dbg_total_start_time;
				$ret_obj->total_time = $time;
				if (!$noprint) {
					print 'Total: ' . "{$time}<br>\n";
				}
			}
			$dbg_time_report[] = $msg . ': ' . "{$time}<br>\n";
		}
		return $ret_obj;
	}

	public function writeTimeLog()
	{
		global $dbg_time_report;
		if (Config::get('app.log_time_on', 0) != 1 || !is_array($dbg_time_report)) {
			return;
		}
		$url = '';
		if (isset($_SERVER['REQUEST_URI'])) {
			$url = explode('?', $_SERVER['REQUEST_URI'])[0];
			$url = str_replace('/', '+', $url);
		}
		$file = __DIR__ . '/../../storage/logs/' . date('Y-m-d-H-i-s-') . $url . microtime(true) . '.timelog';
		file_put_contents($file, join("\n", $dbg_time_report));
	}
	public function _initTimelog()
	{
		global $dbg_start_time, $dbg_time_report;
		if (!$dbg_start_time) {
			$this->_cpoint('First req', 'set', true);
			$dbg_start_time = true;
			$dbg_time_report = [];
		}
	}
	private function _log($obj, $url = null) {
		return;

		$timeStr = '';
		if ($this->_useCheckpoints) {
			$timeData = $this->_cpoint('end req', 'total', true);
			$timeStr = "\n\ntime = {$timeData->time}\ntotal time = {$timeData->total_time}\n";
		}
		$date = date('Y-m-d+H:i:s');
		if (is_string($obj)) {
			$filename = storage_path('framework/cache/outer_request.log');
			file_put_contents($filename, "=============================\n\n{$date}: {$obj}{$timeStr}\n\n========================================\n\n", FILE_APPEND);
			return;
		}

		$data = print_r($obj, 1);
		$filename = storage_path('framework/cache/outer_request.log');
		file_put_contents($filename, "=============================\n\n{$date}\n\n========================================\n\nurl = {$url}\n\n--------{$timeStr}\n\n{$data}\n\n========================================\n\n", FILE_APPEND);
	}

}

