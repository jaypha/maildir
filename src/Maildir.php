<?php
//----------------------------------------------------------------------------
//
//----------------------------------------------------------------------------
// Read and write files in Maildir format.
// See https://cr.yp.to/proto/maildir.html
//----------------------------------------------------------------------------
//
// How filenames are used in this class
//
// In this class, the name returned by save, and accepted by other functions
// do not include the flag settings. So while the actual filename on the disk may
// change as flags are set and cleared, the name used with this class does not.
//
//----------------------------------------------------------------------------

namespace Jaypha\Maildir;

class Maildir
{
  protected static $qVal = 0;

  //-----------------------------------

  // Create a maildir structure
  static function create($rootDir) : Maildir
  {
    if (!is_dir($rootDir))
      throw new \RuntimeException("'$rootDir' is not a directory");
    mkdir("$rootDir/cur");
    mkdir("$rootDir/tmp");
    mkdir("$rootDir/new");
    return new Maildir($rootDir);
  }

  //-----------------------------------

  // Destroys the maildir structure.
  static function destroy($rootDir)
  {
    if (!is_dir($rootDir))
      throw new \RuntimeException("'$rootDir' is not a directory");
    if (is_dir("$rootDir/cur"))
    {
      array_map("unlink",glob("$rootDir/cur/*"));
      rmdir("$rootDir/cur");
    }
    if (is_dir("$rootDir/new"))
    {
      array_map("unlink",glob("$rootDir/new/*"));
      rmdir("$rootDir/new");
    }
    if (is_dir("$rootDir/tmp"))
    {
      array_map("unlink",glob("$rootDir/tmp/*"));
      rmdir("$rootDir/tmp");
    }
  }

  //-----------------------------------

  // Creates a name to store a file under.
  static function createName() : string
  {
    $tod = gettimeofday();
    $left = $tod["sec"];
    $m = "M".$tod["usec"];
    $p = "P".getmypid();
    $q = "Q".(++self::$qVal);
    $right = gethostname();
    return "$left.$m$p$q.$right";
  }

  //-----------------------------------

  // Tests whether the directory has a maildir structure
  static function isMaildir($dir) : bool
  {
    return (
      is_dir("$dir/cur") &&
      is_dir("$dir/new") &&
      is_dir("$dir/tmp")
    );
  }

  //-----------------------------------

  protected $rootDir;

  function __construct($rootDir)
  {
    if (!self::isMaildir($rootDir))
      throw new \LogicException("Maildir: directory structure not found. Perhaps use Maildir::create()");
    $this->rootDir = $rootDir;
  }

  //-----------------------------------

  function __get($p)
  {
    switch ($p)
    {
      case "rootDir":
        return $this->rootDir;
      default:
        throw new \LogicException("Maildir: Property '$p' not supported");
    }
  }

  //-----------------------------------

  function isNew(string $name) : bool
  {
    return is_file("$this->rootDir/new/$name");
  }

  //-----------------------------------

  function save($contents) : string
  {
    $name = $this->createName();
    $res = file_put_contents("$this->rootDir/tmp/$name", $contents);
    if ($res === false)
      throw new \RuntimeException("Failed to write data to '$rootDir' Maildir.");
    $res = rename("$this->rootDir/tmp/$name", "$this->rootDir/new/$name");
    if ($res === false)
      throw new \RuntimeException("Failed to move file '$name' to 'new' in '$rootDir' Maildir.");
    return $name;
  }

  //-----------------------------------

  // Forces the file to the 'cur' directory
  function touch(string $name)
  {
    if (is_file("$this->rootDir/new/$name"))
      rename("$this->rootDir/new/$name", "$this->rootDir/cur/$name:2,");
  }

  //-----------------------------------

  function exists(string $name) : bool
  {
    return $this->isNew($name) || ($this->findFilename($name) !== false);
  }

  //-----------------------------------

  // Returns the contents of the file as a stream
  function fetch(string $name)
  {
    $this->touch($name);

    $filename = $this->findFilename($name);
    if ($filename === false)
      throw new \RuntimeException("Unable to find '$name'.");
    return file_get_contents("$this->rootDir/cur/$filename");
  }

  //-----------------------------------

  function fetchAsStream(string $name)
  {
    $this->touch($name);

    $filename = $this->findFilename($name);
    if ($filename === false)
      throw new \RuntimeException("Unable to find '$name'.");
    return fopen("$this->rootDir/cur/$filename", "r");
  }

  //-----------------------------------

  function delete(string $name)
  {
    if (is_file("$this->rootDir/new/$name"))
      unlink("$this->rootDir/new/$name");
    else
    {
      $filename = $this->findFilename($name);
      if ($filename === false)
        throw new \RuntimeException("Unable to find '$name'.");
      unlink("$this->rootDir/cur/$filename");
    }
  }

  //-----------------------------------

  protected function clearTmp()
  {
    array_map("unlink",glob("$this->rootDir/tmp/*"));
  }

  //-----------------------------------

  function getFlags(string $name)
  {
    $this->touch($name);
    $fn = $this->findFilename($name);
    if ($fn === false)
      return false;

    $flags = substr($fn, strpos($fn,":")+3);
    return $flags;
  }

  //-----------------------------------

  function hasFlag(string $name, string $flag) : bool
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
    rename("$this->rootDir/cur/$name:2,$flags", "$this->rootDir/cur/$name:2,$newFlags");
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
    rename("$this->rootDir/cur/$name:2,$flags", "$this->rootDir/cur/$name:2,$newFlags");
  }

  //-----------------------------------

  function getNames()
  {
    array_map([$this,"touch"], array_map("basename",glob($this->rootDir."/new/*")));

    foreach (array_map("basename",glob($this->rootDir."/cur/*")) as $filename)
    {
      yield $this->extractName($filename);
    }
  }

  //-----------------------------------

  function getFiles()
  {
    foreach ($this->getNames() as $name)
      yield $name => $this->fetch($name);
  }

  //-----------------------------------

  function getStreams()
  {
    foreach ($this->getNames() as $name)
      yield $name => $this->fetchAsStream($name);
  }

  //-----------------------------------

  // Gets the full path (including directory and flags) for $name
  function getPath(string $name)
  {
    $filename = $this->findFilename($name);
    if ($filename)
      return "$this->rootDir/cur/$filename";
    if ($this->isNew($name))
      return "$this->rootDir/new/$name";
    return false;
  }

  //-----------------------------------

  // Gets the filename (including flags) for $name.
  protected function findFilename(string $name)
  {
    $handle = opendir("$this->rootDir/cur");

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

  // Extracts the name part from a filename (the reverse of findFilename)
  protected function extractName(string $filename) : string
  {
    return substr($filename, 0, strpos($filename,":"));
  }

}

//----------------------------------------------------------------------------
// Copyright (C) 2018 Jaypha.
// License: BSL-1.0
// Author: Jason den Dulk
//
