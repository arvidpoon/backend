<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Calculate extends CI_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->model('route_model');

	}

	public function token() 
	{

		if (! $this->uri->segment(3))
		{
			exit;
		}
		$this->route_model->set_token($this->uri->segment(3));

		if (! $this->route_model->_check_route_file_exist())
		{
			exit;
		}

		$content = $this->route_model->_read_route_file ();
		if (empty($content))
		{
			exit;
		}

		if (! array_key_exists('path', $content))
		{
			exit;
		}
		if (empty($content['path']))
		{
			exit;
		}

		if (! array_key_exists('response_status', $content))
		{
			exit;
		}
		if ($content['response_status'] != 'pending')
		{
			exit;
		}

		if (! array_key_exists('shortest_route', $content))
		{
			exit;
		}

		$this->route_model->_set_post_data($content['path']);

		$this->route_model->_calculate();

		// // Fastest
		// $result = $this->_sendRequest(array());
		// if ($result != 'OK')
		// {
		// 	$this->response(array(
		// 		'error' => $result,
		// 	), 200);
		// 	exit;
		// }

		// // Avoid highways
		// $rseult = $this->_sendRequest(array(
		// 	'avoid' => 'highways',
		// ));
		// if ($result != 'OK')
		// {
		// 	$this->response(array(
		// 		'error' => $result,
		// 	), 200);
		// 	exit;
		// }

		// // Avoid tolls
		// $result = $this->_sendRequest(array(
		// 	'avoid' => 'tolls',
		// ));
		// if ($result != 'OK')
		// {
		// 	$this->response(array(
		// 		'error' => $result,
		// 	), 200);
		// 	exit;
		// }

		// if (count ($this->routes) == 0)
		// {
		// 	$this->response(array(
		// 		'error' => "No route found.",
		// 	), 200);
		// 	exit;
		// }

		// $id = $this->_choose_shortest_route();

		// $fp = fopen ($route_file, "rb");
		// $content_json = fread ($fp, filesize($route_file));
		// fclose ($fp);
		// $content = json_decode($content_json, true);
		// $content['response_status'] = "completed";
		// $content['shortest_route'] = $this->routes[$id];
		// $fp = fopen ($route_file, "w");
		// fwrite ($fp, json_encode($content, JSON_UNESCAPED_UNICODE));
		// fclose ($fp);

	}

}