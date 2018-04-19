# snmp
PHP Class for net-snmp commands

Requirements
------------

Requires: net-snmp-utils

For RedHat/CentOS 6, 7

```shell
[root@centos ~]# yum install net-snmp-utils
```

For Debian/Ubuntu

```shell
[root@debian ~]# apt-get install snmp
```

Installation with Composer
--------------------------

```shell
$ composer require dcortez/snmp
```

Usage
-----

Example php file.

```php
// test-snmp.php
require 'vendor/autoload.php';

use Dcortez\Snmp;

$snmp = new Snmp('127.0.0.1', 'public', '2c', '5'); // <ip>, <community>, <snmp_version>, <timeout>
print_r($snmp->get('.1.3.6.1.2.1.1.1.0'));
```

Test run php file.

```shell
$ php test-snmp.php
Array
(
    [.1.3.6.1.2.1.1.1.0] => Linux test.example.com
)
```
