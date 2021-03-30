iET Icinga Web 2 module
=======================

Icinga Web 2 custom module bundling various iET-related functionality. Usually,
iET is getting a lot of customization, so please do not expect this module to
work for your setup out of the box.

The main purpose of this module is to implement various Hooks.

Icinga Director Import Source
-----------------------------

iET provides a CMDB and is therefore a perfect candidate for an Import Source to
hooked into the [Icinga Director](https://github.com/Icinga/icingaweb2-module-director).

Icinga Host/Service-Actions
---------------------------

This module implements the `Host/ServiceAction` hook provided by the Icinga
Monitoring module. That way it allows to create iET tickets, called Operational
Requests in the environment this has been developed for.

Tickets are created on demand. When you're doing so, the related Icinga Problem
will be acknowledged, and a reference to the created Issue will be placed into
the related Acknowledgement Comment.

Eventtracker EventAction
------------------------

Similar to the above, this module implements the `EventAction` into the
EventTracker, mainly to create Operational Requests. Once an OR has been created,
the Ticket Reference is populated to the related EventTracker Issue.

Icinga Ticket Hook
------------------

Last but not least, this module implements the generic `ticket` Hook for Icinga
Web 2. This means that comments containing references to Operation Requests in
iET will be transformed into links back to iET.

As iET is shipped as a Windows application. This means that links pointing to
iET will make use of the `iet://`-scheme. That way a click will open the referred
ticket directly in your Winodws application.

Configuration
-------------

Configuration takes place in `config.ini` (generic settings) and `instances.ini`
(iET Instances - connection settings).

### iET Instances

Sample config for `[ICINGAWEB_CONFIGDIR]/modules/iet/instances.ini`:

```ini
[production]
host = "iet.example.com"
webservice = "https://iet.example.com/iETWebservices"
namespace = "http://www.example.com/iETWebservices"
; Ticket Url, defaults to iet://<host>/displayrecord?or=%d
; ticket_url = https://iet.example.com/weblink/Open/Form/IT%20INQUIRY%20MANAGEMENT/sv_inquiry.inquiry_id/<ticket>
username = "icinga"
password = "***"
; for certificate-based client authentication against the iET Web Service:
; cert = "/etc/ssl/certs/client.example.com.crt"
; key = "/etc/ssl/private/client.example.com.key"

[test]
host = "iet-test.example.com"
webservice = "https://iet-test.example.com/iETWebservices"
namespace = "http://www.example.com/iETWebservices"
; username = "ietws"
; password = "ietws"
ignore_certificate = true
```

### Global settings

Global default settings are to be found in `[ICINGAWEB_CONFIGDIR]/modules/iet/config.ini`.
The `[implementation]` section allows to specify a specific Web Form implementation.
For historic reasons this will default to "Creating Operational Requests" if none
has been configured.

The `[defaults]` section allows to pre-fill form values. Placeholders allow to
pick properties from related (Icinga) objects.

The `[links]` section is valid only for the `CreateOR` implementation and allows
to ship a bunch of links with newly created tickets.

```ini
[implementation]
; ticket_form = "MinimalMonitoringTicket"

[defaults]
sourcesystem = "Some Name"
fe = "{icinga.vars.my_fe}"
service = "AB02 My Service"

[links]
Event Source = "https://icinga.example.com/icingaweb2/eventtracker/issue?uuid={uuid}"
DokuWiki = "{attributes.my_dokuwiki}"
Knowledge Base = "{attributes.my_knowledge_base}"
```

Feedback
--------

Please note that this module has been implemented without any documentation.
Access to the Windows Application with permissions to run sample requests has
been granted close to the end of the project.

Therefore, please do not consider this a good API implementation. It definitively
isn't. However, it serves it's purpose and runs fine. In case you're running iET
and want to see this improved and/or customized to also fit your needs, please
to not hesitate to ask.
