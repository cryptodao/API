<?php
/**
 * Class Api_cryptodao
 */
class Api_cryptodao{

	private $apiurl = 'https://cryptodao.com/api/';//Pay attention to the last slash

	private $key = 'xx';

	private $privateKey = 'yy';

	/**
	 *
	 * @var string
	 */
	public $secret_file = './secret.store.dat';

	/**
	 *
	 * @var string
	 */
	private $secret = false;
	private $secret_expiry = false;

	/**
	 *
	 * @var bool
	 */
	private $debug = false;

	/**
	 */
	public function __construct($key = false, $privateKey = false, $secret_file = false){
		$key && $this->key = $key;
		$privateKey && $this->privateKey = $privateKey;
		$secret_file && $this->secret_file = $secret_file;
		$this->load_secret();
	}

	/**
	 *
	 * @return array
	 * @access open
	 */
	public function ticker($source, $target){
		return $this->request('ticker', array('source' => $source, 'target' => $target), 'GET', false);
	}

	/**
	 *
	 * @return array
	 * @access open
	 */
	public function depth($source, $target){
		return $this->request('depth', array('source' => $source, 'target' => $target), 'GET', false);
	}

	/**
	 *
	 * @param $since -1~9999999999 
	 *
	 * @return array
	 * @access open
	 */
	public function trades($source, $target, $since = -1){
		return $this->request('trades', array('source' => $source, 'target' => $target, 'since' => $since), 'GET', false);
	}

	/**
	 * Account Balance
	 *
	 * @return array
	 * @access readonly
	 */
	public function balance(){
		return $this->request('balance', array(), 'POST');
	}

	/**
	 *
	 * @return array
	 * @access readonly
	 */
	public function wallet($target){
		return $this->request('wallet', array('target' => $target), 'POST');
	}

	/**
	 *
	 * @param integer $since ）
	 * @param string open|all $type 
	 *
	 * @return array
	 * @access readonly
	 */
	public function orders($source, $target, $since = 0, $type = 'open'){
		return $this->request('orders', array('source' => $source, 'target' => $target, 'since' => $since, 'type' => $type), 'POST');
	}


	/**
	 *
	 * @param integer $orderid ID
	 *
	 * @return array
	 * @access readonly
	 */
	public function fetchOrder($orderid){
		return $this->request('fetchOrder', array('id' => $orderid), 'POST');
	}

	/**
	 *
	 * @param integer $orderid ID
	 *
	 * @return array
	 * @access full
	 */
	public function cancelOrder($orderid){
		return $this->request('cancelOrder', array('id' => $orderid), 'POST');
	}

	/**
	 *
	 * @param float $amount 
	 * @param float $price 
	 *
	 * @return array
	 * @access full
	 */
	public function buy($source, $target, $amount, $price){
		return $this->request('buy', array('source' => $source, 'target' => $target, 'amount' => $amount, 'price' => $price), 'POST');
	}

	/**
	 *
	 * @param float $amount 
	 * @param float $price 
	 *
	 * @return array
	 * @access full
	 */
	public function sell($source, $target, $amount, $price){
		return $this->request('sell', array('source' => $source, 'target' => $target, 'amount' => $amount, 'price' => $price), 'POST');
	}

	/**
	 *
	 * @param string $method API
	 * @param array $params 
	 * @param string GET|POST $http_method 
	 * @param bool $auth 
	 *
	 * @return array
	 */
	protected function request($method, $params = array(), $http_method = 'GET', $auth = true){
		if($auth){
			if(60 > $this->secret_expiry - time()){
				$this->refresh_secret();
			}
			
			$mt = explode(' ', microtime());
			$params['nonce'] = $mt[1] . substr($mt[0], 2, 2);
			
			$params['key'] = $this->key;
			$params['signature'] = hash_hmac('sha256', http_build_query($params, '', '&'), $this->secret);
		}
		
		$data = http_build_query($params, '', '&');
		$data = $this->do_curl($method, $data, ($http_method == 'GET'? 'GET': 'POST'));
		return $data;
	}

	/**
	 *
	 * @return bool
	 */
	private function load_secret(){
		
		if(file_exists($this->secret_file)){
			$storTime = @filemtime($this->secret_file);
			
			if(7200 > time() - $storTime){
				$this->secret = trim(file_get_contents($this->secret_file));
				$this->secret_expiry = $storTime + 7200;
				return true;
			}
		}
		return $this->refresh_secret();
	}

	/**
	 *
	 * @return bool
	 */
	private function refresh_secret(){
		$param = 'privateKey=' . urlencode($this->privateKey).'&key='.$this->key;
		$data = $this->do_curl('getSecret', $param, 'POST');
		if($data['result'] && $this->secret = $data['data']['secret']){
			file_put_contents($this->secret_file, $this->secret);
			$this->secret_expiry = time() + 7200;
			return true;
		}
		return false;
	}

	/**
	 *
	 * @param string $path API
	 * @param string $data
	 * @param string GET|POST $http_method
	 *
	 * @return array
	 */
	private function do_curl($path, $data, $http_method){
		static $ch = null;
		$url = $this->apiurl . $path;
		if($this->debug){
			echo "Sending request to $url\n";
		}
		if(is_null($ch)){
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; Btharbour PHP client; ' . php_uname('s') . '; PHP/' . phpversion() . ')');
		}
		if($http_method == 'GET'){
			$url .= '?' . $data;
		}
		else{
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		}
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		$response = curl_exec($ch);
		if($this->debug){
			echo "Response: $response\n";
		}
		if(empty($response)){
			throw new Exception('Could not get reply: ' . curl_error($ch));
		}
    //echo $response;exit();
		$data = json_decode($response, true);
		if(!is_array($data)){
			throw new Exception('Invalid data received');
		}
		return $data;
	}
}