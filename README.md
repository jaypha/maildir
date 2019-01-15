# Maildir

Written by Jason den Dulk

A class to read and write files using the Maildir format.

Maildir is a common format used to store emails on a computer.

The Maildir spec can be found at https://cr.yp.to/proto/maildir.html.

The Maildir++ spec can be found at  http://www.courier-mta.org/maildir.html.

## Requirements

PHP v5.4.0 or greater.

## Installation

```
composer require jaypha/maildir
```

## API

### class Maildir

#### How filenames are used in this class

In this class, the name returned by save, and accepted by other functions  
do not include the flag settings. So while the actual filename on the disk may  
change as flags are set and cleared, the name used with this class does not.

`Maildir Maildir::create($parentDir)`

Create a Maildir. `$parentDir` must exists. Will create the subdirectories and
return a Maildir instance.

`void Maildir::destroy($parentDir)`

destroys a maildir structure. Removes the cur, new and tmp directories. Does not
affect anything else.

`__construct($parentDir)`

Create a Maildir instance for $parentDir. The necessary subdirectories must
already exist.

`string save(string $contents)`

Save `$contents` into a Maildir file. Returns the name of the file.

`bool isNew(string $name)`

Returns true if the file for `$name` has not yet been transferred to 'cur'.

`bool exists(string $name)`

Returns true if the file for `$name` exists either in cur or new.

`string fetch(string $name)`

Fetches the contents of the file. The file is moved to the 'cur' directory, but
the 'S' flag is not set.

`void delete(string $name)`
Removes the file for `$name`. Will remove the file from either the 'new' or 'cur'
directory

`bool hasFlag(string $name, string $flag)`
Returns true if the file has the flag set.

`void setFlag(string $name, string $flag)`
Sets the flag

`void clearFlag(string $name, string $flag)`
Clears the flag

### class MaildirPlusPlus

#### How folder names are used in this class.

Folder names use the logical format given in the Maildir++ specification,
utilizing the '/' separator.

`MaildirPlusPlus MaildirPlusPlus::create($parentDir)`

Creates a Maildir++ directory structure. Creates folders for Inbox, Sent, Drafts
and Trash.

`MaildirPlusPlus::destroy($parentDir)`

Destorys the Maildir++ structure. Removes all maildir folders.

`__construct($parentDir)`

Create a MaildirPlusPlus instance for $parentDir. This creates Maildir instances
for each folder as well as the inbox.

`Maildir createFolder($folderName, Maildir $parent = null)`

Create a maildir folder for `$foldername`. If `$parent` is provided, it will be used
as the logical "parent" of the new folder.

`Maildir getFolder($folderName)`

Get the folder for `$folderName`.

`void deleteFolder($folderName)`

Removes the folder `$folderName`.

`void move($fromFolder, $name, $toFolder)`

Moves a file from `$fromFolder` to `$toFolder`. The file will always be placed
in the cur directory of the destination folder.

`void trash($fromFolder, $name)`

Moves a file from `$fromFolder` to the Trash folder. Also sets the "T" flag.

`void emptyTrash()`

Deletes all files in the Trash folder.


## Change Log

#### Version 0.3.0

- Added destroy function to Maildir
- Added maildir++ support.

#### Version 0.2.0

- Added delete function
- Removed trash and emptyTrash as they, strictly, are not a part of the spec.

## License

Copyright (C) 2017-8 Jaypha.  
Distributed under the Boost Software License, Version 1.0.  
See http://www.boost.org/LICENSE_1_0.txt

