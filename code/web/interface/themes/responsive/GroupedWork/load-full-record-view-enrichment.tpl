{if !empty($recordDriver)}
<script type="text/javascript">
	{literal}$(document).ready(function(){{/literal}
		AspenDiscovery.GroupedWork.loadEnrichmentInfo('{$recordDriver->getPermanentId()|escape:"url"}');
		AspenDiscovery.GroupedWork.loadDescription('{$recordDriver->getPermanentId()|escape:"url"}', '{$recordType|default:""}', '{$recordId|default:""|escape:"javascript"}');
		{if $enableInnReachIntegration == 1}
		AspenDiscovery.InterLibraryLoan.loadRelatedInnReachTitles('{$recordDriver->getPermanentId()|escape:"url"}')
		{/if}
		{if $enableShareItIntegration == 1}
		AspenDiscovery.InterLibraryLoan.loadRelatedShareItTitles('{$recordDriver->getPermanentId()|escape:"url"}')
		{/if}
		{* More Like This and reviews are in the accordion further down the page. Last, so a browser still holding an
		   older aspen.js without loadWhenVisible loads everything above before it stops. *}
		AspenDiscovery.GroupedWork.loadWhenVisible('#more-details-accordion', function () {ldelim}
			AspenDiscovery.GroupedWork.loadMoreLikeThis('{$recordDriver->getPermanentId()|escape:"url"}', '{$recordDriver->getPrimaryFormat()}');
			AspenDiscovery.GroupedWork.loadReviewInfo('{$recordDriver->getPermanentId()|escape:"url"}');
		{rdelim});
		{literal}});{/literal}
</script>
{/if}
