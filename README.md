# Full Journal Transfer
This plugin allows you to import and export all the content of a journal.

## Compatibility
The latest release of this plugin is compatible with the following PKP applications:

* OPS 3.3.0

## Plugin Download
To download the plugin, go to the [Releases page](https://github.com/lepidus/fullJournalTransfer/releases) and download the tar.gz package of the latest release compatible with your website.

## Installation
1. Enter the administration area of ​​your OJS/OPS website through the __Dashboard__.
2. Navigate to `Settings`>` Website`> `Plugins`> `Upload a new plugin`.
3. Under __Upload file__ select the file __fullJournalTransfer.tar.gz__.
4. Click __Save__ and the plugin will be installed on your website.

## Instructions for use

### Command line

#### Export
Exports a journal to xml running the command in application root:
```bash
php tools/importExport.php FullJournalImportExportPlugin export [xmlFileName] [journal_path]
```

#### Import
Imports a journal from xml running the command in application root:
```bash
php tools/importExport.php FullJournalImportExportPlugin import [xmlFileName]
```

## Running Tests

### Unit Tests

To execute the unit tests, run the following command from root of the PKP Appplication directory:
```bash
lib/pkp/lib/vendor/phpunit/phpunit/phpunit -c lib/pkp/tests/phpunit-env2.xml plugins/importexport/fullJournalTransfer/tests
```

# License
__This plugin is licensed under the GNU General Public License v3.0__

__Copyright (c) 2014-2023 Lepidus Tecnologia__