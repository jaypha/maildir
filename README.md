# Maildir

Written by Jason den Dulk

Classes to read and write files using the maildir and maildir++ formats.

Maildir is a common format used to store emails on a computer. Maildir++ is an
extension to Maildir to support multiple folders and quotas.

The Maildir spec can be found at https://cr.yp.to/proto/maildir.html.

The Maildir++ spec can be found at  https://www.courier-mta.org/imap/README.maildirquota.html.

**Breaking Changes** in verison 0.6.0. See change log.

*This version is the final addition to be made. If no bug reports are made in a year, I will upgrade to 1.0.0.*

### Requirements

PHP v5.4.0 or greater.

### Installation

```
composer require jaypha/maildir
```

### Using Quotas

To utilize quotas, create the maildir++ subtree with `$useQuotas` set to true. For quotas to
work properly, you should stick to the `MaildirPlusPlus` methods `deliver()`, `move()`, `trash()`
and emptyTrash(). The Maildir `save()` and `delete()` methods will still work, but the quotas
file will not be updated until resetQuotas is called.

Remeber that the maildir++ specification recommends that the quotas file be reset roughly
every fifteen minutes. Do so by calling `resetQuotas()`.

## API

### class Maildir

#### How filenames are used in this class

In this class, the name returned by `save()`, and accepted by other functions
do not include the flag settings. So while the actual filename on the disk may
change as flags are set and cleared, the name used with this class does not.

#### Static Methods

`Maildir Maildir::create(string $rootDir)`

Create a Maildir. `$rootDir` must exist. Will create the subdirectories and
return a Maildir instance.

`void Maildir::destroy(string $rootDir)`

Destroys a maildir structure. Removes the cur, new and tmp directories. Does not
affect anything else.

`bool MailDir::isMaildir($dir)`

Tests if the given `$dir` contains a maildir structure

`string MailDir::createName()`

Creates a suitable unique name using the guidelines set in the maildir specification.

#### Properties

`string $rootDir` (read only)

The root directory of the maildir subtree

#### Methods

`__construct(string $rootDir)`

Create a Maildir instance for `$rootDir`. The necessary subdirectories must
already exist.

`string save($contents)`

Save `$contents` into a Maildir file. `$contents` can be a string or a resource (stream). Returns the name of the file.

`void touch(string $name)`

Forces the file for `$name` to be moved into the 'cur' directory.

`bool isNew(string $name)`

Returns true if the file for `$name` has not yet been transferred to 'cur'.

`bool exists(string $name)`

Returns true if the file for `$name` exists either in cur or new.

`string fetch(string $name)`

Fetches the contents of the file. The file is moved to the 'cur' directory, but
the 'S' flag is not set.

`resource fetchAsStream(string $name)`

Fetches the contents of the file as a stream resource, suitable for large files. The
file is moved to the 'cur' directory, but the 'S' flag is not set.

`mixed getPath(string $name)`

Gets the file path for `$name`. Returns `false` if `$name` is not in the maildir.

`void delete(string $name)`

Removes the file for `$name`. Will remove the file from either the 'new' or 'cur'
directory

`bool hasFlag(string $name, string $flag)`

Returns true if the file has the flag set.

`void setFlag(string $name, string $flag)`

Sets the flag

`void clearFlag(string $name, string $flag)`

Clears the flag

`generator getNames()`

Yields the list of names stored in the maildir.

`generator getFiles()`

Yields the list of files stored in the maildir, indexed by name.

`generator getStreams()`

Like `getFiles()`, but yields streams rather that file contents.

### class MaildirPlusPlus

#### How folder names are used in this class.

Folder names use the logical format given in the Maildir++ specification,
utilizing the '/' separator.

#### Static Methods

`MaildirPlusPlus MaildirPlusPlus::create(string $rootDir, $useQuotas = false)`

Creates a Maildir++ directory structure. Creates folders for Inbox, Sent, Drafts
and Trash.

`MaildirPlusPlus::destroy(string $rootDir)`

Destorys the Maildir++ structure. Removes all maildir folders.

#### Properties

`string $rootDir` (read only)

The root directory of the maildir++ subtree

#### Methods

`__construct(string $rootDir)`

Create a MaildirPlusPlus instance for `$rootDir`. This creates Maildir instances
for each folder as well as the inbox.

`Maildir createFolder(string $folderName, Maildir $parent = null)`

Create a maildir folder for `$foldername`. If `$parent` is provided, it will be used
as the logical "parent" of the new folder.

`mixed getFolder(string $folderName)`

Get the folder for `$folderName`. Returns a `Maildir` if found, `false` otherwise.

`void deleteFolder(string $folderName)`

Removes the folder `$folderName`.

`mixed locate(string $name)`

Retrieves the name of the folder the file is currently lcoated in. Returns `false`
if file not found.

`mixed deliver($contents)`

Attempts to save a file to subtree. Returns the name if successful. Returns false if
the quota would be exceeded.

`void move(string $name, string $toFolder)`

Moves a file to the `$toFolder`. The file will always be placed
in the cur directory of the destination folder.

`void trash(string $name)`

Moves file to the Trash folder. Also sets the "T" flag.

`void emptyTrash()`

Deletes all files in the Trash folder.

`void setQuotas(mixed $sizeQuota, mixed $countQuota)`

Sets the quota for total file size and file count. If either is set to `null`, no quota
will be set for that parameter.

`array getQuotas()`

Returns the quotas set by setQuotas as an associative array, 'size' for size quota,
'count' for count quota. `null` value indicates no quota set.

`void resetQuotas()`

Re-calculates the total size and count of all the (non-trashed) files in the subtree, and
writes it to maildirsize.
Accordng to the maildir++ specification. This should be done roughly every fifteen minutes.
If your program does something like that, this is the method to call.

`array getSizeAndCount()`

Returns the total file size and file count for the subtree, using the maildirsize file if
quotas are being used

## Change Log

#### Version 0.6.0

**Breaking Changes**

- `MaildirPlusPlus` no longer inherits from `Maildir`. Use `getFolder("Inbox")` to get the inbox
  maildir.
- `Maildir::makeCurrent()` has been renamed to `touch()` and is now public.
- `$parentDir` has been changed to `$rootDir`.
- Deprecated function arguments are now dropped.

**Other changes**

- **Quotas support!** Added quota support functions to MaildirPlusPlus.
- Added isMaildir, rootDir, getPath, fetchAsStream and getStreams to Maildir.
- Maildir::createName is now public.

**Notes**

I decided to make MaildirPlusPlus no longer inherit from Maildir because I believe that
MaildirPlusPlus represents a collection of Maildirs rather than a Maildir in its own right. While
it could technichally masquerade as a Maildir; semantically, it doesn't really have an 'is_a'
relationship with Maildir. So I removed it out of a desire to engage in good programming practice.

#### Version 0.5.0

- Added getNames and getFiles to allow iteration over the maildir contents.

#### Version 0.4.1

- Removed string constraint on save to allow passing streams.

#### Version 0.4.0

- Added locate function
- Move and trash no longer require an origin folder name.

#### Version 0.3.0

- Added destroy function to Maildir
- Added maildir++ support.

#### Version 0.2.0

- Added delete function
- Removed trash and emptyTrash from Maildir.

## License

Copyright (C) 2018-21 Jaypha.  
Distributed under the Boost Software License, Version 1.0.  
See http://www.boost.org/LICENSE_1_0.txt
