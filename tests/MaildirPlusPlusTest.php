<?php
//----------------------------------------------------------------------------
//
//----------------------------------------------------------------------------

namespace Jaypha\Maildir;

use PHPUnit\Framework\TestCase;


class MaildirPPExp extends MaildirPlusPlus
{
  private $x;
  function __construct($m) { $this->x = $m; }
  function __get($p)
  {
    switch ($p)
    {
      case "dirs":
        return $this->x->dirs;
    }
  }

  function _getDirNameFromFolder($f) { return $this->getDirNameFromFolder($f); }
  function _getFolderNameFromDir($d) { return $this->getFolderNameFromDir($d); }
  function _getSize($name) { return $this->getSize($name); }
  function _sizePattern() { return parent::SIZE_PATTERN; }
}



class MaildirPlusPlusTest extends TestCase
{
  const DIR = __DIR__."/maildir";
  const QUOTA_FILE = __DIR__."/maildir/maildirsize";


  private $maildirpp;
  
  function setup()
  {
    if (!file_exists(self::DIR))
      mkdir(self::DIR);
    $this->maildirpp = MaildirPlusPlus::create(self::DIR);
  }

  function testConverters()
  {
    $m = new MaildirPPExp(null);
    
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
    $this->assertTrue($this->maildirpp->rootDir == self::DIR);

    $folder = $this->maildirpp->getFolder("Sent");
    $this->assertTrue($folder->rootDir == self::DIR."/.Sent");

    $folder = $this->maildirpp->getFolder("Trash");
    $this->assertTrue($folder->rootDir == self::DIR."/.Trash");

    $this->assertFalse($this->maildirpp->getFolder("Null"));
    
  }

  function testCreateFolder()
  {
    $mppx = new MaildirPPExp($this->maildirpp);

    $folder = $this->maildirpp->createFolder("one");
    $this->assertTrue(array_key_exists(".one",$mppx->dirs));
    $this->assertEquals($folder->rootDir, self::DIR."/.one");

    $folder = $this->maildirpp->createFolder("one/two");
    $this->assertTrue(array_key_exists(".one.two",$mppx->dirs));
    $this->assertEquals($folder->rootDir, self::DIR."/.one.two");

    $folder = $this->maildirpp->createFolder("three");
    $this->assertEquals($folder->rootDir, self::DIR."/.three");

    $folder = $this->maildirpp->createFolder("first/second");
    $this->assertEquals($folder->rootDir, self::DIR."/.first.second");

    $folder = $this->maildirpp->deleteFolder("first/second");
    $this->assertFalse(array_key_exists(".first.second",$mppx->dirs));
    $this->assertFalse(is_dir(self::DIR."/.first.second"));
  }

  function testLocate()
  {
    $name1 = $this->maildirpp->deliver("abc");
    $name2 = $this->maildirpp->getFolder("Drafts")->save("xyz");
    $this->assertEquals($this->maildirpp->locate($name1), "Inbox");
    $this->assertEquals($this->maildirpp->locate($name2), "Drafts");
    $this->assertFalse($this->maildirpp->locate("nobody"));
  }

  function testMove()
  {
    $inbox = $this->maildirpp->getFolder("Inbox");
    $name = $this->maildirpp->deliver("xyz");
    $drafts = $this->maildirpp->getFolder("Drafts");
    $this->assertTrue($inbox->exists($name));
    $this->maildirpp->move($name, "Drafts");
    $this->assertTrue($drafts->exists($name));
    $this->assertFalse($inbox->exists($name));
    $this->assertEquals($this->maildirpp->locate($name), "Drafts");
  }

  function testTrash()
  {
    $inbox = $this->maildirpp->getFolder("Inbox");
    $name = $this->maildirpp->deliver("xyz");
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
    $name1 = $this->maildirpp->deliver("xyz");
    $name2 = $this->maildirpp->deliver("abc");
    $trash = $this->maildirpp->getFolder("Trash");
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

  function testSizeAndCount()
  {
    $name1 = $this->maildirpp->deliver("xyz");
    $this->assertFalse(file_exists(self::QUOTA_FILE));
    $stat = $this->maildirpp->getSizeAndCount();
    $this->assertEquals(3, $stat["size"]);
    $this->assertEquals(1, $stat["count"]);

    $name2 = $this->maildirpp->deliver("abc123%^&");
    $this->maildirpp->getFolder("Inbox")->touch($name2);
    assert(!file_exists(self::QUOTA_FILE));
    $stat = $this->maildirpp->getSizeAndCount();
    $this->assertEquals(12, $stat["size"]);
    $this->assertEquals(2, $stat["count"]);

    $this->maildirpp->trash($name1);
    assert(!file_exists(self::QUOTA_FILE));
    $stat = $this->maildirpp->getSizeAndCount();
    $this->assertEquals(9, $stat["size"]);
    $this->assertEquals(1, $stat["count"]);
  }

  function testGetSize()
  {
    MaildirPlusPlus::destroy(self::DIR);
    $mdpp = MaildirPlusPlus::create(self::DIR, true);
    $mppx = new MaildirPPExp($mdpp);
    $this->assertTrue(is_file(self::QUOTA_FILE));

    $name1 = $mdpp->deliver("abcdefghijklmnopqrstuvwxyz");
    $matches = [];
    $res = preg_match($mppx->_sizePattern(), $name1, $matches);
    $this->assertEquals(1, $res);
    $this->assertEquals(2, count($matches));
    $this->assertEquals(",S=26", $matches[0]);
    $this->assertEquals("26", $matches[1]);

    
  }

  function testSetQuota()
  {
    MaildirPlusPlus::destroy(self::DIR);
    $mdpp = MaildirPlusPlus::create(self::DIR, true);
    $this->assertTrue(is_file(self::QUOTA_FILE));
    $this->assertEquals("\n0 0\n", file_get_contents(self::QUOTA_FILE));

    $mdpp->setQuotas(15,4);
    $this->assertEquals("15S,4C\n0 0\n", file_get_contents(self::QUOTA_FILE));
    $quotas = $mdpp->getQuotas();
    $this->assertEquals(["size" => 15, "count"=> 4], $quotas);

    $mdpp->setQuotas(NULL,12);
    $this->assertTrue(is_file(self::QUOTA_FILE));
    $this->assertEquals("12C\n0 0\n", file_get_contents(self::QUOTA_FILE));
    $quotas = $mdpp->getQuotas();
    $this->assertEquals(["size" => null, "count"=> 12], $quotas);

    $mdpp->setQuotas(20,NULL);
    $this->assertTrue(is_file(self::QUOTA_FILE));
    $this->assertEquals("20S\n0 0\n", file_get_contents(self::QUOTA_FILE));
    $quotas = $mdpp->getQuotas();
    $this->assertEquals(["size" => 20, "count"=> null], $quotas);

    $mdpp->setQuotas(NULL, NULL);
    $this->assertTrue(is_file(self::QUOTA_FILE));
    $this->assertEquals("\n0 0\n", file_get_contents(self::QUOTA_FILE));
    $quotas = $mdpp->getQuotas();
    $this->assertEquals(["size" => null, "count"=> null], $quotas);
    $this->assertEquals(["size" => 0, "count"=> 0], $mdpp->getSizeAndCount());

    $name1 = $mdpp->deliver("abcdefghi");
    $this->assertEquals(",S=9", substr($name1, -4));
    $this->assertEquals("\n0 0\n9 1\n", file_get_contents(self::QUOTA_FILE));
    $this->assertEquals(["size" => 9, "count"=> 1], $mdpp->getSizeAndCount());

    $name2 = $mdpp->deliver("1234");
    $this->assertEquals(",S=4", substr($name2, -4));
    $this->assertEquals("\n0 0\n9 1\n4 1\n", file_get_contents(self::QUOTA_FILE));
    $this->assertEquals(["size" => 13, "count"=> 2], $mdpp->getSizeAndCount());

    $mdpp->resetQuotas();
    $this->assertEquals("\n13 2\n", file_get_contents(self::QUOTA_FILE));
    $this->assertEquals(["size" => 13, "count"=> 2], $mdpp->getSizeAndCount());

    $mdpp->trash($name1);
    $this->assertEquals("\n13 2\n-9 -1\n", file_get_contents(self::QUOTA_FILE));
    $this->assertEquals(["size" => 4, "count"=> 1], $mdpp->getSizeAndCount());

    $mdpp->resetQuotas();
    $this->assertEquals("\n4 1\n", file_get_contents(self::QUOTA_FILE));
    $this->assertEquals(["size" => 4, "count"=> 1], $mdpp->getSizeAndCount());
  }

  function testQuotaDeliveries()
  {
    MaildirPlusPlus::destroy(self::DIR);
    $mdpp = MaildirPlusPlus::create(self::DIR, true);
    $mdpp->setQuotas(15,3);

    $name = $mdpp->deliver("abcdefghi");
    $this->assertTrue($name !== false);
    $this->assertEquals([".",".."], scandir(self::DIR."/tmp"));

    $name = $mdpp->deliver("123456789");
    $this->assertFalse($name);
    $this->assertEquals([".",".."], scandir(self::DIR."/tmp"));

    $name = $mdpp->deliver("1");
    $this->assertTrue($name !== false);
    $this->assertEquals([".",".."], scandir(self::DIR."/tmp"));

    $name = $mdpp->deliver("2");
    $this->assertTrue($name !== false);
    $this->assertEquals([".",".."], scandir(self::DIR."/tmp"));

    $name = $mdpp->deliver("3");
    $this->assertFalse($name);
    $this->assertEquals([".",".."], scandir(self::DIR."/tmp"));
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
