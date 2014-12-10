{**
 * plugins/importexport/native/importSuccess.tpl
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display a list of the successfully-imported entities.
 *
 *}
{strip}
{assign var="pageTitle" value="plugins.importexport.fullJournalTransfer.import.success"}
{include file="common/header.tpl"}
{/strip}
<div id="importSuccess">
<p>{translate key="plugins.importexport.fullJournalTransfer.import.success.description"}</p>
</div>

{include file="common/footer.tpl"}
