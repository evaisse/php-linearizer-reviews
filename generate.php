<?php

include __DIR__ . '/vendor/autoload.php';

spl_autoload_register(function ($className) {

    $path = $className . '.php';
    $path = str_replace('\\', '/', $path);

    if (file_exists(__DIR__ . '/src/' . $path)) {
        include __DIR__ . '/src/' . $path;
    }

});

function t($title = null)
{
    static $t;


    if ($t) {
        $out = round((microtime(true) - $t) * 1000, 4) . 'ms';
        $t = null;
        return "\n------\n$out \n------\n\n";
    } else {
        $t = microtime(true);
        return "$title\n============\n";
    }
}


class Foo
{
    private $bar = 666;

    protected $foo;


    public function setBar($i)
    {
        $this->bar = $i;
    }

    public function setRef(Foo $a) {
        $this->foo = $a;
    }
}


function phpserialized2json($data)
{
    $s = serialize($data);
    $ref = [];

    return $ref;
}

function &collector() {
  static $sample = array(1);
  return $sample;
}

$exit = array(
    'goo'       => 'glé',
    'fileres'   => fopen(__FILE__, 'r'),
);

$sample = &collector();
$sample[] = 2;
$sample[] = &$exit;
$sample[2]["huhu"] = 123;
$sample[2]["igrio"] = &collector();


$sample[2]["closureExample"] = function () {

};

$sample[3] = new stdclass();
$sample[3]->bla = "jkljjl";
$sample[3]->test = &$exit;
$sample[3]->bli  = "jkl\"i".chr(0)."jjl";
$sample[4] = new stdclass();
$sample[4]->foo = new Foo();


if (class_exists('SoapClient')) {
    $soap = $sample[4]->soap = new SoapClient('http://www.webservicex.net/geoipservice.asmx?WSDL');
    try {
        $sample[4]->soapRequest = $soap->__soapCall("GetGeoIPSoapIn", [
            "IPAddress" => "31.187.70.14",
        ]);
    } catch (Exception $e) {
        // $sample[4]->soapError = $e;
    }

}


function isReference(&$xVal,&$yVal) 
{
    $isReference = false;
    $temp = $xVal;
    $t = ($yVal === "I am a reference") ? "I'm a tricky reference" : "I am a reference";
    $xVal = $t;
    if ($yVal === $t) $isReference = true;
    $xVal = $temp;
    return $isReference;
}

function WalkArrayRecursive(&$val, &$copy, array &$temp = array())
{
    if (!is_array($val) && !is_object($val)) {
        $copy = $val;
        return;
    }

    $copy = array();
    $temp[] = &$val;

    foreach ($val as $k => &$v)
    {
        $isRef = false;
        if (!is_array($v) && !is_object($v)) {
            $copy[$k] = $v;
            continue;
        }


        foreach ($temp as &$p) {
            if (isReference($v, $p)) {
                $copy[$k] = "**RECURSION**";
                $isRef = true;
            }
        }
        
        if ($isRef) {
            continue;
        }

        // var_dump(["tmp" => $temp]);
        WalkArrayRecursive($v, $copy[$k], $temp);
    }
}



function test($title, $data, $test) {

    print t($title);
    $test($data);
    print t() . PHP_EOL . PHP_EOL;
}


$out = array();
$in = array("var" => $sample);
WalkArrayRecursive($in, $out);
