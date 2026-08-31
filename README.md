**English** | [Português Brasileiro](/docs/README-pt_BR.md)

# Full Journal Transfer
This plugin allows you to import and export all the content of a journal.

## Compatibility
The latest release of this plugin is compatible with the following PKP applications:

* OJS 3.4.0

**Note:** Packages can only be transferred between installations on the same OJS 3.4.0 version line.

## Requirements

- PHP >= 8.0.2
- php-mbstring
- php-intl
- php-xml

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

**Obs**.: Journals containing substantial data will consume a large memory resources. In such instances, employ the PHP parameter `-d memory_limit=-1` during import/export operations.

## Troubleshooting

This plugin uses features from the users and the native import/export plugin. If the execution does not work as expected, test with the PKP import/export plugins to resolve any problems before proceeding with this one.

## Side Effects

Some expected behaviors when importing the journal:

- All database IDs will be modified, invalidating external references.
- The imported journal is initially disabled.
- A second import of the same journal path is rejected without duplicating content.
- Export archives contain author competing interests and must be handled as sensitive data.
- The selected theme and its declared options are transferred when the theme plugin code is installed in the
  destination OJS. When it is unavailable, the imported journal uses the default theme with its default options.
- Institutional metrics require a valid ROR identifier; records without a stable ROR are rejected.

## Imported/Exported Journal Content

**Using PKP native import/export**:

- Users and User Roles
- Articles
- Issues

**Added**:

- Journal data
- Author preferred public names and competing interests
- Selected theme and theme options
- Navigation Menus
- Plugins Configs
- Sections
- Review Forms
- Review Assignments
- Review Rounds
- Review Files
- Reviewer Files
- Reviewer Comments
- Revision Files
- Stage Assignments
- Editor Decisions
- Discussions
- Metrics

## Running Tests

### Unit Tests

To execute the unit tests, run the following command from root of the PKP Application directory:
```bash
lib/pkp/lib/vendor/bin/phpunit -c lib/pkp/tests/phpunit.xml --no-coverage plugins/importexport/fullJournalTransfer/tests
```

### Round trip

```bash
php plugins/importexport/fullJournalTransfer/tests/round-trip/run \
  --fixture plugins/importexport/fullJournalTransfer/tests/round-trip/fixture-ojs-3.4.0.10-v1.tar.gz \
  --expected plugins/importexport/fullJournalTransfer/tests/round-trip/expected-ojs-3.4.0.10-v1.json \
  --app-root [ojs_root] \
  --files-dir [files_dir] \
  --public-dir [public_dir] \
  --database [database_name] \
  --mysql-command [mysql_command] \
  --inventory-command plugins/importexport/fullJournalTransfer/tests/round-trip/inventory \
  --apply
```

# Credits
This plugin was idealized and sponsored by the Brazilian Institute of Information in Science and Technology (IBICT) for OJS version 2.x.

Funding for version 3.3 comes from the Federal University of São Paulo (Unifesp) and Federal University of Recôncavo da Bahia (UFRB).

Developed by Lepidus Tecnologia.


# License
This plugin is licensed under the GNU General Public License v3.0

Copyright (c) 2014-2026 Lepidus Tecnologia
