<?php
namespace Jaypha\Maildir;

use PHPUnit\Framework\TestCase;

class MaildirCreateTest extends TestCase
{
  const DIR = __DIR__."/maildir";

  function testCreate()
  {
    if (!file_exists(self::DIR))
      mkdir(self::DIR);
    $md = Maildir::create(self::DIR);
    $this->assertEquals(self::DIR, $md->rootDir);
    $this->assertTrue(is_dir(self::DIR."/cur"));
    $this->assertTrue(is_dir(self::DIR."/tmp"));
    $this->assertTrue(is_dir(self::DIR."/new"));
    Maildir::destroy(self::DIR);
    $this->assertFalse(is_dir(self::DIR."/cur"));
    $this->assertFalse(is_dir(self::DIR."/tmp"));
    $this->assertFalse(is_dir(self::DIR."/new"));
  }
}

