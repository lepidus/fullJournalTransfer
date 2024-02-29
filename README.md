# Full Journal Transfer
This plugin allows you to import and export all the content of a journal.

_This plugin is not yet ready for general use. We are releasing preliminary versions for testing._

## Compatibility
The latest release of this plugin is compatible with the following PKP applications:

* OJS 3.3.0

**Note:** This plugin is designed for the export and import of journals within the same version of OJS. Example: from `3.3.0-16` to `3.3.0-16`. For best results, it is recommended to use OJS version 3.3.0-16 or newer.

## Plugin Download
To download the plugin, go to the [Releases page](https://github.com/lepidus/fullJournalTransfer/releases) and download the tar.gz package of the latest release compatible with your website.

## Installation
1. Enter the administration area of ​​your OJS website through the __Dashboard__.
2. Navigate to `Settings`>` Website`> `Plugins`> `Upload a new plugin`.
3. Under __Upload file__ select the file __fullJournalTransfer.tar.gz__.
4. Click __Save__ and the plugin will be installed on your website.

## Instructions for use

### Command line

#### Export
Export a journal to a tar.gz file containing the xml and file directory by executing the command in the application's root directory:
```bash
php tools/importExport.php FullJournalImportExportPlugin export [targzFileName] [journal_path]
```

#### Import
To import a journal from tar.gz file, execute the command in the application's root directory:
```bash
php tools/importExport.php FullJournalImportExportPlugin import [targzFileName] [user_name]
```

## Imported/Exported Journal Content

**Using PKP native import/export**:

- Users and User Roles
- Articles
- Issues

**Added**:

- Journal data
- Navigation Menus
- Plugins Configs
- Sections
- Review Forms
- Review Assignments
- Review Rounds
- Stage Assignments
- Editor Decisions

**To Do**:

- Review Files
- Discussions
- Metrics

## Running Tests

### Unit Tests

To execute the unit tests, run the following command from root of the PKP Application directory:
```bash
lib/pkp/lib/vendor/phpunit/phpunit/phpunit -c lib/pkp/tests/phpunit-env2.xml plugins/importexport/fullJournalTransfer/tests
```

# Credits
This plugin was idealized and sponsored by the Brazilian Institute of Information in Science and Technology (IBICT) for OJS version 2.x.

Funding for version 3.3 comes from the Federal University of São Paulo (Unifesp) and Federal University of Recôncavo da Bahia (UFRB).

Developed by Lepidus Tecnologia.


# License
This plugin is licensed under the GNU General Public License v3.0

Copyright (c) 2014-2024 Lepidus Tecnologia
