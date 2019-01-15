<?php
//----------------------------------------------------------------------------
//
//----------------------------------------------------------------------------

namespace Jaypha\Maildir;

class MaildirPlusPlus extends Maildir
{
  protected $dirs;

  static function create($parentDir)
  {
    if (!is_dir($parentDir))
      throw new \RuntimeException("'$parentDir' is not a directory");

    Maildir::create($parentDir);
    mkdir("$parentDir/.Sent");
    Maildir::create("$parentDir/.Sent");
    mkdir("$parentDir/.Trash");
    Maildir::create("$parentDir/.Trash");
    mkdir("$parentDir/.Drafts");
    Maildir::create("$parentDir/.Drafts");

    return new MaildirPlusPlus($parentDir);
  }

  static function destroy($parentDir)
  {
    Maildir::destroy($parentDir);

    array_map(function ($fn) use ($parentDir) {
      if (is_dir($fn) && $fn != "$parentDir/." && $fn != "$parentDir/..")
      {
        Maildir::destroy("$fn");
        rmdir("$fn");
      }
    }, glob("$parentDir/.*"));
  }

  function __construct($parentDir)
  {
    parent::__construct($parentDir);
    $this->dirs = [
      ".Inbox" => $this,
    ];

    $files = scandir($parentDir);

    foreach ($files as $file)
    {
      if ($file == "." || $file == "..") continue;
      if (!is_dir("$parentDir/$file")) continue;
      if (substr($file,0,1) == ".")
      {
        $this->dirs[$file] = new Maildir("$parentDir/$file");
      }
    }
  }

  function createFolder($folderName, Maildir $parent = null)
  {
    $dirName = $this->getDirNameFromFolder($folderName);
    if ($parent)
    {
      $s = ltrim(substr($parent->parentDir,strlen($this->parentDir)),"/");
      $dirName = $s.$dirName;
    }
    mkdir("$this->parentDir/$dirName");
    $this->dirs[$dirName] = Maildir::create("$this->parentDir/$dirName");
    return $this->dirs[$dirName];
  }

  function getFolder($folderName)
  {
    $dirName = $this->getDirNameFromFolder($folderName);
    if (array_key_exists($dirName, $this->dirs))
      return $this->dirs[$dirName];
    else
      throw new \RuntimeException("Folder '$folderName' doesn't exist");
  }

  function deleteFolder($folderName)
  {
    $dirName = $this->getDirNameFromFolder($folderName);
    if (array_key_exists($dirName, $this->dirs))
    {
      Maildir::destroy("$this->parentDir/$dirName");
      rmdir("$this->parentDir/$dirName");
      unset($this->dirs[$dirName]);
    }
  }
  
  function move($fromFolder, $name, $toFolder)
  {
    $folder = $this->getFolder($fromFolder);
    $folder->makeCurrent($name);
    $fromDir = $folder->parentDir;
    $filename = $folder->findFilename($name);
    $folder = $this->getFolder($toFolder);
    $toDir = $folder->parentDir;
    rename("$fromDir/cur/$filename", "$toDir/cur/$filename");
  }

  function trash($fromFolder, $name)
  {
    $this->move($fromFolder, $name, "Trash");
    $this->dirs[".Trash"]->setFlag($name, "T");
  }

  function emptyTrash()
  {
    array_map("unlink",glob("$this->parentDir/.Trash/cur/*"));
    array_map("unlink",glob("$this->parentDir/.Trash/tmp/*"));
    array_map("unlink",glob("$this->parentDir/.Trash/new/*"));
  }

  protected function getDirNameFromFolder($folderName)
  {
    return ".".str_replace("/",".",$folderName);
  }

  protected function getFolderNameFromDir($dirName)
  {
    return str_replace(".","/",substr($dirName,1));
  }
  
}


//----------------------------------------------------------------------------
// Copyright (C) 2019 Jaypha.
// License: BSL-1.0
// Author: Jason den Dulk
//
