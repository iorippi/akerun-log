<?php
$akerun_log_cache = array ();
class AkerunLog {
	public $name;
	private $akerun_id;
	private $access_token;
	private $log_hours;
	private $max_api_request_per_minute = 50;
	private $log_api_url = null;
	protected $nfc_only;
	public $log;
	public $akerun_json_error_log = null;
	public function __construct($options) {
		global $akerun_log_cache;
		date_default_timezone_set("Asia/Tokyo");

		// 1. Set Parameters
		$this->name = $options['name'];
		$this->akerun_id = $options['akerun_id'];
		$this->access_token = $options['access_token'];
		if (!empty($options['log_hours']))
			$this->log_hours = $options['log_hours'];
		else
			$this->log_hours = 24;
		if (!empty($options['nfc_only']))
			$this->nfc_only = $options['nfc_only'];
		else
			$this->nfc_only = 1;
		// 2-0 Check for JSON Cache
		$log_til = strtotime('now');
		$akerun_cachefile_index = $this->akerun_id.'_'.$this->log_hours;
		$akerun_cachefile_name = 'akerunlog-cache_'.$akerun_cachefile_index.'.json';
		$akerun_log_cache_expire = $akerun_log_cache[$akerun_cachefile_index][1] ?: 0;
		if (!count($akerun_log_cache) || $akerun_log_cache_expire < $log_til) {
			// 2-a-1 Get JSON from API Call
			$log_from = strtotime('-'.$this->log_hours.' hours', $log_til);
			$log_api_param = array(
				"akerun_id" => $this->akerun_id,
				"from" => date("Y-m-d", $log_from)."T".date("H:i:s", $log_from).".000Z",
				"access_token" => $this->access_token
			);
			$log_api_url = "https://api.akerun.com/v2/external/accesses?";
			foreach ($log_api_param as $name => $val)
				$log_api_url = $log_api_url.$name.'='.$val.'&';
			$this->log_api_url = $log_api_url;
			$log_json = file_get_contents($this->log_api_url);
			if ($log_json !== false) {
				//$log_json = mb_convert_encoding($log_json, 'UTF8', 'ASCII, JIS, UTF-8, EUC-JP, SJIS-WIN');
				// 2-a-2 Save JSON to Cache
				file_put_contents($akerun_cachefile_name, (string)$log_json);
				/***
				 * $log_api_interval_sec: Cache Expiration Calculation
				 * (API Max. call: 50 times per every 60 seconds)
				 * every 50/n times in 60sec (where n is the number of unique akerun_id stored to cache: 3 akerun_ids
				 * => 16 times per minute = every 60 / 16 sec = every 4 sec)
				 */
				$log_api_interval_denom = count($akerun_log_cache) ?: 1;
				$log_api_interval_sec = ceil(60 * ($log_api_interval_denom / $this->max_api_request_per_minute));
				$akerun_log_cache[$akerun_cachefile_index] = array(
					$akerun_cachefile_name,
					strtotime('+'.$log_api_interval_sec.' seconds', $log_til) // Cache expiration time
				);
			} else {
				$this->akerun_json_error_log = "PHP: file_get_contents() failed.";
			}
		} else {
		// 2-b Get JSON from Cache
			$log_json = file_get_contents($akerun_cachefile_name);
		}
		// 3. Convert JSON to PHP Array
		$log = json_decode($log_json, true);
		$this->log = $log;
		if ($log['messages'])
			$this->akerun_json_error_log = "API returned error message: ".$log['messages'][0];
		elseif (!$log['success'])
			$this->akerun_json_error_log = "Unknown Error";
		
		// test-0
		if ($options['test'][0]):?>
			<section class="akerun-log_test">
				<meta charset="utf-8">
				<h1>AkerunLog __construct</h1>
				<ul>
					<li><h2>$akerun_log_cache:</h2><pre><?php print_r($akerun_log_cache); ?></pre></li>
					<li><h2>$name:</h2><pre><?php print_r($this->name); ?></pre></li>
					<li><h2>$akerun_id:</h2><pre><?php print_r($this->akerun_id); ?></pre></li>
					<li><h2>$access_token:</h2><pre><?php print_r($this->access_token); ?></pre></li>
					<li><h2>$log_hours:</h2><pre><?php print_r($this->log_hours); ?></pre></li>
					<li><h2>$max_api_request_per_minute:</h2><pre><?php print_r($this->max_api_request_per_minute); ?></pre></li>
					<li><h2>$log_api_url:</h2><pre><?php print_r($this->log_api_url); ?></pre></li>
					<li><h2>$nfc_only</h2><pre><?php print_r($this->nfc_only); ?></pre></li>
					<li><h2>$akerun_json_error_log</h2><pre><?php print_r($this->akerun_json_error_log); ?></pre></li>	
					<li><h2>$log:</h2><pre><?php print_r($this->log); ?></pre></li>
				</ul>
			</section>
		<?php endif;
	}
}
class AkerunLogByUsers extends AkerunLog {
	public $log_users;
	public function __construct($options) {
		// 1. Create log
		parent::__construct($options);
		if ($this->akerun_json_error_log !== null)
			return;
		// 2. Parse log
		$log_users = array();
		foreach ($this->log['accesses'] as $log_index => $log_data) {
			if ($this->nfc_only && strpos($log_data['client_type'], 'nfc_') === false)
				continue;
			$id = $log_data['user']['id'];
			$full_name = $log_data['user']['full_name'];
			$history = array(
				$log_data['client_type'],
				$log_data['created_at']
			);
			if (!array_key_exists($id, $log_users)) {
				$log_users[$id] = array(
					'name' => $full_name,
					'history' => array()
				);
			}
			array_push($log_users[$id]['history'], $history);
		}
		$this->log_users = $log_users;
		echo 'testopt';
		print_r($options['test']);
		// test-1
		if ($options['test'][1]):?>
			<section class="akerun-log_test">
				<meta charset="utf-8">
				<h1>AkerunLogByUsers __construct</h1>
				<ul>
					<li><h2>$log_users:</h2><pre><?php print_r($this->log_users); ?></pre></li>
				</ul>
			</section>
		<?php endif;
	}
}
class AkerunLogByNFCUsers extends AkerunLogByUsers {
	public $nfc_user_count;
	public function __construct($options) {
		// 1. Create log (NFC Only)
		$options['nfc_only'] = 1; // NFC 制限を強制
		parent::__construct($options);
		// 2. Parse log
		$this->nfc_user_count = array_reduce($this->log_users, function ($carry, $user_data) {
			return $user_data['history'][0][0] == "nfc_outside" ? $carry + 1 : $carry;
		}, 0);
		// test-2
		if ($options['test'][2]):?>
			<section class="akerun-log_test">
				<meta charset="utf-8">
				<h1>AkerunLogByNFCUsers __construct</h1>
				<ul>
					<li><h2>$nfc_user_count:</h2><pre><?php print_r($this->nfc_user_count); ?></pre></li>
				</ul>
			</section>
		<?php endif;
	}
}
?>
