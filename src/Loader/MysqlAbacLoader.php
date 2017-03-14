<?php

namespace PhpAbac\Loader;

use Symfony\Component\Config\Loader\FileLoader;

/**
 * @description A CodeIgniter-based handler for loading ABAC policies from MySQL instead of YAML
 * @requires CodeIgniter >= 2.2.x
 * @requires MySQL DB `x`.`abac_policy`
 * @author benjamin.alldridge <ben.alldridge@aamcommercial.com.au>
 * @link http://github.com/adjustit/php-abac
 */

class MysqlAbacLoader extends AbacLoader
{
    
    private $CI;
	
	protected static $_EXTENSION_ALLOWED_A = [''];
    
    /**
     * build our ABAC MySQL object up via Symfony FileLoader
     * @private
     * @param [object] $input
     */
    public function __construct($input)
	{
        parent::__construct($input);
        
        $this->CI =& get_instance();
        $this->CI->load->database('db_read');
    }
    
    /**
     * handle our MySQL ABAC policy recall
     * @param  [string] $resource      the resource name coupled with resource location
     *                                 accepts <location>,<resource> format                                        
     * @param  [string] [$type         = null]
     * @return [object] the policy contents
     */
    public function load($resource, $type = null)
    {
		list($server, $resource) = explode(',', $resource);
		
		$result = $this->CI->db
			->select('policy')
			->where('server', $server)
			->where('resource', $resource)
			->get('abac_policy');

        return $this->_parse_policy($result);
    }

    public function supports($resource, $type = null)
    {
        return is_string($resource);
    }
	
	/**
	 * handle the policy to either be the valid policy or a default-false policy
	 * @private
	 * @param  [object] $policy the raw MySQL object returned by CI AR
	 * @return [array] a JSON-decoded array representation of the policy
	 */
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