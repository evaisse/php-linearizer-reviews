<?php
/**
 * A linearizer based on reflection classes, 
 * avoid recursion & output buffering
 *
 * Handle closure, resources, streams.
 * 
 * @author evaisse
 */
class FluxProfilerLinearizer
{
    const CLASS_IDENTIFIER_KEY = '@type';

    /**
     * Storage for object
     *
     * Used for recursion
     *
     * @var SplObjectStorage
     */
    protected $objectStorage;

    /**
     * Object mapping for recursion
     *
     * @var array
     */
    protected $objectMapping = array();

    /**
     * Object mapping index
     *
     * @var integer
     */
    protected $objectMappingIndex = 0;

    /**
     * @var boolean $hasPropertiesVisibility toggle properties visibility has prefix for properties names, {"@type" => "Foo", "private:bar"}
     */
    protected $hasPropertiesVisibility = false;

    /**
     * @param  bool $value 
     */
    public function setPropertiesVisibility($value)
    {
        $this->hasPropertiesVisibility = !!$value;
    }


    /**
     * linearize the value in JSON
     *
     * @param mixed $value
     * @return array
     */
    public function flatten($value) {
        $this->reset();
        return $this->linearizeData($value);
    }



    protected function removeInvalidUtf8Chars($text) 
    {
$regex = <<<'END'
/
  (
    (?: [\x00-\x7F]               # single-byte sequences   0xxxxxxx
    |   [\xC0-\xDF][\x80-\xBF]    # double-byte sequences   110xxxxx 10xxxxxx
    |   [\xE0-\xEF][\x80-\xBF]{2} # triple-byte sequences   1110xxxx 10xxxxxx * 2
    |   [\xF0-\xF7][\x80-\xBF]{3} # quadruple-byte sequence 11110xxx 10xxxxxx * 3 
    ){1,100}                      # ...one or more times
  )
| ( [\x80-\xBF] )                 # invalid byte in range 10000000 - 10111111
| ( [\xC0-\xFF] )                 # invalid byte in range 11000000 - 11111111
/x
END;
        return preg_replace_callback($regex, array($this, "removeInvalidUtf8CharsCallback"), $text);
    }

    protected function removeInvalidUtf8CharsCallback($captures) {
          if ($captures[1] != "") {
            // Valid byte sequence. Return unmodified.
            return $captures[1];
          }
          elseif ($captures[2] != "") {
            // Invalid byte of the form 10xxxxxx.
            // Encode as 11000010 10xxxxxx.
            return "?";
          }
          else {
            // Invalid byte of the form 11xxxxxx.
            // Encode as 11000011 10xxxxxx.
            return "?";
          }
    }


    /**
     * [testReference description]
     * @param  [type] &$xVal [description]
     * @param  [type] &$yVal [description]
     * @return [type]        [description]
     */
    public function isReference(&$xVal,&$yVal) 
    {
        $isReference = false;
        $temp = $xVal;
        $t = ($yVal === "I am a reference") ? "I'm a tricky reference" : "I am a reference";
        $xVal = $t;
        if ($yVal == $t) $isReference = true;
        $xVal = $temp;
        return $isReference;
    }

    /**
     * Parse the data to be json encoded
     *
     * @param mixed $value
     * @return mixed
     * @throws Exception
     */
    protected function linearizeData($value)
    {
        if (is_string($value)) {
            if (@json_encode($value) === false) {
                $value = $this->removeInvalidUtf8Chars($value);
            }
            return $value;
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        if (is_resource($value)) {
            if (get_resource_type($value) === 'stream') {
                return array_merge(array(
                    "@type" => "resource:" . get_resource_type($value),
                ), @stream_get_meta_data($value));
            } else {
                return array(
                    "@type" => "resource:" . get_resource_type($value),
                );
            }
        }

        if (is_array($value)) {
            $this->walkArrayRecursive($value);
            return $value;
        }

        return $this->linearizeObject($value);
    }

    /**
     * [walkArrayRecursive description]
     * @param  [type] &$array_name [description]
     * @param  [type] &$temp       [description]
     * @return [type]              [description]
     */
    function walkArrayRecursive(&$arrayName, array &$parents = array())
    {
        if (is_array($arrayName)) {
            foreach ($arrayName as $k => &$v){
                foreach ($parents as &$value) {
                    $this->isReference($arrayName, $value);
                    $arrayName[$k] = "*RECURSION*";
                    continue;
                }
                $arrayName[$this->linearizeData($k)] = $this->linearizeData($v);
                $parents[] = $arrayName;
                $this->walkArrayRecursive($v, $parents);
            }
        }
    }

    /**
     * Extract the data from an object
     *
     * @param object $value
     * @return array
     */
    protected function linearizeObject($value) {

        if (is_object($value) && method_exists($value, 'jsonSerialize')) {
            return $this->linearizeData($value->jsonSerialize());
        }

        $ref = new ReflectionClass($value);

        if ($this->objectStorage->contains($value)) {
            return array(self::CLASS_IDENTIFIER_KEY => "#object:" . get_class($value) . '@' . $this->objectStorage[$value]);
        }

        $this->objectStorage->attach($value, $this->objectMappingIndex++);

        $paramsTolinearize = $this->getObjectProperties($ref, $value);
        $data = array( 
            self::CLASS_IDENTIFIER_KEY => "object:" . get_class($value) . '@' . $this->objectStorage[$value]
        );
        $data += array_map(array($this, 'linearizeData'), $this->extractObjectData($value, $ref, $paramsTolinearize));
        return $data;
    }

    /**
     * Return the list of properties to be linearized
     *
     * @param ReflectionClass $ref
     * @param object $value
     * @return array
     */
    protected function getObjectProperties($ref, $value) {
        if (method_exists($value, '__sleep')) {
            return $value->__sleep();
        }

        $props = array();
        $values = array();
        foreach ($ref->getProperties() as $prop) {
            $props[] = $prop->getName();
        }
        return array_unique(array_merge($props, array_keys(get_object_vars($value))));
    }

    /**
     * Extract the object data
     *
     * @param object $value
     * @param ReflectionClass $ref
     * @param array $properties
     * @return array
     */
    protected function extractObjectData($value, $ref, $properties) {

        $data = array();

        foreach ($properties as $property) {
            try {
                $pref = 'public:';
                
                $propRef = $ref->getProperty($property);
                

                if ($propRef->isProtected()) {
                    $pref = 'protected:';
                } else if ($propRef->isPrivate()) {
                    $pref = 'private:';
                }

                if ($propRef->isStatic()) {
                    $pref = '::' . $pref;
                }

                if (method_exists($propRef, 'setAccessible')) {
                    $propRef->setAccessible(true);
                }

                if ($this->hasPropertiesVisibility) {
                    $data[$pref . $property] = $propRef->getValue($value);
                } else {
                    $data[$property] = $propRef->getValue($value);
                }
            } catch (ReflectionException $e) {
                if ($this->hasPropertiesVisibility) {
                    $data[$pref . $property] = $value->$property;
                } else {
                    $data[$property] = $value->$property;
                }
            }
        }

        ksort($data);

        return $data;
    }

    /**
     * Reset variables
     *
     * @return void
     */
    protected function reset() 
    {
        $this->objectStorage = new SplObjectStorage();
        $this->objectMapping = array();
        $this->objectMappingIndex = 0;
    }

}