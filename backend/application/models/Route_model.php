<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Route_model extends CI_Model {

    // The valid value of latitude is between -90.000000 and 90.000000
    protected $valid_lat = array (
        'min' => -90, 
        'max' => 90,
    );

    // The valid value of longitude is between -180.000000 and 180.000000
    protected $valid_lng = array (
        'min' => -180, 
        'max' => 180,
    );

    protected $valid_field = array('path', 'response_status', 'response_message', 'retried', 'shortest_route');

    protected $CI;
    protected $post_data;
    protected $routes = array();
    protected $shortest_distance = -1;
    protected $duration = 0;
    protected $token;

    public function __construct()
    {

        parent::__construct();
        $this->CI =& get_instance();
        $this->token = '';

    }
    
    public function set_token ($token)
    {
        $this->token = $token;
    }

    public function get_token ()
    {
        return $this->token;
    }

    // truncated to 6 decimal places, then multiply by 1,000,000 to become an integer
    private function _convert_to_int ($num)
    {

        $decimals = 7;

        $str = (string) $num;
        if (strpos($str, '.')) 
        {
            list ($int, $dec) = explode('.', $str);

            // added "0" to fill the decimal places if not a decimal places value
            $dec = str_pad($dec, $decimals, "0");

            // removed decimal point => multiply by 10,000,000
            return intval($int . substr($dec, 0, $decimals));
        }

        // added "000000" to fill the decimal places if the value is an integer => multiply by 10,000,000
        return intval($str . str_repeat("0", $decimals));
    }

    private function _in_range ($min, $max, $value) 
    {

        $value_int = $this->_convert_to_int($value);
        return (($value_int >= $min) && ($value_int <= $max));

    }

    public function _coordinate_validation ($path) 
    {

        if (count ($path) == 0) 
        {
            return array(
                'status' => 'error',
                'error' => 'Path is empty.',
            );
        }
        if (count ($path) < 2)
        {
            return array(
                'status' => 'error',
                'error' => 'No destination provided.',
            );
        }
        $min_lat = $this->_convert_to_int($this->valid_lat['min']);
        $max_lat = $this->_convert_to_int($this->valid_lat['max']);
        $min_lng = $this->_convert_to_int($this->valid_lng['min']);
        $max_lng = $this->_convert_to_int($this->valid_lng['max']);

        foreach ($path as $coordinate) 
        {
            if (count ($coordinate) != 2) 
            {
                return array(
                    'status' => 'error',
                    'error' => 'Invalid format.',
                );
            }

            list ($latitude, $longitude) = $coordinate;
            if (! is_numeric($latitude))
            {
                return array(
                    'status' => 'error',
                    'error' => 'Invalid latitude value.',
                );
            }
            if (! is_numeric($longitude))
            {
                return array(
                    'status' => 'error',
                    'error' => 'Invalid longitude value.',
                );
            }
            if (! $this->_in_range($min_lat, $max_lat, $latitude)) 
            {
                return array(
                    'status' => 'error',
                    'error' => 'Invalid latitude value.',
                );
            }
            if (! $this->_in_range($min_lng, $max_lng, $longitude))
            {
                return array(
                    'status' => 'error',
                    'error' => 'Invalid longitude value.',
                );
            }
        }
        return array(
            'status' => 'OK',
        );

    }

    private function _sendRequest($post_data)
    {

        $data = array();
        foreach ($post_data as $idx => $val)
        {
            $data[$idx] = $val;
        }

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => 'https://maps.googleapis.com/maps/api/directions/json?' . http_build_query($data),
        ));
        $resp_json = curl_exec($curl);

        // Close request to clear up some resources
        curl_close($curl);
        $resp = json_decode($resp_json, true);
        if ($resp['status'] != 'OK')
        {
            return $resp['status'];
        }

        foreach ($resp['routes'] as $route)
        {
            $distance = 0;
            $duration = 0;
            $legs = array();
            foreach ($route['legs'] as $leg)
            {
                $distance += $leg['distance']['value'];
                $duration += $leg['duration']['value'];
                $legs[] = $leg;
            }
            $this->routes[] = array(
                'distance' => $distance,
                'duration' => $duration,
                'legs' => $legs,
            );
        }
        return $resp['status'];

    }

    private function _choose_shortest_route() 
    {
        $shortest_distance = -1;
        $shortest_id = -1;
        foreach ($this->routes as $idx => $route)
        {
            if (($shortest_distance == -1) || ($shortest_distance > $route['distance']))
            {
                $shortest_distance = $route['distance'];
                $shortest_id = $idx;
            }
        }
        return $shortest_id;
    }

    public function _generate_token () 
    {
        $token = md5(uniqid(mt_rand(), true));
        $this->token = sprintf ("%s-%s-%s-%s-%s", substr($token, 0, 8), substr($token, 8, 4), substr($token, 12, 4), substr($token, 16, 4), substr($token, 20));
        return $this->token;
    }

    public function _check_route_file_exist()
    {

        $route_file = $this->CI->config->item('routes_path') . $this->token;
        if (! file_exists($route_file))
        {
            return false;
        }
        return true;

    }

    public function _create_route_file($path) 
    {

        $route_file = $this->CI->config->item('routes_path') . $this->token;

        $data = array (
            'path' => $path,
            'response_status' => 'pending',
            'response_message' => '',
            'retried' => -1,
            'shortest_route' => NULL,
            'created' => date("Y-m-d H:i:s"),
            'modified' => date("Y-m-d H:i:s"),
        );

        $fp = fopen ($route_file, "w");
        fwrite ($fp, json_encode($data, JSON_UNESCAPED_UNICODE));
        fclose ($fp);

    }

    public function _read_route_file()
    {

        $route_file = $this->CI->config->item('routes_path') . $this->token;

        $fp = fopen ($route_file, "rb");
        $content_json = fread ($fp, filesize($route_file));
        fclose ($fp);
        $result = array();
        $content = json_decode($content_json, true);
        if (empty ($content))
        {
            return $result;
        }
        foreach ($this->valid_field as $key)
        {
            if (! in_array ($key, $content))
            {
                return array();
            }
            $result[$key] = $content[$key];
        }

        $result['created'] = $content['created'];
        $result['modified'] = $content['modified'];
        return $result;

    }

    public function _update_route_file($data)
    {

        $route_file = $this->CI->config->item('routes_path') . $this->token;

        $fp = fopen ($route_file, "rb");
        $content_json = fread ($fp, filesize($route_file));
        fclose ($fp);

        $content = json_decode($content_json, true);
        foreach ($data as $key => $item)
        {
            if (in_array($key, $this->valid_field))
            {
                $content[$key] = $item;
            }
        }

        $content['modified'] = date("Y-m-d H:i:s");

        $fp = fopen ($route_file, "w");
        fwrite ($fp, json_encode($content, JSON_UNESCAPED_UNICODE));
        fclose ($fp);

    }

    public function _set_post_data ($path)
    {

        $num = count ($path);
        $origin = $path[0][0] . "," . $path[0][1];
        $this->post_data = array();
        for ($i = 1; $i < $num; $i++)
        {
            $destination = $path[$i][0] . "," . $path[$i][1];
            $waypoints = array();
            for ($j = 1; $j < $num; $j++)
            {
                if ($j != $i)
                {
                    $waypoints[] = $path[$j][0] . "," . $path[$j][1];
                }
            }
            $data = array (
                'key' => $this->CI->config->item('google_api_key'),
                'mode' => 'driving',
                'units' => 'metric',
                'alternatives' => true,
                'origin' => $origin,
                'destination' => $destination,
            );
            if (count ($waypoints) > 0)
            {
                $data['waypoints'] = implode("|", $waypoints);
            }
            $this->post_data[] = $data;
        }

    }

    public function _calculate() 
    {

        $content = $this->_read_route_file();
        $data = array (
            'retried' => $content['retried'] + 1,
        );
        $this->_update_route_file ($data);

        $this->routes = array();
        foreach ($this->post_data as $post_data)
        {
            $result = $this->_sendRequest($post_data);
            if ($result != 'OK')
            {
                $data = array(
                    'response_status' => 'fail',
                    'response_message' => 'Google API return error',
                );
                $this->_update_route_file ($data);
                return false;
            }
        }
        if (count($this->routes) == 0)
        {
            $data = array(
                'response_status' => 'fail',
                'response_message' => 'No route found',
            );
            $this->_update_route_file ($data);
            return false;
        }
        $route_id = $this->_choose_shortest_route();
        $data = array(
            'response_status' => 'completed',
            'response_message' => '',
            'shortest_route' => $this->routes[$route_id],
        );
        $this->_update_route_file ($data);
        return true;

    }

}