{**
 * plugins/importexport/fullJournalTransfer/importError.tpl
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display an error message for an aborted import process.
 *
 *}
{strip}
{assign var="pageTitle" value="plugins.importexport.fullJournalTransfer.import.error"}
{include file="common/header.tpl"}
{/strip}
<div id="importError">
<p>{translate key="plugins.importexport.fullJournalTransfer.import.error.description"}</p>
{if $error}
	<!-- A single error occurred. -->
	<p>{translate key=$error}</p>
{else}
	<!-- Multiple errors occurred. List them. -->
	<ul>
	{foreach from=$errors item=error}
		<li>{translate key=$error[0] params=$error[1]}</li>
	{/foreach}
	</ul>
{/if}
</div>
{include file="common/footer.tpl"}
