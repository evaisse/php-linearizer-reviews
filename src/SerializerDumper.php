<?php
/**
 * @see  https://github.com/bd808/php-unserialize-js/blob/master/phpUnserialize.js
 */
class SerializerDumper
{
    
    
    protected $string;
    
        
    public function flatten($data)
    {
        $this->idx      = 0;
        $this->string   = serialize($data);
        $this->idx      = 0;
        $this->refStack = array();
        $this->ridx     = 0;
        while ($this->idx < strlen($this->string)) {
            $this->parseNext();
        }
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
        strpos($this->string, $char, $idx);
    }
    
    protected function substr($idx, $del)
    {
        substr($this->string, $idx, $del);
    }
        
    protected function readLength()
    {
        $del       = $this->indexOf(':', $this->idx);
        $val       = $this->substr($this->idx, $del);
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
        $utfLen = 0;
        $bytes  = 0;
        
        while ($bytes < $len) {
            $ch = $this->charCodeAt($this->idx + $this->utfLen++);
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
                return $this->readInt();
            case 's':
                return $this->readString();
            default:
                throw new SerializerDumperException("Parse Error : Unknown key type '" . $type . "' at position " . ($this->idx - 2));
        }
    }
    
    protected function parseAsArray()
    {
        $len         = $this->readLength();
        $resultArray = array();
        $resultHash  = array();
        $keep        = $resultArray;
        $lref        = $this->ridx++;
        
        $this->refStack[$lref] = $keep;
        for ($i = 0; $i < $len; $i++) {
            $key = $this->readKey();
            $val = $this->parseNext();
            if ($keep === $resultArray && (int) $key === $i) {
                // store in array version
                $resultArray . push(val);
            } else {
                if ($keep !== $resultHash) {
                    // found first non-sequential numeric key
                    // convert existing data to hash
                    for ($j = 0, $alen = count($resultArray); $j < $alen; $j++) {
                        $resultHash[$j] = $resultArray[$j];
                    }
                    $keep                  = $resultHash;
                    $this->refStack[$lref] = $keep;
                }
                $resultHash[$key] = $val;
            } //end if
        } //end for
        
        $this->idx++;
        return $keep;
    } //end parseAsArray
    
    protected function fixPropertyName($parsedName, $baseClassName)
    {
        
        
        if ("\u0000" === $parsedName[0]) {
            // "<NUL>*<NUL>property"
            // "<NUL>class<NUL>property"
            $pos = strpos($parsedName, "\u0000", 1);
            if ($pos > 0) {
                $class_name = substr($parsedName, 1, $pos);
                $prop_name  = $parsedName . substr($pos + 1);
                
                if ("*" === $class_name) {
                    // protected
                    return $prop_name;
                } else if ($baseClassName === $class_name) {
                    // own private
                    return $prop_name;
                } else {
                    // private of a descendant
                    return $class_name + "::" + $prop_name;
                    
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
            }
        } else {
            // public "property"
            return $parsedName;
        }
    }
    
    protected function parseAsObject()
    {
        $obj       = array();
        $lref      = $this->ridx++;
        // HACK last char after closing quote is ':',
        // but not ';' as for normal string
        $clazzname = $this->readString();
        
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
            "@class" => $clazzname,
            "serialized" => $content
        );
    } //end $this->parseAsCustom
    
    protected function parseAsRefValue()
    {
        $ref                           = $this->readInt();
        // php's ref counter is 1-based; our stack is 0-based.
        $val                           = $this->refStack[$ref - 1];
        $this->refStack[$this->ridx++] = $val;
        return $val;
    } //end $this->parseAsRefValue
    
    protected function parseAsRef()
    {
        $ref = $this->readInt();
        // php's ref counter is 1-based; our stack is 0-based.
        return $this->refStack[$ref - 1];
    } //end $this->parseAsRef
    
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
        var_dump($type);
        switch ($type) {
            case 'i':
                return $this->parseAsInt();
            case 'd':
                return $this->parseAsFloat();
            case 'b':
                return $this->parseAsBoolean();
            case 's':
                return $this->parseAsString();
            case 'a':
                return $this->parseAsArray();
            case 'O':
                return $this->parseAsObject();
            case 'C':
                return $this->parseAsCustom();
            
            // link to object, which is a $value - affects $this->refStack
            case 'r':
                return $this->parseAsRefValue();
            
            // PHP's reference - DOES NOT affect $this->refStack
            case 'R':
                return $this->parseAsRef();
            case 'N':
                return $this->parseAsNull();
            default:
                throw new SerializerDumperException("Parse Error: Unknown type '" . $type . "' at position " . ($this->idx - 2), 1);
        } //end switch
    }
    
}



class SerializerDumperException extends Exception {}