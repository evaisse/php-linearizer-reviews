<?php


/*
    "O:3:"Foo":2:{s:8:"Foobar";a:4:{s:9:"isoString";s:3:"?t?";s:12:"binaryString";s:6:"4xVAB";s:10:"utf8String";s:5:"été";s:5:"fopen";i:0;}s:6:"*ref";r:1;}"
 */

include __DIR__ . '/plugins/autoload.php';

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

    protected $ref;


    public function setBar($i)
    {
        $this->bar = $i;
    }

    public function setRef(Foo $a) {
        $this->ref = $a;
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
    'goo' => 'gle',
);

$collection = &collector();
$collection[] = 2;
$collection[] = &$exit;
$collection[2]["huhu"] = 123;
$collection[2]["igrio"] = &collector();

$collection[3] = new stdclass();
$collection[3]->bla = "jkljjl";
$collection[3]->test = &$exit;
$collection[3]->bli  = "jkljjl";



// $c = new Foo();
// $c->setBar("été");

// $b = new Foo();
// $b->setBar(54687984354679876546465454646);
// $b->setRef($c);

// $a = new Foo();
// $a->setRef($b);
// $a->setBar(array(
//     'isoString'     => utf8_decode("été"),
//     'binaryString'  => pack("nvc*", 0x1234, 0x5678, 65, 66),
//     'utf8String'    => "été",
//     "fopen"         => fopen(__FILE__, 'r'),
//     'close'         => function ($r) use ($a) {},
//     "yoyoyoyoyoyo"          => $collection,
// ));

// $c->setRef($a);



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

$out = array();
$in = array("var" => $collection);
WalkArrayRecursive($in, $out);
var_dump($collection);
var_dump($out);

exit;

print t('Fluxprofiler');
$linearizer = new FluxProfilerLinearizer();
// var_dump($linearizer->isReference($collection, $collection[1]));
// exit;
var_dump($linearizer->flatten($collection));
// var_dump(json_encode($linearizer->flatten($a)));
print t();


// print t('serialized2json');
// $linearizer = new FluxProfilerLinearizer();
// var_dump($linearizer->flatten($a));
// print t();


// print t('Academe\SerializeParser\Parser');
// $parser = new \Academe\SerializeParser\Parser();
// $parsed = $parser->parse(serialize(["Test" => $a]));
// var_dump($parsed);
// print t();


// print t('Symfony\Component\VarDumper\VarDumper');
// $linearizer = new \Symfony\Component\VarDumper\VarDumper();
// $cloner = new \Symfony\Component\VarDumper\Cloner\VarCloner();
// $dumper = new Dumper();
// $linearizer->setHandler(function ($var) use ($cloner, $dumper) {
//     $dumper->dump($cloner->cloneVar($var));
// });
// $linearizer->dump($a);
// print t();

