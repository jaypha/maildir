<?php
//----------------------------------------------------------------------------
//
//----------------------------------------------------------------------------

namespace Jaypha\Maildir;

use PHPUnit\Framework\TestCase;

class MaildirTest extends TestCase
{
  const DIR = __DIR__."/maildir";

  function testCreate()
  {
    $d = Maildir::create(self::DIR);
    $this->assertTrue(is_dir(self::DIR."/cur"));
    $this->assertTrue(is_dir(self::DIR."/tmp"));
    $this->assertTrue(is_dir(self::DIR."/new"));
  }

  function testSave()
  {
    $d = Maildir::create(self::DIR);
    $n = $d->save("xyz");
    $this->assertTrue($d->exists($n));
    $this->assertTrue($d->isNew($n));
    $this->assertTrue(is_file(self::DIR."/new/$n"));
  }

  function testRead()
  {
    $d = Maildir::create(self::DIR);
    $n = $d->save("xyz");
    $f = $d->fetch($n);
    $this->assertEquals($f, "xyz");
    $this->assertTrue(is_file(self::DIR."/cur/$n:2,"));
  }

  function testFlag()
  {
    $d = Maildir::create(self::DIR);
    $n = $d->save("xyz");
    $d->setFlag($n, "F");
    $this->assertTrue($d->hasFlag($n,"F"));
    $this->assertFalse($d->hasFlag($n,"T"));
    $this->assertTrue(is_file(self::DIR."/cur/$n:2,F"));
    $d->setFlag($n, "D");
    $this->assertTrue($d->hasFlag($n,"D"));
    $this->assertTrue($d->hasFlag($n,"F"));
    $this->assertEquals($d->getFlags($n), "DF");
    $this->assertTrue(is_file(self::DIR."/cur/$n:2,DF"));
    $d->clearFlag($n, "D");
    $this->assertFalse($d->hasFlag($n,"D"));
    $this->assertTrue($d->hasFlag($n,"F"));
    $this->assertTrue(is_file(self::DIR."/cur/$n:2,F"));
  }

  function testDelete()
  {
    $d = Maildir::create(self::DIR);
    $n1 = $d->save("abc");
    $n2 = $d->save("xyz");


    $this->assertTrue($d->exists($n1));
    $this->assertTrue($d->exists($n2));

    $d->delete($n1);
    $this->assertFalse($d->exists($n1));
    $this->assertTrue($d->exists($n2));
    $this->assertFalse(is_file(self::DIR."/new/$n1"));

    $d->fetch($n2); // Transfer to 'cur' to test deleting from 'cur'.
    $d->delete($n2);
    $this->assertFalse($d->exists($n2));
    $this->assertFalse(is_file(self::DIR."/cur/$n2:2,"));
  }

  public function tearDown()
  {
    self::delTree(self::DIR."/cur");
    self::delTree(self::DIR."/new");
    self::delTree(self::DIR."/tmp");
  }

  public static function tearDownAfterClass()
  {
  }

  public static function delTree($dir) {
   $files = array_diff(scandir($dir), array('.','..'));
    foreach ($files as $file) {
      (is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file");
    }
    return rmdir($dir);
  } 
}

//----------------------------------------------------------------------------
// Copyright (C) 2018 Jaypha.
// License: BSL-1.0
// Author: Jason den Dulk
//
