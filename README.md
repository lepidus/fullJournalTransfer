Full Journal Transfer plugin for OJS 2.4.6
=======

About
-----
This is an import/export plugin for OJS 2.4.6 for transfering journals among OJS portals. The content of a journal is transfered in the process, including articles in review, submitted articles, email and event log.

System Requirements
-------------------
This plugin requires OJS 2.4.6 in both OJS instances (the one importing and the one exporting). 

Installation
------------
- Copy the plugin contents in the plugins/importexport folder

Usage
------------
The recommended way for use this journal is through the command line, using the importExport.php script available in the tools folder of the OJS install directory.

For exporting:
    > php tools/importExport.php FullJournalImportExportPlugin export \<filename\>.tar.gz \<journalPath\>

For importing:
    > php tools/importExport.php FullJournalImportExportPlugin import \<filename\>.tar.gz

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
Please email the authors for support, bugfixes, or comments.
