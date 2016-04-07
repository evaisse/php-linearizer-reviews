<?php


include __DIR__ . '/generate.php';



use Symfony\Component\VarDumper\VarDumper;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Cloner\Stub;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;



VarDumper::setHandler(function ($var) {
    $cloner = new VarCloner();
    $dumper = 'cli' === PHP_SAPI ? new CliDumper() : new HtmlDumper();

    $dumper->dump($cloner->cloneVar($var));
});

$cloner = new VarCloner();




$data = $cloner->cloneVar($sample);


array_walk_recursive($data->getRawData(), function (&$val, $key) {

    if ($val instanceof Stub) {
        $val = $val->value;
    }

});



var_dump($data->getRawData());

print_r($sample);

// $dumper = new CliDumper();
// $dumper->dump($cloner->cloneVar($sample));

// var_dump($data);