<?php
/**
 * Clone any var (even most complex ones) into a simpler representation with array & scalar only, 
 * removing references, objects & resources and other complex types
 * 
 * @see  https://github.com/bd808/php-unserialize-js/blob/master/phpUnserialize.js
 * @author evaisse
 */
class Utils_Dumper
{
    /**
     * Flatten a given var into a simpler definition, without object & references, 
     * only scalars and arrays
     * 
     * @param  array $data 
     * @return object
     */
    public function flatten($originalObject)
    {
        $data = array($originalObject);

        @array_walk_recursive($sample, function (&$val, $key) {
            if (!is_scalar($val)) {
                $val = (array)$val;
            }
        });

        return empty($data) ? null : $data[0];
    }

}
