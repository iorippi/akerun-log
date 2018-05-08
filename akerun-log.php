<?php
/*  - - - - - - - - - - - - - - - - - - 
	  Akerun API Interpreter
	  Version 0.8.0
	  (c)2018 Iori Tatsuguchi
	  source: https://github.com/iorippi/akerun-log/edit/feature-update/function.php

	  License: MIT License: Do whatever the heck you want with this but there's no guarantee whatsoevvvvvvvrrrrr
	- - - - - - - - - - - - - - - - - - */
// Config
// * Last slash after directory name must be omitted
define('AKERUNLOG_CACHE_DIR', dirname(__FILE__).'/akerunlog_cache');
define('AKERUNLOG_CACHE_FILENAME', 'cache.json');
define('AKERUNLOG_CACHE_FILEPATH', AKERUNLOG_CACHE_DIR.'/'.AKERUNLOG_CACHE_FILENAME);

class AkerunLog {
	// Outputs (per single instance)
	public $data; // Raw data (JSON from API Call to PHP Array, plus some metadata about execution process)
	public $name;
	protected $pid = 0;
	protected $total_requests = 0;
	private $output_test;
	// Outputs (shared across instances)
	public static $exec_err_log = array();
	protected static $data_cache = array ();
	// General settings
	protected $max_apireq_permin = 50;// Max. API Call per minute
	protected $akerun_api_url = "https://api.akerun.com/v2/external/accesses";
	protected $timezone = "Asia/Tokyo";
	// Specific settings
	protected $akerun_params = array(
		'akerun_id'		=> '',		// *Required
		'offset'		=> NULL,	// API Default: 0
		'limit'			=> 300,		// API Default: 20
		'from'			=> NULL,	// API Default: As far as limit goes?
		'til'			=> NULL,	// API Default: Now?
		'access_token'	=> ''		// *Required
	);
	protected $log_hours = 24;		// [NULL | number] if non-NULL, overrides $akerun_params['from'] ---future implementation: daily, weekly
	protected $filter_user_id = array();
	protected $filter_user_full_name = array();
	protected $nfc_only = TRUE;

	public function __construct($options) {
		// 1. Update options
		$this->pid++;
		$this->total_requests++;
		$this->update_options($options);

		// 2. Set timezone
		date_default_timezone_set($this->timezone);

		// 3. Check session cache, hard cache or API request
		$this->get_session_cache() || $this->get_hard_cache() || $this->get_api();
		$this->data = self::$data_cache[$this->akerun_api_url];
		
		// 4. Output test (for development debugging)
		if ($this->test && get_class($this) == 'AkerunLog')
			self::test_output();
	}
	
	private function write_exec_err_log($message) {
		self::$exec_err_log[$akerunlog_pid] = 'Akerunlog PID: '.$this->akerunlog_pid.'; \nMessage: '.$message;
	}

	private function update_options($options) {
	// Update options from request given
		// Override variables if set by specific request
		foreach ($options as $option_name => $option_value) {
			if (in_array($option_name, array_keys($this->akerun_params)))
				$this->akerun_params[$option_name] = $option_value;
			else
				$this->$option_name = $option_value;
		}

		// Set time variables
		// - Set [til] and [from] (Will not override if [til] has already set)
		// - [til] has to be set for better cache controlling purpose
		// Set seconds from calculated interval
		$api_max_renewal_per_min = $this->max_apireq_permin / $this->total_requests;
		$api_renewal_interval_in_sec = ceil(60 / $api_max_renewal_per_min);
		$til_sec = date('s') - date('s') % $api_renewal_interval_in_sec;
		if ($this->akerun_params['til'] === NULL)
			$this->akerun_params['til'] = date('Y-m-d\TH:i:'.$til_sec.'\.000\Z', time() - date('Z'));
		if ($this->log_hours !== NULL) {
			$from = strtotime('-'.$this->log_hours.' hours', strtotime($this->akerun_params['til']));
			$this->akerun_params['from'] = date('Y-m-d\TH:i:s\.000\Z', $from);
		}

		// Clean-up unspecified parameters
		$this->akerun_params = array_filter($this->akerun_params, function($cur) {
			return $cur !== NULL;
		});

		// Set URL for API call
		$url = $this->akerun_api_url.'?';
		foreach ($this->akerun_params as $param_name => $param_value)
			$url = $url.$param_name.'='.$param_value.'&';
		$this->akerun_api_url = rtrim($url, '&');

		// Check required options before execution
		if (!isset($this->$akerun_params['akerun_id'], $this->$akerun_params['access_token']))
			$this->write_exec_err_log("AkerunLog::__construct: Required variable(s) are missing. Check [akerun_id] and [access_token]");
	}

	private function get_session_cache() {
	// Retrieve Array data from session-cache if exists
		// Pull sesson-cache regardless of api_time
		if (isset(self::$data_cache['akerun_api_url'][$this->akerun_api_url]))
			return TRUE;
		else
			return FALSE;
	}

	private function declare_hard_cache() {
		// Set decoded hard cache to $this->hard_cache_php if exists
		$result = FALSE;
		if (file_exists(AKERUNLOG_CACHE_FILEPATH)) {
			$hard_cache_json = file_get_contents(AKERUNLOG_CACHE_FILEPATH);
			$hard_cache_php = json_decode($hard_cache_json, TRUE);
			$this->hard_cache_php = $hard_cache_php;
			$result = TRUE;
		}

		return $result;
	}

	private function get_hard_cache() {
	// Retrieve JSON data from hard-cache if exists
		$result = FALSE;
		if ($this->declare_hard_cache()) {
			$hard_cache_php = $this->hard_cache_php;
			// Register cache to session and return ONLY IF [time ([til] option)] matches
			if ($hard_cache_php['time'] == $this->akerun_params['til'] && isset($hard_cache_php[$this->akerun_api_url])) {
				self::$data_cache = $hard_cache_php;
				$result = TRUE;
			}
		}

		return $result;
	}

	private function get_api() {
	// Retrieve JSON data from API call
		// Check remaining call
		// $remaining_call = $this->max_apireq_permin - self::$data_cache['apireq_count'];
		
		// Call API, save to hard
		$api_json = file_get_contents($this->akerun_api_url);
		if ($api_json === FALSE) {
			$this->write_exec_err_log("Failed at AkerunLog::get_api():file_get_contents()");
			return FALSE;
		}
		$api_php = json_decode($api_json, TRUE);
		// Save to session cache
		if (!empty(self::$data_cache)) {
			// Append
			self::$data_cache[$this->akerun_api_url] = $api_php;
		} else {
			// Create new
			self::$data_cache = array(
				'time' => [time (til)],
				'apireq_count' => 0, // 未使用
				$this->akerun_api_url => $api_php
			);
		}
		self::$data_cache['apireq_count']++;

		// Save to hard cache
		$api_json = json_encode(self::$data_cache, TRUE);
		if (!file_exists(AKERUNLOG_CACHE_DIR))
			mkdir(AKERUNLOG_CACHE_DIR);
		file_put_contents(AKERUNLOG_CACHE_FILEPATH, (string)$api_json);

		return TRUE;
	}
	public function test_output() {
		?>
		<section class="akerun-log_test">
			<meta charset="utf-8">
			<h1><?php echo get_class($this);?></h1>
			<ul>
				<?php foreach ($this as $key => $value): ?>
				<li>
					<h2><?php echo $key;?></h2>
					<pre><?php print_r($value);?></pre>
				</li>
				<?php endforeach;?>
			</ul>
		</section>
		<?php
	}
}
class AkerunLogByUsers extends AkerunLog {
	public $data_users = array();
	private $output_test;

	public function __construct($options) {
		// Reserve 'test' switch for later
		if (isset($options['test'])) {
			$this->output_test = TRUE;
			unset($options['test']);
		}

		// 1. Retrieve raw data
		parent::__construct($options);

		// 2. Parse data
		$data_users = array();
		foreach ($this->data['accesses'] as $log_index => $log_data) {
			// Retrieve info
			$id = $log_data['user']['id'];
			$full_name = $log_data['user']['full_name'];
			$history = array(
				'client_type' => $log_data['client_type'],
				'created_at' => $log_data['created_at']
			);
			// Ditch non-NFC users
			if ($this->nfc_only && strpos($log_data['client_type'], 'nfc_') === FALSE)
				continue;
			// Ditch filtered users
			if (in_array($id, $this->filter_user_id) || in_array($full_name, $this->filter_user_full_name))
				continue;
			if (!array_key_exists($id, $data_users)) {
				$data_users[$id] = array(
					'name' => $full_name,
					'history' => array()
				);
			}
			array_push($data_users[$id]['history'], $history);
		}
		$this->data_users = $data_users;

		// 3. Output test (for development debugging)
		if ($this->test && get_class($this) == 'AkerunLogByUsers')
			self::test_output();
	}
}
class AkerunLogByNFCUsers extends AkerunLogByUsers {
	public $nfc_user_count;
	private $output_test;

	public function __construct($options) {
		// Reserve 'test' switch for later
		if (isset($options['test'])) {
			$this->output_test = TRUE;
			unset($options['test']);
		}

		// 1. Retrieve raw data
		$options['nfc_only'] = TRUE; // NFC 制限を強制
		parent::__construct($options);
		
		// 2. Parse data
		$this->nfc_user_count = array_reduce($this->data_users, function ($carry, $user_data) {
			return $user_data['history'][0]['client_type'] == "nfc_outside" ? $carry + 1 : $carry;
		}, 0);

		// 3. Output test (for development debugging)
		if ($this->test && get_class($this) == 'AkerunLogByNFCUsers')
			self::test_output();
	}
}
?>
