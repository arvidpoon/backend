<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Welcome extends CI_Controller {

	private function _sendRequestPost($data, $url)
	{
	    $postdata = array(
	    	'path' => json_encode($data),
	    );

	    $ch = curl_init($url);
	    curl_setopt($ch, CURLOPT_POST, true);                                                                      
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);                                                                  
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                                                                      
		$result = curl_exec($ch);
		if ($result === false)
		{
		    $info = curl_getinfo($ch);
		    curl_close($ch);
		    die('error occured during curl exec. Additioanl info: ' . var_export($info));
		}
		curl_close($ch);
		return $result;

	}

	public function index()
	{

		$data = array(
			array("22.372081", "114.107877"),
			array("22.284419", "114.159510"),
			array("22.3469144", "114.1981317"),
			array("22.3508364", "114.2454191"),
			array("22.326442", "114.167811"),
			array("22.3436521" ,"114.1974397"),
		);

		$url = base_url() . "route"; 
		$token_json = json_decode($this->_sendRequestPost($data, $url), true);
		$token = $token_json['token'];

        $twig_param = array(
        	'return' => $token,
    	);
    	echo $this->twig->render('welcome_message', $twig_param);

	}

}
