# Maildir

Written by Jason den Dulk

A class to read and write files using the Maildir format.

Maildir is a common format used to store emails on a computer.

The Maildir spec can be found at https://cr.yp.to/proto/maildir.html

## Requirements

PHP v5.4.0 or greater.

## Installation

```
composer require jaypha/maildir
```

## API

### class Maildir

`Maildir Maildir::create($parentDir)`

Create a Maildir. `$parentDir` must exists. Will create the subdirectories and  
return a Maildir instance.

`__construct($parentDir)`

Create a Maildir instance for $parentDir. The necessary subdirectories must
already exist.

`string save(string $contents)`
Save `$contents` into a Maildir file. Returns the name of the file.

`bool isNew(string $name)`
Returns true if the file for $name has not yet been transferred to 'cur'.

`bool exists(string $name)`
Returns true if the file for $name exists either in cur or new.

`string fetch(string $name)`
Fetches the contents of the file. The file is moved to the 'cur' directory, but  
the 'S' flag is not set.

`void trash(string $name)`
Sets a file to be deleted.

`void emptyTrash()`
Deletes all files marked with the 'T' flag.

`string getFlags($name)`
Gets all the flags for a file.

`bool hasFlag($name, $flag)`
Returns true if the file has teh flag set.

`bool setFlag($name, $flag)`
Sets the flag

`bool clearFlag($name, $flag)`
Clears the flag

## TODO

Create a class for Maildir++.

## License

Copyright (C) 2017-8 Jaypha.  
Distributed under the Boost Software License, Version 1.0.  
See http://www.boost.org/LICENSE_1_0.txt

