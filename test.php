<?php


/*
    "O:3:"Foo":2:{s:8:"Foobar";a:4:{s:9:"isoString";s:3:"?t?";s:12:"binaryString";s:6:"4xVAB";s:10:"utf8String";s:5:"été";s:5:"fopen";i:0;}s:6:"*ref";r:1;}"
 */

include __DIR__ . '/generate.php';


print t('var_dump');
var_dump($sample);
print t();

print t('Using serialization parsing');
$linearizer = new Utils_Dumper();


@array_walk_recursive($sample, function (&$val, $key) {
    if (!is_scalar($val)) {
        $val = (array)$val;
    }
});

var_dump((array)$sample);

// print json_encode($linearizer->flatten(file_get_contents('/tmp/test.serialized')), JSON_PRETTY_PRINT);
// print json_encode($linearizer->flatten("couocuco"), JSON_PRETTY_PRINT);
// print json_encode($linearizer->flatten(null), JSON_PRETTY_PRINT);
print t();


var_dump(serialize(fopen(__FILE__, 'r')));