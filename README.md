Full Journal Transfer plugin for OJS 2.4.6+
=======

About
-----
This is an import/export plugin for OJS 2.4.6 or above (the last tested was 2.4.8-4) for transfering journals among OJS portals. The content of a journal is transfered in the process, including articles in review, submitted articles, email and event log.

System Requirements
-------------------
This plugin requires OJS 2.4.6 or above. OJS 3.x is not supported.

Although the plugin supports several versions, both the source and destination OJS must be the same to use it.

Example:
Origin and destination 2.4.8-1 version, supported
Origin and destination 2.4.7 version, supported
Origin 2.4.8-4 version and destination 2.4.7 version, not supported

Installation
------------
- Copy the plugin contents in the plugins/importexport folder

Usage
------------
The recommended way to use this plugin is through the command line, using the importExport.php script available in the tools folder of the OJS install directory.

For exporting:

    > php tools/importExport.php FullJournalImportExportPlugin export <filename>.tar.gz <journalPath>

For importing:

    > php tools/importExport.php FullJournalImportExportPlugin import <filename>.tar.gz

    > php tools/rebuildSearchIndex.php

License
-------
This software is released under the GNU General Public License.

See the file docs/COPYING included with this distribution for the terms of this license.

Credits
--------
This plugin was idealized and sponsored by Instituto Brasileiro de Informação em Ciência e Tecnologia (IBICT).

Developed by Lepidus Tecnologia.

Contact/Support
---------------
http://forum.pkp.sfu.ca/c/questions (english)

http://forum.ibict.br/c/ojs-seer (portuguese)
