# jsHashes [![Build Status](https://travis-ci.org/h2non/jshashes.svg)](https://travis-ci.org/h2non/jshashes) [![NPM version](https://img.shields.io/npm/v/jshashes.svg)](https://www.npmjs.com/package/jshashes) 

`jshashes` is lightweight library implementing the most extended [cryptographic hash function](http://en.wikipedia.org/wiki/Cryptographic_hash_function) algorithms in pure JavaScript (ES5 compliant).

The goal is to provide an dependency-free, fast and reliable solution for hash algorithms for both client-side and server-side JavaScript environments. 
The code is fully compatible with the ECMAScript 5 specification and is used in production in browsers and [node.js](http://nodejs.org)/[io.js](http://iojs.org)

If you are looking for a low-level performance library for the server-side, note that node.js/io.js provides its own native module: [`crypto`](http://nodejs.org/api/crypto.html)

## Supported hash algorithms

* `MD5` (<http://www.ietf.org/rfc/rfc1321.txt>)
* `SHA1` (<http://www.itl.nist.gov/fipspubs/fip180-1.htm>)
* `SHA256` (<http://csrc.nist.gov/publications/fips/fips180-2/fips180-2.pdf>)
* `SHA512` (<http://csrc.nist.gov/publications/fips/fips180-2/fips180-2.pdf>)
* `HMAC` (<http://www.ietf.org/rfc/rfc2104.txt>)
* `RIPEMD-160` (<http://homes.esat.kuleuven.be/~bosselae/ripemd160.html>)

**Aditional functionalities**

* `Base64 encoding/decoding` (<http://tools.ietf.org/html/rfc3548>)
* `CRC-32 calculation`
* `UTF-8 encoding/decoding`

## Environments

- Browsers (ES3)
- node.js/io.js (all versions)
- Rhino
- RingoJS

## Usage

Each algorithm has its respective own instantiable `object`. Here you can see an example of how to create a new instance for each one:

```javascript
// new MD5 instance
var MD5 = new Hashes.MD5
// new SHA1 instance
var SHA1 = new Hashes.SHA1
// new SHA256 instance
var SHA256 =  new Hashes.SHA256
// new SHA512 instace
var SHA512 = new Hashes.SHA512
// new RIPEMD-160 instace
var RMD160 = new Hashes.RMD160
```

An example of how to generate an hexadecimal-based hash encoding for each algorithm:

```javascript
// sample string
var str = 'Sample text!'
// output to console
console.log('MD5: ' + MD5.hex(str))
console.log('SHA1: ' + SHA1.hex(str))
console.log('SHA256: ' + SHA256.hex(str))
console.log('SHA512: ' + SHA512.hex(str))
console.log('RIPEMD-160: ' + RMD160.hex(str))
```

### Browsers

This is a simple implementation for a client-side environment:

```html
<html>
<head>
<script type="text/javascript" src="src/hashes.js"></script>
<script type="text/javascript">
// sample string
var str = 'This is a sample text!'
// new MD5 instance and hexadecimal string encoding
var MD5 = new Hashes.MD5().hex(str)
// output into DOM
document.write('<p>MD5: <b>' + MD5 + '</b></p>')
</script>
</head>
<body>
</body>
</html>
```

### node.js / io.js

```javascript
// require the module
var Hashes = require('jshashes')
// sample string
var str = 'This is a sample text!'
// new SHA1 instance and base64 string encoding
var SHA1 = new Hashes.SHA1().b64(str)
// output to console
console.log('SHA1: ' + SHA1)
```

### Command-line interface

You can use the simple command-line interface to generate hashes.

```bash
$ hashes sha1-hex This is a sample string
> b6a8501d8a70e74e1dc12a6082102622fdc719bb

# or with quotes
$ hashes sha1-hex "This is a sample string"
> b6a8501d8a70e74e1dc12a6082102622fdc719bb
```

For more information about the options supported, type:

```bash
$ hashes -h
```

### Installation

Via [npm](https://npmjs.org)

```
$ npm install jshashes
```

Via [Bower](http://bower.io/):
```
$ bower install jshashes
```

Via [Component](https://github.com/component/component):
```
$ component install h2non/jshashes
```

Or loading the script directly:
```
http://cdn.rawgit.com/h2non/jsHashes/master/hashes.js
```

## Public methods

Each algorithm `class` provides the following public methods:

* `hex(string)` - Hexadecimal hash encoding from string.
* `b64(string)` - Base64 hash encondig from string.
* `any(string,encoding)` - Custom hash algorithm values encoding.
* `hex_hmac(key,string)` - Hexadecimal hash with HMAC salt key.
* `b64_hmac(key,string)` - Base64 hash with HMAC salt key.
* `any_hmac(key,string,encoding)` - Custom hash values encoding with HMAC salt key support.
* `vm_test()` - Simple self-test to see is working. Returns `this` Object.
* `setUpperCase(boolean)` - Enable/disable uppercase hexadecimal returned string. Returns `this` Object.
* `setPad(string)` - Defines a custom base64 pad string. Default is '=' according with the RFC standard. Returns `this` Object.
* `setUTF8(boolean)` - Enable/disable UTF-8 character encoding. Returns `this` Object.

## Hash encoding formats supported

* Hexadecimal (most extended)
* Base64
* Custom hash values `any()` method

## Benchmark

Node.js 0.6.18 running on a VPS Intel I7 930 with 512 MB of RAM (see `server/benchmark.js`)

```javascript
Simple benchmark test generating 10000 hashes for each algorithm.
String: "A0gTtNtKh3RaduBfIo59ZdfTc5pTdOQrkxdZ5EeVOIZh1cXxqPyexKZBg6VlE1KzIz6pd6r1LLIpT5B8THRfcGvbJElwhWBi9ZAE"

* MD5
** Done in: 205 miliseconds
* SHA1
** Done in: 277 miliseconds
* SHA256
** Done in: 525 miliseconds
* SHA512
** Done in: 593 miliseconds
* RMD160
** Done in: 383 miliseconds
```

See `client/benchmark.html` for client-side.

## Notes

* Don't support checksum hash for files on the server-side, only strings-based inputs are supported.
* It has not been planned to include support for more hash algorithms.
* The goal is to provide the same JavaScript code in both server and client side, so it isn't planned to improve it in other ways.
* Only Node.js server-side was tested, so with minimal changes, you can setup `jsHashes` in other server-side JS environment.

## Changelog

* `1.0.7`
  - Merge #37: fix terminator statement token.
* `1.0.6`
  - Fix #34: options `pad` typo.
* `1.0.4`
  - Fix CLI script call error when use it from Bash
  - Added CLI usage example
* `1.0.3`
  - Important bugfixes to UTF-8 encoding (broken in 1.0.2) and the RIPEMD-160 hash (broken in 1.0.1). (gh #6)
  - New test suite for hashes, CRC32, and hmac; run with 'npm test' in node.
  - Fixed global variable leaks. (gh #13)
  - CRC32 will now always return positive values. (gh #11)
  - Added package version property to the exposed Hashes Object
  - Updated CLI script utility supporting all algorithms (see bin/hashes)
  - Fixed UTF-8 encoding/decoding error (if input parameter is undefined or invalid)
* `1.0.2`
  - Performance improvements and minimal refactor (length property caching, literal notation)
  - Available from Bower package manager
* `1.0.1`
  - Refactoring (hoisting, coercion, removed redundant functions, scoping, restructure...)
  - Performance improves
  - JSLint validation (except bitwise operators)
  - Now the library can be used like a AMD CommonJS module
  - Updated documentation
  - New folders structure
  - Added closure compiled and minimized library version
  - Available from Jam package manager
* `0.1.5b`
  - Added index.js for easy call the module in Node.js
  - Updated documentation
* `0.1.4b`
  - Now declaring objects using Literal Notation.
  - Solved sintax errors on minimized version (jshashes.min.js)
  - Added benchmark test and sample
* `0.1.3b`
  - Starting non-redundancy code refactorization
  - Added `Helpers` Object with some global functions
  - Added native support for Base64 provided as `class`
  - Added CRC-32 calculation support
  - Added URL encode/decode helpers functions
* `0.1.2b`
  - SHA1 error fixed.
  - General code changes (renaming classes, private methods, new methods...).
  - Changing library namespace to 'Hashes'.
  - Starting code documentation.
  - Added new examples of how to use.
* `0.1.1b`
  - Minimal library improvements.
  - There has been added some samples, like how to use it and support for NPM package.
* `0.1.0b`
  - First release: the code is stable, but the library is still beta and must be improved and documented.

## TODO

* Performance benchmarking

## Authors

### Library author

* [Tomas Aparicio](https://github.com/h2non/)

### Original algorithm authors

* [Paul Johnston](http://pajhome.org.uk/crypt/md5/)
* Angel Marin (SHA256)
* Jeremy Lin (RIPEMD-160)

### Other contributors

* [C. Scott Ananian](https://github.com/cscott)
* Greg Holt
* Andrew Kepert
* Ydnar
* Lostinet

## License

jsHashes is released under `New BSD` license. See `LICENSE` file.

## Issues

Feel free to report any issue you experiment via Github <https://github.com/h2non/jsHashes/issues>.
