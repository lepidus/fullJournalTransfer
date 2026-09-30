**English** | [Português Brasileiro](/docs/README-pt_BR.md)

# Full Journal Transfer
This plugin transfers the journal data covered by the explicit contracts described below.

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
- The plugin does not include historical editorial events or sent-email records from `event_log`,
  `event_log_settings`, `email_log`, or `email_log_users`. Events created in the destination during import belong
  to the import operation and do not reproduce the source history.
- Historical submission modification times are preserved. After import, rebuild the search index and clear the
  application caches according to the destination site's operational procedure. OAI consumers should perform a full
  harvest after switching to the destination journal instead of relying only on an incremental date window.
- The selected theme and its declared options are transferred when the theme plugin code is installed in the
  destination OJS. When it is unavailable, the imported journal uses the default theme with its default options.
- Institutional metrics require a valid ROR identifier; records without a stable ROR are rejected.

## Journal locales and settings

The UI, form, and submission locale lists are transferred independently. During import, each list is
intersected with the locales enabled for the destination OJS site, preserving the source order. Import stops
before the journal is created when the primary locale is unavailable or when no form or submission locale
remains. Validation errors in this early CLI/filter path are deterministic English messages because plugin
locale catalogs are not loaded at that point.

To migrate all localized metadata, install and enable every locale used by the source journal in the destination
OJS site before import. The CLI warns which unavailable locales will be filtered from the import.

The following journal settings are transferred:

- Identity and contact: `name`, `acronym`, `abbreviation`, `about`, `description`, `editorialTeam`,
  `authorInformation`, `librarianInformation`, `readerInformation`, `privacyStatement`, `openAccessPolicy`,
  `contactAffiliation`, `contactEmail`, `contactName`, `contactPhone`, `mailingAddress`, `country`, `onlineIssn`,
  `printIssn`, `publisherInstitution`, `publisherUrl`, `supportEmail`, `supportName`, `supportPhone`, `enableOai`,
  `itemsPerPage`, and `numPageLinks`.
- DOI: `enableDois`, `enabledDoiTypes`, `doiPrefix`, `doiSuffixType`, `doiIssueSuffixPattern`,
  `doiPublicationSuffixPattern`, `doiRepresentationSuffixPattern`, `doiVersioning`, and `doiCreationTime`.
- License and copyright: `copyrightYearBasis`, `copyrightHolderType`, `copyrightHolderOther`, `copyrightNotice`,
  `licenseTerms`, `licenseUrl`, and `rights`.
- Submission and metadata: `disableSubmissions`, `authorGuidelines`, `beginSubmissionHelp`, `contributorsHelp`,
  `detailsHelp`, `forTheEditorsHelp`, `uploadFilesHelp`, `submissionChecklist`, `submissionAcknowledgement`,
  `copySubmissionAckAddress`, `copySubmissionAckPrimaryContact`, `submitWithCategories`, `agencies`, `citations`,
  `competingInterests`, `coverage`, `dataAvailability`, `disciplines`, `keywords`, `languages`, `subjects`, and
  `requireAuthorCompetingInterests`.
- Review and editorial workflow: `defaultReviewMode`, `numWeeksPerResponse`, `numWeeksPerReview`,
  `restrictReviewerFileAccess`, `reviewerAccessKeysEnabled`, `reviewGuidelines`, `reviewHelp`,
  `numDaysBeforeInviteReminder`, `numDaysBeforeSubmitReminder`, `rateReviewerOnQuality`, and `notifyAllAuthors`.
- Publication: `publishingMode` for open-access or no-publication journals. Subscription publishing is rejected
  before journal creation because subscription records are not transferred.

Localized settings are limited to the accepted locale union, and the submission checklist is limited to the
effective form locales. Null values remain absent. Assigned issue, publication, and representation DOIs are
preserved through Native XML. Automatic DOI deposits, agency account data, credentials, tokens, and private
deposit-plugin settings are not transferred, and importing a package never schedules a DOI deposit.

## Imported/Exported Journal Content

**Using PKP native import/export**:

- Active users and their user roles; disabled users are included only when referenced by the workflow and are
  transferred without user roles
- Articles
- Issues

**Added**:

- Journal data
- Author preferred public names and competing interests
- Submission historical dates and issue publication timestamps
- Incomplete submission wizard progress
- Selected theme and theme options
- Journal public files and logo, image, favicon and CSS settings
- Navigation Menus
- Sections
- Review Forms
- Review Assignments
- Review form responses, preserving null and empty values independently
- Review Rounds
- Review Files
- Reviewer Files
- Reviewer Comments
- Revision Files
- Stage Assignments
- Editor Decisions
- Discussions
- Metrics

## Public files and appearance

The package includes static files from the journal's own public directory, normally
`public/journals/<id>/`, preserving subdirectories that contain files. It excludes `public/site/` and
other journals. In addition to native cover transfer, it transfers `pageHeaderLogoImage`,
`homepageImage`, `favicon`, `journalThumbnail` and `styleSheet`, including metadata and accepted locales.

Files are stored under `public-files/` in the package and restored into the new journal's directory.
References to the source public directory are updated for the destination domain, installation path
and journal ID in exported XML content, including HTML and theme options, and in CSS, HTML, HTM, SVG,
JS, JSON, XML and MAP files. Relative references within the tree remain usable. External resources
are not downloaded. Rewriting JavaScript may invalidate integrity hashes and source map positions
maintained outside the transferred content.

The explicit static extension list in `publicFiles/PublicFilesTransfer.php` permits common images,
fonts, styles, browser scripts, documents, archives and media. Unsupported extensions, server
executables, special files and symbolic links stop the transfer. Appearance settings referencing
missing files are also rejected. Import does not overwrite existing public files and registers new
files and directories for compensation if the import fails.

The configured public directory must be relative to the application or an absolute path inside it;
absolute filesystem paths are converted to relative public URL paths. All permitted files in the
journal directory are included, even if currently unreferenced. This preserves assets used by
announcements, highlights, pages and plugins, but does not add transfer of their records or settings
when those are outside the plugin's existing contracts. Code and assets installed in theme or plugin
directories must be installed separately at the destination.

Use this plugin version at both ends to transfer the new `public_files` section. Older packages
without that optional section remain supported, but cannot restore appearance files they did not
include. HTML, JavaScript and SVG remain active content: import packages only from trusted sources.

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
