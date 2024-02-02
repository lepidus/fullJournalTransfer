# Full Journal Transfer
This plugin allows you to import and export all the content of a journal.

## Compatibility
The latest release of this plugin is compatible with the following PKP applications:

* OJS 3.3.0

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
Export a journal to XML by executing the command in the application's root directory:
```bash
php tools/importExport.php FullJournalImportExportPlugin export [xmlFileName] [journal_path]
```

#### Import
To import a journal from XML, execute the command in the application's root directory:
```bash
php tools/importExport.php FullJournalImportExportPlugin import [xmlFileName] [user_name]
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

**To Do**:

- Editor Decisions
- Stage Assignments
- Review Files
- Discussions

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
