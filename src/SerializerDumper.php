<?php
/**
 * @see  https://github.com/bd808/php-unserialize-js/blob/master/phpUnserialize.js
 */
class SerializerDumper
{
    
    /**
     * Working buffer
     * @var string
     */
    protected $string;


    /**
     * @var boolean
     */
    protected $displayVisibility = false;
    

    
    /**
     * @param  array $data 
     * @return object
     */
    public function flatten($originalObject)
    {
        $data = $originalObject;

        @array_walk_recursive($data, function (&$val, $key) {
            if (is_object($val) && $val instanceof Closure) {
                $val = array(
                    "@type" => "object:".get_class($val)."@0",
                );
            }
            if (is_resource($val)) {
                if (get_resource_type($val) === 'stream') {
                    $val = array_merge(array(
                        "@type" => "resource:".get_resource_type($val),
                    ), @stream_get_meta_data($val));
                } else {
                    $val = array(
                        "@type" => "resource:".get_resource_type($val),
                    );
                }
            }
        });

        $this->string   = serialize($data);
        $this->idx      = 0;
        $this->refStack = array();
        $this->ridx     = 1;
        
        return $this->parseNext();
    }
    
    protected function charCodeAt($index)
    {
        $char = mb_substr($this->string, $index, 1, 'UTF-8');
        if (mb_check_encoding($char, 'UTF-8')) {
            $ret = mb_convert_encoding($char, 'UTF-32BE', 'UTF-8');
            return hexdec(bin2hex($ret));
        } else {
            return null;
        }
    }
    
    
    protected function indexOf($char, $idx)
    {
        return strpos($this->string, $char, $idx);
    }
    
    protected function substr($idx, $del)
    {
        return substr($this->string, $idx, $del);
    }
        
    protected function readLength()
    {
        $del       = $this->indexOf(':', $this->idx);
        $val       = $this->substr($this->idx, $del - $this->idx);
        $this->idx = $del + 2;
        return intval($val);
    } //end $this->readLength
    
    protected function readInt()
    {
        $del       = $this->indexOf(';', $this->idx);
        $val       = $this->substr($this->idx, $del);
        $this->idx = $del + 1;
        return (int) $val;
    } //end $this->readInt
    
    protected function parseAsInt()
    {
        $val                           = $this->readInt();
        $this->refStack[$this->ridx++] = $val;
        return $val;
    }
    
    protected function parseAsFloat()
    {
        $del                           = $this->indexOf(';', $this->idx);
        $val                           = $this->substr($this->idx, $del);
        $this->idx                     = $del + 1;
        $val                           = $this->parseFloat($val);
        $this->refStack[$this->ridx++] = $val;
        return $val;
    } //end parseAsFloat
    
    protected function parseAsBoolean()
    {
        $del                           = $this->indexOf(';', $this->idx);
        $val                           = $this->substr($this->idx, $del);
        $this->idx                     = $del + 1;
        $val                           = ("1" === $val) ? true : false;
        $this->refStack[$this->ridx++] = $val;
        return $val;
    } //end parseAsBoolean
    
    protected function readString()
    {
        $len    = $this->readLength();

        $val = $this->substr($this->idx, $len);
        $this->idx += $len + 2;

        return $val;


        $utfLen = 0;
        $bytes  = 0;

        while ($bytes < $len) {
            $ch = $this->charCodeAt($this->idx + $utfLen++);
            if ($ch <= 0x007F) {
                $bytes++;
            } else if ($ch > 0x07FF) {
                $bytes += 3;
            } else {
                $bytes += 2;
            }
        }
        $val = $this->substr($this->idx, $this->idx + $utfLen);
        $this->idx += $utfLen + 2;
        return $val;
    } //end $this->readString
    
    protected function parseAsString()
    {
        $val                           = $this->readString();
        $this->refStack[$this->ridx++] = $val;
        return $val;
    }
    
    protected function readType()
    {
        $type = $this->string[$this->idx];
        $this->idx += 2;
        return $type;
    }
    
    protected function readKey()
    {
        $type = $this->readType();

        switch ($type) {
            case 'i':
                $key = $this->readInt();
                break;
            case 's':
                $key = $this->readString();
                break;
            default:
                throw new SerializerDumperException("Parse Error : Unknown key type '" . $type . "' at position " . ($this->idx - 2));
                break;
        }
        return $key;
    }
    
    protected function parseAsArray()
    {
        $len         = $this->readLength();
        $resultArray = array();
        $resultHash  = array();
        $keep        = $resultArray;
        $lref        = $this->ridx++;
        
        $resultArray["@type"] = "array@".$this->ridx;

        $this->refStack[$this->ridx++] = $keep;

        for ($i = 0; $i < $len; $i++) {
            $key = $this->readKey();
            $val = $this->parseNext();
            $resultArray[$key] = $val;
        }
        
        $this->idx++;
        return $resultArray;
    }

    /**
     * Fix property regarding the <NUL> char separator
     * 
     * @param  string $parsedName
     * @param  string $baseClassName 
     * @return string property name
     */
    protected function fixPropertyName($parsedName, $baseClassName)
    {
        if (chr(0) === $parsedName[0] && strpos($parsedName, chr(0), 1)) {

            // <NUL>*<NUL>name => protected
            // <NUL>class<NUL>name => private 
            $pos = strpos($parsedName, chr(0), 1);

            $className = substr($parsedName, 1, $pos - 1);
            $propName  = substr($parsedName, $pos + 1);

            if (strpos($parsedName, chr(0)."*".chr(0)) === 0) {
                // protected
                return "protected:$propName";
            } else if ($baseClassName === $className) {
                // own private
                return "private:$propName";
            } else {
                // private of a descendant
                return "parent:$propName";
                
                // On the one hand, we need to prefix property name with
                // class name, because parent and child classes both may
                // have private property with same name. We don't want
                // just to overwrite it and lose something.
                //
                // On the other hand, property name can be "foo::bar"
                //
                //     $obj = new stdClass();
                //     $obj->{"foo::bar"} = 42;
                //     // any user-defined class can do this by default
                //
                // and such property also can overwrite something.
                //
                // So, we can to lose something in any way.
            }
        } else {
            // public "property"
            return $parsedName;
        }
    }
    
    protected function parseAsObject()
    {
        $lref      = $this->ridx++;
        // HACK last char after closing quote is ':',
        // but not ';' as for normal string
        $clazzname = $this->readString();
        $obj       = array(
            "@type" => $clazzname."@".$this->ridx,
        );
        
        $this->refStack[$lref] = $obj;
        $len                   = $this->readLength();
        for ($i = 0; $i < $len; $i++) {
            $key       = $this->fixPropertyName($this->readKey(), $clazzname);
            $val       = $this->parseNext();
            $obj[$key] = $val;
        }
        
        $this->idx++;
        return $obj;
    } //end $this->parseAsObject
    
    protected function parseAsCustom()
    {
        $clazzname = $this->readString();
        $content   = $this->readString();
        return array(
            "@type" => $clazzname."@".$this->ridx,
            "serialized" => $content
        );
    } //end $this->parseAsCustom
    
    protected function parseAsRefValue()
    {
        $ref                           = $this->readInt();
        // php's ref counter is 1-based; our stack is 0-based.
        // $val                           = $this->refStack[$ref - 1];
        $this->refStack[$this->ridx++] = $ref;
        return [
            "@type" => "ref@" . $this->ridx,
        ];
    } //end $this->parseAsRefValue
    
    protected function parseAsRef()
    {
        $ref = $this->readInt();
        // php's ref counter is 1-based; our stack is 0-based.
        return $this->refStack[$ref - 1];
    }
    
    protected function parseAsNull()
    {
        $val                           = null;
        $this->refStack[$this->ridx++] = $val;
        return $val;
    } //end $this->parseAsNull
    
    
    /**
     * 
     */
    protected function parseNext()
    {
        $type = $this->readType();
        
        switch ($type) {
            case 'i':
                return $this->parseAsInt();
                break;
            case 'd':
                return $this->parseAsFloat();
                break;
            case 'b':
                return $this->parseAsBoolean();
                break;
            case 's':
                return $this->parseAsString();
                break;
            case 'a':
                return $this->parseAsArray();
                break;
            case 'O':
                return $this->parseAsObject();
                break;
            case 'C':
                return $this->parseAsCustom();
                break;
            
            // link to object, which is a $value - affects $this->refStack
            case 'r':
                return $this->parseAsRefValue();
                break;
            
            // PHP's reference - DOES NOT affect $this->refStack
            case 'R':
                return $this->parseAsRef();
                break;
            case 'N':
                return $this->parseAsNull();
                break;
            default:
                throw new SerializerDumperException("Parse Error: Unknown type '" . $type . "' at position " . ($this->idx - 2), 1);
                break;
        } //end switch
    }
    
}



class SerializerDumperException extends Exception {}