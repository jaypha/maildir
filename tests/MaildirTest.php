<?php
//----------------------------------------------------------------------------
//
//----------------------------------------------------------------------------

namespace Jaypha\Maildir;

use PHPUnit\Framework\TestCase;

class MaildirTest extends TestCase
{
  const DIR = __DIR__."/maildir";

  private $maildir;

  function setup()
  {
    if (!file_exists(self::DIR))
      mkdir(self::DIR);
    $this->maildir = Maildir::create(self::DIR);
  }

  function testSave()
  {
    // Test string
    $name = $this->maildir->save("xyz");
    $this->assertTrue($this->maildir->exists($name));
    $this->assertTrue($this->maildir->isNew($name));
    $this->assertTrue(is_file(self::DIR."/new/$name"));
    $this->assertEquals(self::DIR."/new/$name", $this->maildir->getPath($name));

    // Test stream
    $stream = fopen("data://text/plain;base64,".base64_encode("abc"), "r");
    $name = $this->maildir->save($stream);
    $this->assertTrue($this->maildir->exists($name));
    $this->assertTrue($this->maildir->isNew($name));
    $this->assertTrue(is_file(self::DIR."/new/$name"));
    $this->assertEquals(self::DIR."/new/$name", $this->maildir->getPath($name));
  }

  function testRead()
  {
    $contents = "xyz";
    $name = $this->maildir->save("xyz");
    $savedContents = $this->maildir->fetch($name);
    $this->assertEquals($contents, $savedContents);
    $this->assertTrue(is_file(self::DIR."/cur/$name:2,"));
    $this->assertEquals(self::DIR."/cur/$name:2,", $this->maildir->getPath($name));

    $stream = $this->maildir->fetchAsStream($name);
    $this->assertTrue(is_resource($stream));
    $this->assertEquals($contents, stream_get_contents($stream));
    fclose($stream);
    
    
  }

  function testFlag()
  {
    $name = $this->maildir->save("xyz");
    $this->maildir->setFlag($name, "F");
    $this->assertTrue($this->maildir->hasFlag($name,"F"));
    $this->assertFalse($this->maildir->hasFlag($name,"T"));
    $this->assertTrue(is_file(self::DIR."/cur/$name:2,F"));
    $this->assertEquals(self::DIR."/cur/$name:2,F", $this->maildir->getPath($name));
    $this->maildir->setFlag($name, "D");
    $this->assertTrue($this->maildir->hasFlag($name,"D"));
    $this->assertTrue($this->maildir->hasFlag($name,"F"));
    $this->assertEquals($this->maildir->getFlags($name), "DF");
    $this->assertTrue(is_file(self::DIR."/cur/$name:2,DF"));
    $this->assertEquals(self::DIR."/cur/$name:2,DF", $this->maildir->getPath($name));
    $this->maildir->clearFlag($name, "D");
    $this->assertFalse($this->maildir->hasFlag($name,"D"));
    $this->assertTrue($this->maildir->hasFlag($name,"F"));
    $this->assertTrue(is_file(self::DIR."/cur/$name:2,F"));
    $this->assertEquals(self::DIR."/cur/$name:2,F", $this->maildir->getPath($name));
  }

  function testDelete()
  {
    $name1 = $this->maildir->save("abc");
    $name2 = $this->maildir->save("xyz");

    $this->assertTrue($this->maildir->exists($name1));
    $this->assertTrue($this->maildir->exists($name2));

    $this->maildir->delete($name1);
    $this->assertFalse($this->maildir->exists($name1));
    $this->assertTrue($this->maildir->exists($name2));
    $this->assertFalse(is_file(self::DIR."/new/$name1"));

    $this->maildir->fetch($name2); // Transfer to 'cur' to test deleting from 'cur'.
    $this->maildir->delete($name2);
    $this->assertFalse($this->maildir->exists($name2));
    $this->assertFalse(is_file(self::DIR."/cur/$name2:2,"));
    $this->assertFalse($this->maildir->getPath($name2));
  }

  function testListNames()
  {
    $names = [
      $this->maildir->save("abc") => "abc",
      $this->maildir->save("xyz") => "xyz"
    ];

    foreach (array_keys($names) as $name)
      $this->assertTrue($this->maildir->exists($name));

    $count = 0;
    foreach ($this->maildir->getNames() as $name)
    {
      ++$count;
      $this->assertArrayHasKey($name, $names);
    }
    $this->assertEquals(2, $count);

    $count = 0;
    foreach ($this->maildir->getFiles() as $name => $contents)
    {
      ++$count;
      $this->assertArrayHasKey($name, $names);
      $this->assertEquals($names[$name], $contents);
    }
    $this->assertEquals(2, $count);

    $count = 0;
    foreach ($this->maildir->getStreams() as $name => $stream)
    {
      ++$count;
      $this->assertArrayHasKey($name, $names);
      $this->assertEquals($names[$name], stream_get_contents($stream));
      fclose($stream);
    }
    $this->assertEquals(2, $count);
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
