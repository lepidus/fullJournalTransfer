{**
 * Copyright (c) 2014 Instituto Brasileiro de Informação em Ciência e Tecnologia 
 * Author: Giovani Pieri <giovani@lepidus.com.br>
 *
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *}
{strip}
{assign var="pageTitle" value="plugins.importexport.fullJournal.displayName"}
{include file="common/header.tpl"}
{/strip}

<br/>

<h3>{translate key="plugins.importexport.fullJournal.export"}</h3>
<ul class="plain">
	<li>&#187; <a href="{plugin_url path="export"}">{translate key="common.export"}</a></li>
</ul>

<h3>{translate key="plugins.importexport.fullJournal.import"}</h3>
<p>{translate key="plugins.importexport.fullJournal.import.description"}</p>
<form action="{plugin_url path="import"}" method="post" enctype="multipart/form-data">
<input type="file" class="uploadField" name="importFile" id="import" /> <input name="import" type="submit" class="button" value="{translate key="common.import"}" />
</form>

{include file="common/footer.tpl"}
