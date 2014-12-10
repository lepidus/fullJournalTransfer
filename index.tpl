{**
 * plugins/importexport/native/index.tpl
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * List of operations this plugin can perform
 *
 *}
{strip}
{assign var="pageTitle" value="plugins.importexport.native.displayName"}
{include file="common/header.tpl"}
{/strip}

<br/>

<h3>{translate key="plugins.importexport.fullJournalTransfer.export"}</h3>
<ul class="plain">
	<li>&#187; <a href="{plugin_url path="export"}">{translate key="plugins.importexport.fullJournalTransfer.exportJournal"}</a></li>
</ul>

<h3>{translate key="plugins.importexport.fullJournalTransfer.import"}</h3>
<p>{translate key="plugins.importexport.fullJournalTransfer.import.description"}</p>
<form action="{plugin_url path="import"}" method="post" enctype="multipart/form-data">
<input type="file" class="uploadField" name="importFile" id="import" /> <input name="import" type="submit" class="button" value="{translate key="common.import"}" />
</form>

{include file="common/footer.tpl"}
