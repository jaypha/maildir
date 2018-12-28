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

  //-----------------------------------

  private $parentDir;
  function __construct($parentDir)
  {
    $this->parentDir = $parentDir;
  }

  //-----------------------------------

  function isNew($name)
  {
    return is_file("$this->parentDir/new/$name");
  }

  //-----------------------------------

  function save($contents)
  {
    $name = $this->createName();
    $res = file_put_contents("$this->parentDir/tmp/$name", $contents);
    if ($res === false)
      throw new \RuntimeException("Failed to write data to '$parentDir' Maildir.");
    $res = rename("$this->parentDir/tmp/$name", "$this->parentDir/new/$name");
    return $name;
  }

  //-----------------------------------

  function exists($name)
  {
    return $this->isNew($name) || ($this->findFilename($name) !== false);
  }

  //-----------------------------------

  function fetch($name)
  {
    $this->makeCurrent($name);

    $filename = $this->findFilename($name);
    if ($filename === false)
      throw new \RuntimeException("Unable to find '$name'.");
    return file_get_contents("$this->parentDir/cur/$filename");
  }

  protected function makeCurrent($name)
  {
    if (is_file("$this->parentDir/new/$name"))
      rename("$this->parentDir/new/$name", "$this->parentDir/cur/$name:2,");
  }

  //-----------------------------------

  function trash($name)
  {
    $this->setFlag($name, "T", true);
  }

  //-----------------------------------

  function emptyTrash()
  {
    $files = scandir($this->parentDir."/cur");

    foreach ($files as $file)
    {
      if ($file == "." || $file == "..") continue;
      $flags = substr($file, strpos($file,":")+3);
      if (strpos($flags, "T") !== false)
      unlink("$this->parentDir/cur/$file");
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

  function getFlags($name)
  {
    $this->makeCurrent($name);
    $fn = $this->findFilename($name);
    if ($fn === false)
      return false;

    $flags = substr($fn, strpos($fn,":")+3);
    return $flags;
  }

  //-----------------------------------

  function hasFlag($name, $flag)
  {
    $flags = $this->getFlags($name);
    return strpos($flags, $flag) !== false;
  }

  //-----------------------------------

  function clearFlag($name, $flag)
  {
    $flags = $this->getFlags($name);
    $newFlags = str_replace($flag, "", $flags);
    if ($newFlags == $flags)
      return;
    rename("$this->parentDir/cur/$name:2,$flags", "$this->parentDir/cur/$name:2,$newFlags");
  }

  //-----------------------------------
  
  function setFlag($name, $flag)
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

  protected function findFilename($name)
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
