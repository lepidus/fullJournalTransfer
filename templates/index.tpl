{**
 * plugins/importexport/fullJournalTransfer/templates/index.tpl
 *
 * Displays message indicating plugin should be used only by command line
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
    <h1 class="app__pageHeading">
        {$pageTitle|escape}
    </h1>

    <div class="app__contentPanel">
        <strong>{translate key="plugins.importexport.fullJournalTransfer.attention"}</strong>
        <p>
            {translate key="plugins.importexport.fullJournalTransfer.guiAttentionMessage"}
        </p>
    </div>
{/block}