<?php
//----------------------------------------------------------------------------
//
//----------------------------------------------------------------------------
// Read and write files in Maildir format.
// See https://cr.yp.to/proto/maildir.html
//----------------------------------------------------------------------------

namespace Jaypha\Maildir;

class Maildir
{
  static $qVal = 0;

  static function create($parentDir)
  {
    if (!is_dir($parentDir))
      throw new \RuntimeException("'$parentDir' is not a directory");
    mkdir("$parentDir/cur");
    mkdir("$parentDir/tmp");
    mkdir("$parentDir/new");
    return new Maildir($parentDir);
  }

  static function destroy($parentDir)
  {
    if (!is_dir($parentDir))
      throw new \RuntimeException("'$parentDir' is not a directory");
    if (is_dir("$parentDir/cur"))
    {
      array_map("unlink",glob("$parentDir/cur/*"));
      rmdir("$parentDir/cur");
    }
    if (is_dir("$parentDir/new"))
    {
      array_map("unlink",glob("$parentDir/new/*"));
      rmdir("$parentDir/new");
    }
    if (is_dir("$parentDir/tmp"))
    {
      array_map("unlink",glob("$parentDir/tmp/*"));
      rmdir("$parentDir/tmp");
    }
  }

  //-----------------------------------

  protected $parentDir;

  function __construct($parentDir)
  {
    $this->parentDir = $parentDir;
  }

  //-----------------------------------

  function isNew(string $name)
  {
    return is_file("$this->parentDir/new/$name");
  }

  //-----------------------------------

  function save(string $contents)
  {
    $name = $this->createName();
    $res = file_put_contents("$this->parentDir/tmp/$name", $contents);
    if ($res === false)
      throw new \RuntimeException("Failed to write data to '$parentDir' Maildir.");
    $res = rename("$this->parentDir/tmp/$name", "$this->parentDir/new/$name");
    return $name;
  }

  //-----------------------------------

  function exists(string $name)
  {
    return $this->isNew($name) || ($this->findFilename($name) !== false);
  }

  //-----------------------------------

  function fetch(string $name)
  {
    $this->makeCurrent($name);

    $filename = $this->findFilename($name);
    if ($filename === false)
      throw new \RuntimeException("Unable to find '$name'.");
    return file_get_contents("$this->parentDir/cur/$filename");
  }

  protected function makeCurrent(string $name)
  {
    if (is_file("$this->parentDir/new/$name"))
      rename("$this->parentDir/new/$name", "$this->parentDir/cur/$name:2,");
  }

  //-----------------------------------

  function delete(string $name)
  {
    if (is_file("$this->parentDir/new/$name"))
      unlink("$this->parentDir/new/$name");
    else
    {
      $filename = $this->findFilename($name);
      if ($filename === false)
        throw new \RuntimeException("Unable to find '$name'.");
      unlink("$this->parentDir/cur/$filename");
    }
  }

  //-----------------------------------

  protected function clearTmp()
  {
    $files = scandir($this->parentDir."/tmp");

    foreach ($files as $file)
    {
      if ($file == "." || $file == "..") continue;
      unlink("$this->parentDir/tmp/$file");
    }
  }

  //-----------------------------------

  function getFlags(string $name)
  {
    $this->makeCurrent($name);
    $fn = $this->findFilename($name);
    if ($fn === false)
      return false;

    $flags = substr($fn, strpos($fn,":")+3);
    return $flags;
  }

  //-----------------------------------

  function hasFlag(string $name, string $flag)
  {
    $flags = $this->getFlags($name);
    return strpos($flags, $flag) !== false;
  }

  //-----------------------------------

  function clearFlag(string $name, string $flag)
  {
    $flags = $this->getFlags($name);
    $newFlags = str_replace($flag, "", $flags);
    if ($newFlags == $flags)
      return;
    rename("$this->parentDir/cur/$name:2,$flags", "$this->parentDir/cur/$name:2,$newFlags");
  }

  //-----------------------------------
  
  function setFlag(string $name, string $flag)
  {
    $flags = $this->getFlags($name);
    if (strpos($flags, $flag) !== false)
      return;
    $f = str_split($flags);
    $f[] = $flag;
    sort($f);
    $newFlags = implode("", $f);
    rename("$this->parentDir/cur/$name:2,$flags", "$this->parentDir/cur/$name:2,$newFlags");
  }

  //-----------------------------------

  protected function findFilename(string $name)
  {
    $handle = opendir($this->parentDir."/cur");

    $ret = false;
    while (false !== ($entry = readdir($handle))) {
      if (substr($entry, 0, strpos($entry,":")) == $name)
      {
        $ret = $entry;
        break;
      }
    }

    closedir($handle);
    return $ret;    
  }

  //-----------------------------------

  protected function createName()
  {
    $tod = gettimeofday();
    $left = $tod["sec"];
    $m = "M".$tod["usec"];
    $p = "P".getmypid();
    $q = "Q".(++self::$qVal);
    $right = gethostname();
    return "$left.$m$p$q.$right";
  }
}

//----------------------------------------------------------------------------
// Copyright (C) 2018 Jaypha.
// License: BSL-1.0
// Author: Jason den Dulk
//
