{**
 * Copyright (c) 2014 Instituto Brasileiro de Informação em Ciência e Tecnologia 
 * Author: Giovani Pieri <giovani@lepidus.com.br>
 *
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *}
{strip}
{assign var="pageTitle" value="plugins.importexport.fullJournal.import.error"}
{include file="common/header.tpl"}
{/strip}
<div id="importError">
<p>{translate key="plugins.importexport.fullJournal.import.error.description"}</p>
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
