<?php

namespace PhpAbac\Manager;

use PhpAbac\Model\PolicyRule;
use PhpAbac\Model\PolicyRuleAttribute;

class PolicyRuleManager
{
    /** @var \PhpAbac\Manager\AttributeManager * */
    private $attributeManager;
    /** @var array * */
    private $rules;
	/** @var array **/
    public $ruleKeys;

    /**
     * @param \PhpAbac\Manager\AttributeManager $attributeManager
     * @param array $rules
     */
    public function __construct(AttributeManager $attributeManager, $rules)
    {
        $this->attributeManager = $attributeManager;
        $this->rules = $rules;
		$this->ruleKeys = array_keys($rules);
    }

    /**
     * @param string $ruleName
     * @param object $user
     * @param object $resource
     * @return PolicyRule[]
     * @throws \InvalidArgumentException
     */
     public function getRule($ruleName)
    {
        if(!isset($this->rules[$ruleName])) {
            throw new \InvalidArgumentException('The given rule "' . $ruleName . '" is not configured');
        }
        $rule =
            (new PolicyRule())
            ->setName($ruleName)
        ;
        // For each policy rule attribute, the data is formatted
        foreach($this->processRuleAttributes($this->rules[$ruleName]['attributes']) as $pra) {
            $rule->addPolicyRuleAttribute($pra);
        }
        return $rule;
    }

    /**
     * This method is meant to convert attribute data from array to formatted policy rule attribute
     *
     * @param array $attributes
     * @param object $user
     * @param object $resource
     */
   public function processRuleAttributes($attributes) {
        foreach($attributes as $attributeName => $attribute) {
            $pra = (new PolicyRuleAttribute())
                ->setAttribute($this->attributeManager->getAttribute($attributeName))
                ->setComparison($attribute['comparison'])
                ->setComparisonType($attribute['comparison_type'])
                ->setValue((isset($attribute['value'])) ? $attribute['value'] : null)
            ;
            // In the case the user configured more keys than the basic ones
            // it will be stored as extra data
            foreach($attribute as $key => $value) {
                if(!in_array($key, ['comparison', 'comparison_type', 'value'])) {
                    $pra->addExtraData($key, $value);
                }
            }
            // This generator avoid useless memory consumption instead of returning a whole array
            yield $pra;
        }
    }

    /**
     * This method is meant to set appropriated extra data to $pra depending on comparison type
     *
     * @param PolicyRuleAttribute $pra
     * @param object $user
     * @param object $resource
     */
    public function processRuleAttributeComparisonType(PolicyRuleAttribute $pra, $user, $resource)
    {
        switch ($pra->getComparisonType()) {
            case 'user':
                $pra->setExtraData(['user' => $user]);
                break;
            case 'object':
                $pra->setExtraData(['resource' => $resource]);
                break;
        }
    }
}
