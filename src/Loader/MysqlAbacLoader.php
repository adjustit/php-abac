<?php

namespace PhpAbac\Loader;

use Symfony\Component\Config\Loader\FileLoader;

class MysqlAbacLoader extends FileLoader
{
    
    private $CI;
    
    public function __construct($input) {
        parent::__construct($input);
        
        $this->CI =& get_instance();
        $this->CI->load->database('db_read');
    }
    
    public function load($resource, $type = null)
    {
		list($server, $resource) = explode(',', $resource);
		
		if($server == 1) {
			$result = $this->CI->db
				->select('policy')
				->where('server', $server)
				->where('resource', $resource)
				->get('abac_policy');
		}
		else {
			$result = $this->CI->db
				->select('policy')
				->where('server', $server)
				->like('controller', $resource, 'before')
				->get('abac_policy');
		}

        return $this->_parse_policy($result);
    }

    public function supports($resource, $type = null)
    {
        return is_string($resource);
    }
	
	private function _parse_policy($policy)
	{
		if($policy->num_rows() === 0) {
			$policy = $this->CI->db
				->select('policy')
				->where('id', 0)
				->get('abac_policy');
		}
		
		return json_decode($policy->row()->policy, true);
	}
}