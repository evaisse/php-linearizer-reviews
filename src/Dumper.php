<?php

class Dumper extends \Symfony\Component\VarDumper\Dumper\AbstractDumper
{

    public $out = null;

    public function dumpScalar(\Symfony\Component\VarDumper\Cloner\Cursor $cursor, $type, $value)
    {
        print "$type:" . $cursor->hashKey . '=> ' .$value . "\n";
    }

    public function dumpString(\Symfony\Component\VarDumper\Cloner\Cursor $cursor, $str, $bin, $cut)
    {
        // TODO: Implement dumpString() method.
        print "string:".$cursor->hashKey . '=> ' .$str . "\n";
    }

    public function enterHash(\Symfony\Component\VarDumper\Cloner\Cursor $cursor, $type, $class, $hasChild)
    {
        // TODO: Implement enterHash() method.
        print "enterhash:$type" . '=> ' .$class . "\n";
    }

    public function leaveHash(\Symfony\Component\VarDumper\Cloner\Cursor $cursor, $type, $class, $hasChild, $cut)
    {
        // TODO: Implement leaveHash() method.
        print "leavehash:$type" . '=> ' .$class . "\n";
    }


}