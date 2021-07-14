<?php
//----------------------------------------------------------------------------
//
//----------------------------------------------------------------------------

namespace Jaypha\Maildir;

class MaildirPlusPlus
{
  const QUOTA_RECALC_SIZE = 5120;
  protected const QUOTA_FILENAME = "maildirsize";

  protected $dirs;
  protected $useQuotas;
  protected $rootDir;

  //-------------------------------------------------------

  static function create($rootDir, $useQuotas = false)
  {
    if (!is_dir($rootDir))
      throw new \RuntimeException("'$rootDir' is not a directory");

    if (scandir($rootDir) != [".",".."])
      throw new \RuntimeException("'$rootDir' is not empty");

    Maildir::create($rootDir);
    mkdir("$rootDir/.Sent");
    Maildir::create("$rootDir/.Sent");
    mkdir("$rootDir/.Trash");
    Maildir::create("$rootDir/.Trash");
    mkdir("$rootDir/.Drafts");
    Maildir::create("$rootDir/.Drafts");

    if ($useQuotas)
      file_put_contents("$rootDir/".self::QUOTA_FILENAME, "\n0 0\n");

    return new MaildirPlusPlus($rootDir);
  }

  static function isMaildirPlusPlus($dir)
  {
    return
      Maildir::isMaildir($dir) &&
      is_dir("$dir/.Trash") && 
      Maildir::isMaildir("$dir/.Trash");
  }

  //-------------------------------------------------------

  static function destroy($rootDir)
  {
    Maildir::destroy($rootDir);

    array_map(function ($fn) use ($rootDir) {
      if (is_dir($fn) && $fn != "$rootDir/." && $fn != "$rootDir/..")
      {
        Maildir::destroy("$fn");
        rmdir("$fn");
      }
    }, glob("$rootDir/.*"));
    if (file_exists("$rootDir/".self::QUOTA_FILENAME))
      unlink("$rootDir/".self::QUOTA_FILENAME);
  }

  //-------------------------------------------------------


  function __construct($rootDir)
  {
    if (!self::isMaildirPlusPlus($rootDir))
      throw new \RuntimeException("MaildirPlusPlus: directory structure not found. Perhaps use MaildirPlusPlus::create()");

    $this->rootDir = $rootDir;
    $this->useQuotas = file_exists("$rootDir/".self::QUOTA_FILENAME);

    $inbox = new Maildir($rootDir);

    $this->dirs = [
      ".Inbox" => $inbox,
    ];

    $files = scandir($rootDir);

    foreach ($files as $file)
    {
      if ($file == "." || $file == "..") continue;
      if (!is_dir("$rootDir/$file")) continue;
      if (substr($file,0,1) == ".")
      {
        $this->dirs[$file] = new Maildir("$rootDir/$file");
      }
    }
  }

  //-----------------------------------

  function __get($p)
  {
    switch ($p)
    {
      case "rootDir":
        return $this->rootDir;
      default:
        throw new \LogicException("MaildirPlusPlus: Property '$p' not supported");
    }
  }

  //-------------------------------------------------------

  function createFolder($folderName, Maildir $parent = null)
  {
    $dirName = $this->getDirNameFromFolder($folderName);
    if ($parent)
    {
      $s = ltrim(substr($parent->rootDir,strlen($this->rootDir)),"/");
      $dirName = $s.$dirName;
    }
    mkdir("$this->rootDir/$dirName");
    $this->dirs[$dirName] = Maildir::create("$this->rootDir/$dirName");
    return $this->dirs[$dirName];
  }

  //-------------------------------------------------------

  function getFolder($folderName)
  {
    $dirName = $this->getDirNameFromFolder($folderName);
    if (array_key_exists($dirName, $this->dirs))
      return $this->dirs[$dirName];
    return false;
  }

  //-------------------------------------------------------

  function deleteFolder($folderName)
  {
    if ($folderName == "Trash")
      throw new \LogicException("MaildirPlusPlus: Cannot delete Trash folder");

    $dirName = $this->getDirNameFromFolder($folderName);
    if (array_key_exists($dirName, $this->dirs))
    {
      Maildir::destroy("$this->rootDir/$dirName");
      rmdir("$this->rootDir/$dirName");
      unset($this->dirs[$dirName]);
    }
  }
  
  //-------------------------------------------------------

  function locate($name)
  {
    $dirName = $this->locateRaw($name);
    return $dirName ? $this->getFolderNameFromDir($dirName) : false;
  }

  // Returns the file dir where $name is stored.
  protected function locateRaw($name)
  {
    foreach ($this->dirs as $dirName=>$dir)
      if ($dir->exists($name))
        return $dirName;
    return false;
  }

  //-------------------------------------------------------

  function deliver($contents)
  {
    $name = Maildir::createName();
    $res = file_put_contents("$this->rootDir/tmp/$name", $contents);
    if ($res === false)
      throw new \RuntimeException("Failed to write data to '$rootDir' Maildir.");

    $filesize = 0;
    if ($this->useQuotas)
    {
      $filesize = filesize("$this->rootDir/tmp/$name");
      $newName = "$name,S=$filesize";

      $quotas = $this->getQuotas();
      $filesizes = $this->getSizeAndCount();
      if (
        ($quotas["size"] !== null && ($filesizes["size"] + $filesize) > $quotas["size"]) ||
        ($quotas["count"] !== null && ($filesizes["count"] + 1) > $quotas["count"])
      )
      {
        unlink("$this->rootDir/tmp/$name");
        return false;
      }
    }
    else
      $newName = $name;
    $res = rename("$this->rootDir/tmp/$name", "$this->rootDir/new/$newName");
    if ($res === false)
      throw new \RuntimeException("Failed to move file '$name' to 'new' in '$rootDir' Maildir.");

    $this->appendFilesize($filesize,1);

    return $newName;
  }

  //-------------------------------------------------------

  function move($name, $toFolder)
  {
    $fromFolder = $this->locate($name);
    if (!$fromFolder)
      throw new \RuntimeException("MaildirPlusPlus: File '$name' not found");
    $folder = $this->getFolder($fromFolder);
    $folder->touch($name);
    $fromPath = $folder->getPath($name);
    $toDir = $this->getFolder($toFolder)->rootDir;
    $toPath = "$toDir/cur/".basename($fromPath);

    rename($fromPath, $toPath);
  }

  //-------------------------------------------------------

  function trash($name)
  {
    $this->move($name, "Trash");
    $this->dirs[".Trash"]->setFlag($name, "T");
    $this->appendFilesize(-($this->getSize($name)),-1);
    
  }

  //-------------------------------------------------------

  function emptyTrash()
  {
    array_map("unlink",glob("$this->rootDir/.Trash/cur/*"));
    array_map("unlink",glob("$this->rootDir/.Trash/tmp/*"));
    array_map("unlink",glob("$this->rootDir/.Trash/new/*"));
  }

  //-------------------------------------------------------

  function setQuotas($sizeQuota, $countQuota)
  {
    if ($this->useQuotas)
    {
      $quotas = [];

      if ($sizeQuota != null)
        $quotas[] = $sizeQuota."S";

      if ($countQuota != null)
        $quotas[] = $countQuota."C";

      $space = $this->getSizeAndCountRaw();

      $name = MailDir::createName();
      file_put_contents("$this->rootDir/tmp/$name",implode(",",$quotas)."\n{$space["size"]} {$space["count"]}\n");
      rename("$this->rootDir/tmp/$name","$this->rootDir/".self::QUOTA_FILENAME);
    }
  }

  //-------------------------------------------------------

  // Recalculates the size and count for the maildirsize.
  function resetQuotas()
  {
    if ($this->useQuotas)
    {
      $quotas = $this->getQuotas();
      $this->setQuotas($quotas["size"], $quotas["count"]);
    }
  }

  //-------------------------------------------------------

  function getQuotas()
  {
    $quotas = ["size" => NULL, "count" => NULL];

    if ($this->useQuotas)
    {
      $fn = fopen("$this->rootDir/".self::QUOTA_FILENAME,"r");
      $line = fgets($fn);
      fclose($fn);

      $quotaT = explode(",", trim($line));
      foreach ($quotaT as $v)
      {
        if (substr($v, -1) == "S")
          $quotas["size"] = substr($v, 0, -1);
        if (substr($v, -1) == "C")
          $quotas["count"] = substr($v, 0, -1);
      }
    }
    return $quotas;
  }

  //-------------------------------------------------------

  // Adds a line to maildirsize.
  protected function appendFilesize($filesize, $filecount)
  {
    if ($this->useQuotas)
    {
      $fn = fopen("$this->rootDir/".self::QUOTA_FILENAME,"a");
      fwrite($fn, "$filesize $filecount\n");
      fclose($fn);
    }
  }

  //-------------------------------------------------------

  // Gets the space used by the files and the file count, using maildirsize,
  // if using quotas.
  function getSizeAndCount()
  {
    if (!$this->useQuotas)
      return $this->getSizeAndCountRaw();

    if (filesize("$this->rootDir/".self::QUOTA_FILENAME) > self::QUOTA_RECALC_SIZE)
      $this->resetQuota();

    $size = 0;
    $count = 0;

    $fn = fopen("$this->rootDir/".self::QUOTA_FILENAME,"r");
    $line = fgets($fn); // Skip the quota line

    while (!feof($fn))
    {
      $line = trim(fgets($fn));
      if ($line == "") continue;
      $items = explode(" ", $line);
      $size += $items[0];
      $count += $items[1];
    }
    return [ "size" => $size, "count" => $count ];
  }

  //-------------------------------------------------------

  protected function getDirNameFromFolder($folderName)
  {
    return ".".str_replace("/",".",$folderName);
  }

  //-------------------------------------------------------

  protected function getFolderNameFromDir($dirName)
  {
    return str_replace(".","/",substr($dirName,1));
  }

  //-------------------------------------------------------

  // Determine the total space and file count occupied by the files.
  protected function getSizeAndCountRaw()
  {
    $count = 0;
    $size = 0;

    foreach ($this->dirs as $dirName=>$dir)
    {
       // Trash folder is not included in quota.
      if ($dirName == ".Trash") continue;

      foreach ($dir->getNames() as $name)
      {
        ++$count;
        $size += $this->getSize($name);
      }
    }
    return ["size" => $size, "count" => $count];
  }

  //-------------------------------------------------------

  protected const SIZE_PATTERN = "/,S=([0-9]+)/";

  protected function getSize($name)
  {
    $dirName = $this->locateRaw($name);
    $filePath = $this->dirs[$dirName]->getPath($name);
    $matches = [];
    $res = preg_match(self::SIZE_PATTERN, $filePath, $matches);
    if ($res === false)
      throw new \RuntimeException("MaildirPlusPlus: preg_match failed");
    if ($res === 0)
      return filesize($filePath);
    else
      return $matches[1];
  }
}


//----------------------------------------------------------------------------
// Copyright (C) 2019 Jaypha.
// License: BSL-1.0
// Author: Jason den Dulk
//
