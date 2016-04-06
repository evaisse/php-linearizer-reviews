<?php


/*
    "O:3:"Foo":2:{s:8:"Foobar";a:4:{s:9:"isoString";s:3:"?t?";s:12:"binaryString";s:6:"4xVAB";s:10:"utf8String";s:5:"été";s:5:"fopen";i:0;}s:6:"*ref";r:1;}"
 */

include __DIR__ . '/vendor/autoload.php';

spl_autoload_register(function ($className) {

    $path = str_replace('_', '/', $className) . '.php';
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
  static $collection = array(1);
  return $collection;
}

$exit = array(
    'goo'       => 'glé',
    'fileres'   => fopen(__FILE__, 'r'),
);

$collection = &collector();
$collection[] = 2;
$collection[] = &$exit;
$collection[2]["huhu"] = 123;
$collection[2]["igrio"] = &collector();


$collection[2]["closureExample"] = function () {

};

$collection[3] = new stdclass();
$collection[3]->bla = "jkljjl";
$collection[3]->test = &$exit;
$collection[3]->bli  = "jkl\"i".chr(0)."jjl";
$collection[4]->foo = new Foo();


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
$in = array("var" => $collection);
WalkArrayRecursive($in, $out);


print t('var_dump');
var_dump($collection);
print t();


print t('Fluxprofiler');
$linearizer = new FluxProfilerLinearizer();
var_dump($linearizer->flatten($collection));
print t();



print t('Symfony\Component\VarDumper\VarDumper');
$linearizer = new \Symfony\Component\VarDumper\VarDumper();
$cloner = new \Symfony\Component\VarDumper\Cloner\VarCloner();
$dumper = new Dumper();
$linearizer->setHandler(function ($var) use ($cloner, $dumper) {
    $dumper->dump($cloner->cloneVar($var));
});
$linearizer->dump($collection);
print t();

// print t('Dumber');
// $linearizer = new Dumber();
// var_dump($linearizer->flatten($collection));
// print t();

print t('Using serialization parsing');
$linearizer = new SerializerDumper();
print_r($linearizer->flatten($collection));
print t();
/*
a:4:{i:0;i:1;i:1;i:2;i:2;a:3:{s:3:"goo";s:3:"gle";s:4:"huhu";i:123;s:5:"igrio";a:4:{i:0;i:1;i:1;i:2;i:2;R:4;i:3;O:8:"stdClass":3:{s:3:"bla";s:6:"jkljjl";s:4:"test";R:4;s:3:"bli";s:9:"jkl"ijjl";}}}i:3;r:10;}
 */


var_dump(serialize(fopen(__FILE__, 'r')));