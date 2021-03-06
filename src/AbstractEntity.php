<?php

namespace clagiordano\weblibs\dbabstraction;

use InvalidArgumentException;

/**
 * Class AbstractEntity
 *
 * @package clagiordano\weblibs\dbabstraction
 */
abstract class AbstractEntity
{
    protected $values = [];
    protected $allowedFields = [];

    /**
     * Constructor
     * @param array $fields
     */
    public function __construct(array $fields)
    {
        foreach ($fields as $name => $value) {
            $this->$name = $value;
        }
    }

    /**
     * Assign a value to the specified field via the corresponding mutator (if it exists);
     * otherwise, assign the value directly to the '$_values' array
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        if (!in_array($name, $this->allowedFields)) {
            throw new \InvalidArgumentException(
                "Setting the field '$name' is not allowed for this entity."
            );
        }
        
        $mutator = 'set' . ucfirst($name);
        if (method_exists($this, $mutator) && is_callable([$this, $mutator])) {
            $this->$mutator($value);
        } else {
            $this->values[$name] = $value;
        }
    }

    /**
     * Get the value of the specified field (via the getter if it exists or by getting it from the $_values array)
     * @param $name
     * @return
     */
    public function __get($name)
    {
        if (!in_array($name, $this->allowedFields)) {
            throw new \InvalidArgumentException(
                "Getting the field '$name' is not allowed for this entity."
            );
        }

        $accessor = 'get' . ucfirst($name);
        if (method_exists($this, $accessor) && is_callable([$this, $accessor])) {
            return $this->$accessor;
        }

        if (isset($this->values[$name])) {
            return $this->values[$name];
        }

        throw new \InvalidArgumentException(
            "The field '$name' has not been set for this entity yet."
        );
    }

    /**
     * Check if the specified field has been assigned to the entity
     * @param $name
     * @return bool
     */
    public function __isset($name)
    {
        if (!in_array($name, $this->allowedFields)) {
            throw new \InvalidArgumentException(
                "The field '$name' is not allowed for this entity."
            );
        }
        
        return isset($this->values[$name]);
    }

    /**
     * Unset the specified field from the entity
     * @param $name
     * @return bool
     */
    public function __unset($name)
    {
        if (!in_array($name, $this->allowedFields)) {
            throw new \InvalidArgumentException(
                "Unsetting the field '$name' is not allowed for this entity."
            );
        }

        if (isset($this->values[$name])) {
            unset($this->values[$name]);
            return true;
        }

        throw new \InvalidArgumentException(
            "The field '$name' has not been set for this entity yet."
        );
    }

    /**
     * Get an associative array with the values assigned to the fields of the entity
     */
    public function toArray()
    {
        return $this->values;
    }
}
