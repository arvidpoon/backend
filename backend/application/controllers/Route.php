<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require(APPPATH.'/libraries/REST_Controller.php');

class Route extends REST_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->model('route_model');

	}

	private function route_calculation($token) 
	{

		// execute the calculation in background mode (Command line)
		$cmd = "php " . FCPATH . "index.php calculate token " . $token;
		if (substr(php_uname(), 0, 7) == "Windows")
		{
		    pclose(popen("start /B ". $cmd, "r")); 
		}
		else 
		{
		    exec($cmd . " > /dev/null &");  
		}

	}

	public function index_post()
	{

		$path = json_decode($this->input->post('path'), true);
		$result = $this->route_model->_coordinate_validation ($path);
		if ($result['status'] != 'OK')
		{
			$this->response(array(
				'error' => $result['error'],
			), 200);
			exit;
		}

		$token = $this->route_model->_generate_token();

		// save information to file and ready to process
		$this->route_model->_create_route_file ($path);

		$this->route_calculation($token);

		$this->response(array(
			'token' => $token,
		), 200);
		exit;

	}

	public function index_get($token)
	{

		$this->route_model->set_token ($token);
		if (! $this->route_model->_check_route_file_exist())
		{
			$this->response(array(
				'status' => 'failure',
				'error' => 'Invalid token',
			), 200);
			exit;
		}

		$content = $this->route_model->_read_route_file();
		if (empty ($content))
		{
			$this->response(array(
				'status' => 'failure',
				'error' => "Invalid format",
			), 200);
			exit;
		}
		if ($content['response_status'] == "fail")
		{
			$this->response(array(
				'status' => 'failure',
				'error' => $content['response_message'],
			), 200);
			exit;
		}

		if ($content['response_status'] == "pending")
		{
			if ($content['retried'] == 3)
			{
				$this->response(array(
					'status' => 'failure',
					'error' => 'Already retried for 3 times. Timeout.',
				), 200);
				exit;
			}
			$last_modified = new DateTime($content['modified']);
			$since_modified = $last_modified->diff(new DateTime());
			$minutes = $since_modified->days * 24 * 60;
			$minutes += $since_modified->h * 60;
			$minutes += $since_modified->i;
			if ($minutes > 1) 
			{
				$this->route_calculation($token);
			}
			$this->response(array(
				'status' => 'in progress',
			), 200);
			exit;
		}

		$path = array();
		foreach ($content['shortest_route']['legs'] as $leg)
		{
			foreach ($leg['steps'] as $step)
			{
				$path[] = array (
					$step['start_location']['lat'],
					$step['start_location']['lng'],
				);
			}
		}
		$last = end ($content['shortest_route']['legs']);
		$last_step = end ($last['steps']);
		$path[] = array(
			$last_step['end_location']['lat'],
			$last_step['end_location']['lng'],
		);
		$this->response(array(
			'status' => 'success',
			'path' => json_encode($path),
			'total_distance' => $content['shortest_route']['distance'],
			'total_time' => $content['shortest_route']['duration'],
		), 200);

	}

}
