<?php

namespace PhpAbac\Model;

abstract class AbstractAttribute
{
    /** @var string **/
    protected $name;
    /** @var string **/
    protected $type;
    /** @var string **/
    protected $slug;
    /** @var mixed **/
    protected $value;

    /**
     * @param string $name
     *
     * @return \PhpAbac\Model\AbstractAttribute
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $type
     *
     * @return \PhpAbac\Model\PolicyRuleAttribute
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $slug
     *
     * @return \PhpAbac\Model\AbstractAttribute
     */
    public function setSlug($slug)
    {
        $this->slug = $slug;

        return $this;
    }

    /**
     * @return string
     */
    public function getSlug()
    {
        return $this->slug;
    }

    /**
     * @param mixed $value
     *
     * @return \PhpAbac\Model\Attribute
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }
}
