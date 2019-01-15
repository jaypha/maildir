<?php
//----------------------------------------------------------------------------
//
//----------------------------------------------------------------------------

namespace Jaypha\Maildir;

use PHPUnit\Framework\TestCase;

class MaildirCreateTest extends TestCase
{
  const DIR = __DIR__."/maildir";

  function testCreate()
  {
    $d = Maildir::create(self::DIR);
    $this->assertTrue(is_dir(self::DIR."/cur"));
    $this->assertTrue(is_dir(self::DIR."/tmp"));
    $this->assertTrue(is_dir(self::DIR."/new"));
    Maildir::destroy(self::DIR);
    $this->assertFalse(is_dir(self::DIR."/cur"));
    $this->assertFalse(is_dir(self::DIR."/tmp"));
    $this->assertFalse(is_dir(self::DIR."/new"));
  }
}

class MaildirTest extends TestCase
{
  const DIR = __DIR__."/maildir";

  private $maildir;

  function setup()
  {
    $this->maildir = Maildir::create(self::DIR);
  }

  function testSave()
  {
    $n = $this->maildir->save("xyz");
    $this->assertTrue($this->maildir->exists($n));
    $this->assertTrue($this->maildir->isNew($n));
    $this->assertTrue(is_file(self::DIR."/new/$n"));
  }

  function testRead()
  {
    $n = $this->maildir->save("xyz");
    $f = $this->maildir->fetch($n);
    $this->assertEquals($f, "xyz");
    $this->assertTrue(is_file(self::DIR."/cur/$n:2,"));
  }

  function testFlag()
  {
    $n = $this->maildir->save("xyz");
    $this->maildir->setFlag($n, "F");
    $this->assertTrue($this->maildir->hasFlag($n,"F"));
    $this->assertFalse($this->maildir->hasFlag($n,"T"));
    $this->assertTrue(is_file(self::DIR."/cur/$n:2,F"));
    $this->maildir->setFlag($n, "D");
    $this->assertTrue($this->maildir->hasFlag($n,"D"));
    $this->assertTrue($this->maildir->hasFlag($n,"F"));
    $this->assertEquals($this->maildir->getFlags($n), "DF");
    $this->assertTrue(is_file(self::DIR."/cur/$n:2,DF"));
    $this->maildir->clearFlag($n, "D");
    $this->assertFalse($this->maildir->hasFlag($n,"D"));
    $this->assertTrue($this->maildir->hasFlag($n,"F"));
    $this->assertTrue(is_file(self::DIR."/cur/$n:2,F"));
  }

  function testDelete()
  {
    $n1 = $this->maildir->save("abc");
    $n2 = $this->maildir->save("xyz");

    $this->assertTrue($this->maildir->exists($n1));
    $this->assertTrue($this->maildir->exists($n2));

    $this->maildir->delete($n1);
    $this->assertFalse($this->maildir->exists($n1));
    $this->assertTrue($this->maildir->exists($n2));
    $this->assertFalse(is_file(self::DIR."/new/$n1"));

    $this->maildir->fetch($n2); // Transfer to 'cur' to test deleting from 'cur'.
    $this->maildir->delete($n2);
    $this->assertFalse($this->maildir->exists($n2));
    $this->assertFalse(is_file(self::DIR."/cur/$n2:2,"));
  }

  public function tearDown()
  {
    Maildir::destroy(self::DIR);
  }

  public static function tearDownAfterClass()
  {
  }
}

//----------------------------------------------------------------------------
// Copyright (C) 2018 Jaypha.
// License: BSL-1.0
// Author: Jason den Dulk
//
