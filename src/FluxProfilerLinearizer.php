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
     * Array refr index mapping index
     *
     * @var integer
     */
    protected $arrayRefIndex = 0;

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
     * linearize the value as a flattened array
     *
     * @param mixed $value
     * @return array
     */
    public function flatten($value) 
    {
        $this->reset();
        $this->walkReferenceSafe($value, $out);
        return $out;
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
     * Iterate through objects & arrays while watching upon cylce references.
     * 
     * @param  mixed &$val  Input value
     * @param  mixed &$copy Output linearized clone
     * @param  array &$parents A reference dict, to detect deeply nested references
     */
    public function walkReferenceSafe(&$val, &$copy, array &$parents = array())
    {

        if (is_object($val)) {
            $this->linearizeObject($val, $copy, $parents);
            return;
        }

        if (is_array($val)) {
            $this->linearizeArray($val, $copy, $parents);
            return;
        }

        if (is_resource($val)) {
            var_dump($val);
            $this->linearizeResource($val, $copy, $parents);
            return;
        }

        $this->linearizeScalar($val, $copy, $parents);
    }

    /**
     * Parse the data to be json encoded
     *
     * @param mixed $value
     * @return mixed
     * @throws Exception
     */
    protected function linearizeScalar(&$value, &$copy, array &$parents = array())
    {
        if (is_string($value)) {
            if (@json_encode($value) === false) {
                $copy = $this->removeInvalidUtf8Chars($value);
            } else {
                $copy = $value;
            }
            return;
        }

        
        $copy = $value;

    }

    /**
     * Parse the data to be json encoded
     *
     * @param mixed $value
     * @return mixed
     * @throws Exception
     */
    protected function linearizeResource(&$value, &$copy, array &$parents = array())
    {
        if (get_resource_type($value) === 'stream') {
            $copy = array(
                "@type" => "resource:" . get_resource_type($value),
                'infos' => @stream_get_meta_data($value),
            );
        } else {
            $copy = array(
                "@type" => "resource:" . get_resource_type($value),
            );
        }
    }


    /**
     * [linearizeArray description]
     * @param  [type] &$val     [description]
     * @param  [type] &$copy    [description]
     * @param  array  &$parents [description]
     * @return [type]           [description]
     */
    public function linearizeArray(&$val, &$copy, array &$parents = array())
    {
        $copy = array(
            "@type" => "array@" . ++$this->arrayRefIndex,
        );

        $parents[$this->arrayRefIndex] = &$val;

        foreach ($val as $k => &$v) {

            $isRef = false;

            if (!is_array($v) && !is_object($v)) {
                $this->walkReferenceSafe($v, $copy[$k], $parents);
                continue;
            }

            foreach ($parents as $refK => &$p) {
                if ($this->isReference($v, $p)) {
                    $copy[$k] = "#array@$refK";
                    $isRef = true;
                }
            }
            
            if ($isRef) {
                continue;
            }

            $this->walkReferenceSafe($v, $copy[$k], $parents);
        }
    }


    /**
     * Extract the data from an object
     *
     * @param object $val
     * @param mixed $copy
     * @return array
     */
    protected function linearizeObject(&$val, &$copy, array &$parents = array()) {

        if (is_object($val) && method_exists($val, 'jsonSerialize')) {
            $this->walkReferenceSafe($val->jsonSerialize(), $copy);
            return;
        }

        $ref = new ReflectionClass($val);

        if ($this->objectStorage->contains($val)) {
            $copy = array(self::CLASS_IDENTIFIER_KEY => "#object:" . get_class($val) . '@' . $this->objectStorage[$val]);
            return;
        }

        $this->objectStorage->attach($val, $this->objectMappingIndex++);

        $paramsTolinearize = $this->getObjectProperties($ref, $val);


        $copy = array( 
            self::CLASS_IDENTIFIER_KEY => "object:" . get_class($val) . '@' . $this->objectStorage[$val]
        );

        foreach ($paramsTolinearize as $propertyName) {
            $this->extractProperty($val, $copy, $parents, $ref, $propertyName);
        }

        // return $data;
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
     * @param array $propertyName
     * @return array
     */
    protected function extractProperty(&$value, &$copy, array &$parents = array(), $ref, $propertyName) {

        try {
            $pref = 'public:';
            
            $propRef = $ref->getProperty($propertyName);

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

            $v = $propRef->getValue($value);
            $this->walkReferenceSafe($v, $copy[$propertyName], $parents);

        } catch (ReflectionException $e) {

            $this->walkReferenceSafe($value->$propertyName, $copy[$propertyName], $parents);
        }

        ksort($copy);
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
        $this->arrayRefIndex = 0;
    }

}