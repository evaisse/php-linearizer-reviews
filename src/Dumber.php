<?php
/**
 * A basic linearizer implementation using array casting as internal method to flatten objects
 * avoid recursion & output buffering
 * Handle closure, resources, streams.
 *
 * Foo { private $bar = 1 } => {"@type" => "Foo", "private:bar" => 1}
 *                          => without visibility => {"@type" => "Foo", "bar":1}
 *                          => without type hints => {"bar":1}
 * 
 * @author evaisse
 */
class Dumber
{
    const CLASS_IDENTIFIER_KEY = '@type';


    public $depthLimiter = 0;

    /**
     * linearize the value in JSON
     *
     * @param mixed $value
     * @return array
     */
    public function flatten($value) 
    {
        $this->reset();
        return $this->linearizeData($value);
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
        if (++$this->depthLimiter > 1000) {
            return null;
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }
        
        if (is_array($value)) {
            return array_map(array($this, __FUNCTION__), $value);
        }

        return $this->linearizeObject($value);
    }

    /**
     * Extract the data from an object
     *
     * @param object $value
     * @return array
     */
    protected function linearizeObject($value) 
    {
        if (is_object($value) && method_exists($value, 'jsonSerialize')) {
            return $this->linearizeData($value->jsonSerialize());
        }

        return $this->redrawObjectDefinition($value);
    }


    /**
     * Helper method that fix the array definition of an object by replacing 
     * keys * with protected: & private: info
     * @param  array  $input input array
     * @return array 
     */
    protected function redrawObjectDefinition($input) 
    {
        $return = array();

        if (is_object($input)) {
            $return['@class'] = get_class($input);
            $input = (array)$input;
        }

        foreach ($input as $key => $value) {

            if (is_string($key) && strpos($key, chr(0).'*'.chr(0)) !== false) {
                $key = str_replace(chr(0).'*'.chr(0), "", $key) . ":protected";
            }

            if (is_string($key) && strpos($key, chr(0)) !== false) {
                $key = explode(chr(0), $key, 3);
                $key = $key[2] . ":private";
            }

            if (is_resource($value)) {
                if (get_resource_type($value) === 'stream') {
                    return array_merge(array(
                        "@resource" => get_resource_type($value),
                    ), @stream_get_meta_data($value));
                } else {
                    return array(
                        "@resource" => get_resource_type($value),
                    );
                }
            }

            if (!is_scalar($value) && $value !== null) {
                $value = $this->redrawObjectDefinition($value);
            }

            $return[$key] = $value;
        }
        return $return;
    }

    /**
     * Reset variables
     *
     * @return void
     */
    protected function reset() 
    {
        // nothing there
    }
}