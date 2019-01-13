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

### How filenames are used in this class

In this class, the name returned by save, and accepted by other functions  
do not include the flag settings. So while the actual filename on the disk may  
change as flags are set and cleared, the name used with this class does not.

## TODO

Create a class for Maildir++.

## Change Log

#### Version 0.2.0

- Added delete function
- Removed trash and emptyTrash as they, strictly, are not a part of the spec.

## License

Copyright (C) 2017-8 Jaypha.  
Distributed under the Boost Software License, Version 1.0.  
See http://www.boost.org/LICENSE_1_0.txt

