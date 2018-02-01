<?php

namespace PhpAbac;

use PhpAbac\Manager\AttributeManager;
use PhpAbac\Manager\PolicyRuleManager;
use PhpAbac\Manager\ConfigurationManager;
use PhpAbac\Manager\CacheManager;
use PhpAbac\Manager\ComparisonManager;

use Symfony\Component\Config\FileLocator;

use PhpAbac\Model\PolicyRuleAttribute;

class Abac
{
	public $codeVersion = '2.1.2.1-adjustit';	
    /** @var \PhpAbac\Manager\ConfigurationManager **/
    private $configuration;
    /** @var \PhpAbac\Manager\PolicyRuleManager **/
    private $policyRuleManager;
    /** @var \PhpAbac\Manager\AttributeManager **/
    private $attributeManager;
    /** @var \PhpAbac\Manager\CacheManager **/
    private $cacheManager;
    /** @var \PhpAbac\Manager\ComparisonManager **/
    private $comparisonManager;

    /**
     * @param array $configPaths
     */
    public function __construct($configPaths)
    {
        $this->configure($configPaths);
        $this->attributeManager = new AttributeManager($this->configuration->getAttributes());
        $this->policyRuleManager = new PolicyRuleManager($this->attributeManager, $this->configuration->getRules());
        $this->cacheManager = new CacheManager();
        $this->comparisonManager = new ComparisonManager($this->attributeManager);
    }
    
    /**
     * @param array $configPaths
     */
    public function configure($configPaths) {
        $locator = new FileLocator($configPaths);
        $this->configuration = new ConfigurationManager($locator);
        $this->configuration->parseConfigurationFile($configPaths);
    }

    /**
     * Return true if both user and object respects all the rules conditions
     * If the objectId is null, policy rules about its attributes will be ignored
     * In case of mismatch between attributes and expected values,
     * an array with the concerned attributes slugs will be returned.
     * 
     * Available options are :
     * * dynamic_attributes: array
     * * cache_result: boolean
     * * cache_ttl: integer
     * * cache_driver: string
     * 
     * Available cache drivers are :
     * * memory
     * 
     * @param string $ruleName
     * @param object $user
     * @param object $resource
     * @param array $options
     * @return boolean|array
	 *
	 * ** AND/OR Logical Operand **
	 * @lastmodifiedDate 2016-10-20
	 *  
	 * Assesses internally if there is a comparison_operand parameter in $extraData.
	 * Available comparison operand options are :
	 * * and
	 * * or
	 *
	 * This operand flag functions as a logical and/or escape. The default is to 
	 * compound individual comparisons as a logical and, ie. comparisons are 
	 * additional to each previous comparison. The logical or escapes the enforce
	 * method as TRUE once unsetting the applicable rejected attributes from the 
	 * Comparison Manager via unsetRejected() once one of the assessed attributes
	 * returns a TRUE evaluation.
     */
    public function enforce($ruleName, $user, $resource = null, $options = []) {
        // If there is dynamic attributes, we pass them to the comparison manager
        // When a comparison will be performed, the passed values will be retrieved and used
        if(isset($options['dynamic_attributes'])) {
            $this->comparisonManager->setDynamicAttributes($options['dynamic_attributes']);
        }
        // Retrieve cache value for the current rule and values if cache item is valid
        if(($cacheResult = isset($options['cache_result']) && $options['cache_result'] === true) === true) {
            $cacheItem = $this->cacheManager->getItem(
                "$ruleName-{$user->getId()}-" . (($resource !== null) ? $resource->getId() : ''),
                (isset($options['cache_driver'])) ? $options['cache_driver'] : null,
                (isset($options['cache_ttl'])) ? $options['cache_ttl'] : null
            );
            // We check if the cache value s valid before returning it
            if(($cacheValue = $cacheItem->get()) !== null) {
                return $cacheValue;
            }
        }
        $policyRule_a = $this->policyRuleManager->getRule( $ruleName, $user, $resource );				
		
		foreach ($policyRule_a->getPolicyRuleAttributes() as $pra ) {

			$attribute = $pra->getAttribute();

			$getter_params = $this->prepareGetterParams($pra->getGetterParams(), $user, $resource);
			$attribute->setValue( $this->attributeManager->retrieveAttribute( $attribute, $user, $resource, $getter_params ) );				

            # PHP7.2 - count(): Parameter must be an array or an object that implements Countable
            # NOTE: https://github.com/yiisoft/yii/issues/4167
            # NOTE: https://wiki.php.net/rfc/counting_non_countables
            if($pra->getExtraData() !== NULL){ # Minor fix
                if(count($pra->getExtraData()) > 0) {
                    $this->processExtraData($pra, $user, $resource);
                }
            }

			// Get the extra data attributes for a rule
			$extraData = $pra->getExtraData();			

			// Assess if comparison_operand exists and if it evaluates bitwise to 'or'
			if(isset($extraData['comparison_operand']) && $extraData['comparison_operand'] === 'or') {
				$comparisonTest = $this->comparisonManager->compare($pra);				

				// If the comparison test returns TRUE, unset the rejected elements and 
				// exit the loop by returning TRUE
				if($comparisonTest === TRUE) {
					$this->comparisonManager->unsetRejected();

					if($cacheResult) {
						$cacheItem->set(TRUE);
						$this->cacheManager->save($cacheItem);
					}

					return TRUE;
				}				
			}
			else { // Otherwise continue as normal in compounding rules
				$this->comparisonManager->compare($pra);
			}
		}
        // The given result could be an array of rejected attributes or true
        // True means that the rule is correctly enforced for the given user and resource
        $result = $this->comparisonManager->getResult();
		
		if($cacheResult) {
            $cacheItem->set($result);
            $this->cacheManager->save($cacheItem);
        }
        return $result;
    }
    
    /**
     * @param \PhpAbac\Model\PolicyRuleAttribute $pra
     * @param object $user
     * @param object $resource
     */
    public function processExtraData(PolicyRuleAttribute $pra, $user, $resource) {
        foreach($pra->getExtraData() as $key => $data) {
            switch($key) {
                case 'with':
                    // This data has to be removed for it will be stored elsewhere
                    // in the policy rule attribute
                    $pra->removeExtraData('with');
                    // The "with" extra data is an array of attributes, which are objects
                    // Once we process it as policy rule attributes, we set it as the main policy rule attribute value
                    $subPolicyRuleAttributes = [];
					$extraData               = [];
					
                    foreach ( $this->policyRuleManager->processRuleAttributes( $data, $user, $resource ) as $subPolicyRuleAttribute ) {
						$subPolicyRuleAttributes[] = $subPolicyRuleAttribute;
					}
					
                    $pra->setValue($subPolicyRuleAttributes);
                    // This data can be used in complex comparisons
                    $pra->addExtraData('attribute', $pra->getAttribute());
                    $pra->addExtraData('user', $user);
                    $pra->addExtraData('resource', $resource);
                    break;
            }
        }
    }
	
	/**
	 * Return a world-accessible list of rule keys 
	 * @return array
	 */
	public function getPolicyRuleManagerRuleKeys() {
		return $this->policyRuleManager->ruleKeys;
	}
	
	/**
	 * Function to prepare Getter Params when getter require parameters ( this parameters must be specified in configuration file)
	 *
	 * @param $getter_params
	 * @param $user
	 * @param $resource
	 *
	 * @return array
	 */
	private function prepareGetterParams($getter_params, $user, $resource) {
		if (empty($getter_params)) return [];
		$values = [];
		foreach($getter_params as $getter_name=>$params) {
			foreach($params as $param) {
				if ( '@' !== $param[ 'param_name' ][ 0 ] ) {
					$values[$getter_name][] = $param[ 'param_value' ];
				}
				else {
					$values[$getter_name][] = $this->attributeManager->retrieveAttribute( $this->attributeManager->getAttribute( $param[ 'param_value' ] ) , $user, $resource );
				}
			}
		}
		return $values;
	}
	
	/**
	 * @param \PhpAbac\Model\PolicyRuleAttribute $pra
	 * @param object                             $user
	 * @param object                             $resource
	 */
	
}
