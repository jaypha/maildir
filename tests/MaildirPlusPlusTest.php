<?php
//----------------------------------------------------------------------------
//
//----------------------------------------------------------------------------

namespace Jaypha\Maildir;

use PHPUnit\Framework\TestCase;

class MaildirExp extends MaildirPlusPlus
{
  private $x;
  function __construct($m) { $this->x = $m; }
  function __get($p)
  {
    switch ($p)
    {
      case "parentDir";
        return $this->x->parentDir;
      case "dirs":
        return $this->x->dirs;
    }
  }

  function _getDirNameFromFolder($f) { return $this->getDirNameFromFolder($f); }
  function _getFolderNameFromDir($d) { return $this->getFolderNameFromDir($d); }
}

class MaildirPlusPlusCreateTest extends TestCase
{
  const DIR = __DIR__."/maildir";

  function testCreate()
  {
    $d = MaildirPlusPlus::create(self::DIR);
    $this->assertTrue(is_dir(self::DIR."/cur"));
    $this->assertTrue(is_dir(self::DIR."/tmp"));
    $this->assertTrue(is_dir(self::DIR."/new"));
    $this->assertTrue(is_dir(self::DIR."/.Sent/cur"));
    $this->assertTrue(is_dir(self::DIR."/.Sent/tmp"));
    $this->assertTrue(is_dir(self::DIR."/.Sent/new"));
    $this->assertTrue(is_dir(self::DIR."/.Trash/cur"));
    $this->assertTrue(is_dir(self::DIR."/.Trash/tmp"));
    $this->assertTrue(is_dir(self::DIR."/.Trash/new"));
    $this->assertTrue(is_dir(self::DIR."/.Drafts/cur"));
    $this->assertTrue(is_dir(self::DIR."/.Drafts/tmp"));
    $this->assertTrue(is_dir(self::DIR."/.Drafts/new"));
    Maildir::destroy(self::DIR);
    $this->assertFalse(is_dir(self::DIR."/cur"));
    $this->assertFalse(is_dir(self::DIR."/tmp"));
    $this->assertFalse(is_dir(self::DIR."/new"));
    $this->assertFalse(is_dir(self::DIR."/.Sent"));
    $this->assertFalse(is_dir(self::DIR."/.Trash"));
    $this->assertFalse(is_dir(self::DIR."/.Drafts"));
  }
}


class MaildirPlusPlusTest extends TestCase
{
  const DIR = __DIR__."/maildir";

  private $maildirpp;
  
  function setup()
  {
    $this->maildirpp = MaildirPlusPlus::create(self::DIR);
  }

  function testConverters()
  {
    $m = new MaildirExp(null);
    
    $this->assertEquals(
      $m->_getDirNameFromFolder("Trash"),
      ".Trash"
    );
    $this->assertEquals(
      $m->_getDirNameFromFolder("Sent"),
      ".Sent"
    );
    $this->assertEquals(
      $m->_getDirNameFromFolder("One/Two"),
      ".One.Two"
    );
    $this->assertEquals(
      $m->_getDirNameFromFolder("One/Two/Three"),
      ".One.Two.Three"
    );

    $this->assertEquals(
      $m->_getFolderNameFromDir(".One"),
      "One"
    );
    $this->assertEquals(
      $m->_getFolderNameFromDir(".One.Two"),
      "One/Two"
    );
    $this->assertEquals(
      $m->_getFolderNameFromDir(".One.Two.Three"),
      "One/Two/Three"
    );
  }

  function testFolderDir()
  {
    $dir = new MaildirExp($this->maildirpp->getFolder("Inbox"));
    $this->assertTrue($dir->parentDir == self::DIR);

    $dir = new MaildirExp($this->maildirpp->getFolder("Sent"));
    $this->assertTrue($dir->parentDir == self::DIR."/.Sent");

    $dir = new MaildirExp($this->maildirpp->getFolder("Trash"));
    $this->assertTrue($dir->parentDir == self::DIR."/.Trash");

    $this->assertFalse($this->maildirpp->getFolder("Null"));
    
  }

  function testCreateFolder()
  {
    $x = new MaildirExp($this->maildirpp);

    $folder = $this->maildirpp->createFolder("one");
    $dir = new MaildirExp($folder);
    $this->assertEquals($dir->parentDir, self::DIR."/.one");

    $folder = $this->maildirpp->createFolder("one/two");
    $dir = new MaildirExp($folder);
    $this->assertEquals($dir->parentDir, self::DIR."/.one.two");

    $folder = $this->maildirpp->createFolder("three", $folder);
    $dir = new MaildirExp($folder);
    $this->assertEquals($dir->parentDir, self::DIR."/.one.two.three");

    $folder = $this->maildirpp->createFolder("first/second");
    $dir = new MaildirExp($folder);
    $this->assertEquals($dir->parentDir, self::DIR."/.first.second");

    $folder = $this->maildirpp->deleteFolder("first/second");
    $this->assertFalse(array_key_exists(".first.second",$x->dirs));
    $this->assertFalse(is_dir(self::DIR."/.first.second"));
  }

  function testLocate()
  {
    $name1 = $this->maildirpp->save("abc");
    $name2 = $this->maildirpp->getFolder("Drafts")->save("xyz");
    $this->assertEquals($this->maildirpp->locate($name1), "Inbox");
    $this->assertEquals($this->maildirpp->locate($name2), "Drafts");
    $this->assertFalse($this->maildirpp->locate("nobody"));
  }

  function testMove()
  {
    $name = $this->maildirpp->save("xyz");
    $inbox = $this->maildirpp->getFolder("Inbox");
    $drafts = $this->maildirpp->getFolder("Drafts");
    $this->assertTrue($inbox->exists($name));
    $this->maildirpp->move($name, "Drafts");
    $this->assertTrue($drafts->exists($name));
    $this->assertFalse($inbox->exists($name));
    $this->assertEquals($this->maildirpp->locate($name), "Drafts");
  }

  function testTrash()
  {
    $name = $this->maildirpp->save("xyz");
    $inbox = $this->maildirpp->getFolder("Inbox");
    $trash = $this->maildirpp->getFolder("Trash");
    $this->assertTrue($inbox->exists($name));
    $this->maildirpp->trash($name);
    $this->assertTrue($trash->exists($name));
    $this->assertTrue($trash->hasFlag($name,"T"));
    $this->assertFalse($inbox->exists($name));
    $this->assertEquals($this->maildirpp->locate($name), "Trash");
  }

  function testEmptyTrash()
  {
    $trash = $this->maildirpp->getFolder("Trash");
    $name1 = $this->maildirpp->save("xyz");
    $name2 = $this->maildirpp->save("abc");
    $this->maildirpp->trash($name1);
    $this->maildirpp->trash($name2);
    $this->assertTrue($trash->exists($name1));
    $this->assertTrue($trash->exists($name2));

    $this->maildirpp->emptyTrash();
    $this->assertFalse($trash->exists($name1));
    $this->assertFalse($trash->exists($name2));
    $this->assertFalse($this->maildirpp->locate($name1));
    $this->assertFalse($this->maildirpp->locate($name2));
  }

  public function tearDown()
  {
    MaildirPlusPlus::destroy(self::DIR);
  }

}

//----------------------------------------------------------------------------
// Copyright (C) 2019 Jaypha.
// License: BSL-1.0
// Author: Jason den Dulk
//
