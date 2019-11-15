iET Icinga Web 2 module
=======================

Icinga Web 2 custom module bundling various iET-related functionality. Usually,
iET is getting a lot of customization, so please do not expect this module to
work for your setup out of the box.

Sample config for `[ICINGAWEB_CONFIGDIR]/modules/iet/instances.ini`:

```ini
[production]
host = "iet.example.com"
webservice = "https://iet.example.com/iETWebservices"
namespace = "http://www.example.com/iETWebservices"
username = "icinga"
password = "***"
cert = "/etc/ssl/certs/client.example.com.crt"
key = "/etc/ssl/private/client.example.com.key"

[test]
host = "iet-test.example.com"
webservice = "https://iet-test.example.com/iETWebservices"
namespace = "http://www.example.com/iETWebservices"
; username = "ietws"
; password = "ietws"
ignore_certificate = true
```
