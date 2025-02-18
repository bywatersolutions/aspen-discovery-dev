{* Main Listing *}
{if (isset($title)) }
<script type="text/javascript">
	alert("{$title}");
</script>
{/if}
<div class="col-xs-12">
	{if !empty($seriesTitle)}
	<h1 class="notranslate">
		{$seriesTitle}
	</h1>
	{elseif !empty($error)}
		<h1>{translate text="Error" isPublicFacing=true}</h1>
		<div class="alert alert-danger">{$error}</div>
	{/if}
	{if !empty($seriesAuthors)}
	<div class="row">
		<div class="result-label col-sm-4 col-xs-12">{translate text="Author" isPublicFacing=true}</div>
		<div class="result-value col-sm-8 col-xs-12 notranslate">
			{foreach from=$seriesAuthors item=author}
				<span class="sidebarValue">{$author} </span>
			{/foreach}
		</div>
	</div>
	{/if}

	<div class="clearer">&nbsp;</div>

	<div class="result-head">
		<div id="searchInfo">
			{if empty($recordCount)}
				<p>{translate text="Sorry, we could not find series information for this title." isPublicFacing=true}</p>
			{/if}
		</div>
	</div>

	{* Display series information *}
	<div id="seriesTitles">
		{foreach from=$resourceList item=resource name="recordLoop"}
			<div class="result{if ($smarty.foreach.recordLoop.iteration % 2) == 0} alt{/if}">
				{* This is raw HTML -- do not escape it: *}
				{$resource}
			</div>

		{/foreach}
	</div>

	{if !empty($pageLinks.all)}<div class="pagination">{$pageLinks.all}</div>{/if}
</div>
