<?php
//----------------------------------------------------------------------------
//
//----------------------------------------------------------------------------

namespace Jaypha\Maildir;

use PHPUnit\Framework\TestCase;

class MaildirPlusPlusCreateTest extends TestCase
{
  const DIR = __DIR__."/maildir";

  function testCreate()
  {
    if (!file_exists(self::DIR))
      mkdir(self::DIR);
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
    MaildirPlusPlus::destroy(self::DIR);
    $this->assertFalse(is_dir(self::DIR."/cur"));
    $this->assertFalse(is_dir(self::DIR."/tmp"));
    $this->assertFalse(is_dir(self::DIR."/new"));
    $this->assertFalse(is_dir(self::DIR."/.Sent"));
    $this->assertFalse(is_dir(self::DIR."/.Trash"));
    $this->assertFalse(is_dir(self::DIR."/.Drafts"));
  }
}

