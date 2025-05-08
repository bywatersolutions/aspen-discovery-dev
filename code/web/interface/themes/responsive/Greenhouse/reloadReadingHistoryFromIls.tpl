{strip}
	<div class="row">
		<div class="col-xs-12">
			<h1 id="pageTitle">{$pageTitleShort}</h1>
		</div>
	</div>
	{if isset($reloadResults)}
		{assign var="successBarcodes" value=[]}
		{assign var="errorBarcodes" value=[]}

		{foreach from=$reloadResults item=reloadResult}
			{if !empty($reloadResult.success)}
				{append var=successBarcodes value=$reloadResult.barcode}
			{else}
				{append var=errorBarcodes value=$reloadResult.barcode}
			{/if}
		{/foreach}

		<div class="row">
			<div class="col-xs-12">
				{if $successBarcodes|@count > 0}
					<div class="alert alert-success">
						<strong>{translate text="Successfully Scheduled to Reload" isAdminFacing=true}: </strong>
						{implode(", ", $successBarcodes)}
					</div>
				{/if}

				{if $errorBarcodes|@count > 0}
					<div class="alert alert-danger">
						<strong>{translate text="Failed to Schedule for Reload" isAdminFacing=true}: </strong>
						{implode(", ", $errorBarcodes)}
					</div>
				{/if}
			</div>
		</div>
	{/if}
	<div class="row">
		<div class="col-xs-12">
			<div class="alert alert-info">{translate text="Enter the barcode(s) for the users whose reading history you want to reset. Enter each barcode on its own line.<br>A cron job runs every 5 minutes to import users' reading histories from the ILS, but depending on the number of titles to import, the process may take a while." isAdminFacing=true}</div>
		</div>
	</div>
	<form name="resetReadingHistory" method="post" enctype="multipart/form-data" class="form-horizontal">
		<fieldset>
			<input type="hidden" name="objectAction" value="processNewAdministrator">
			<div class="row form-group">
				<label for="barcodes" class="col-sm-2 control-label">{translate text='Barcode(s)' isAdminFacing=true}</label>
				<div class="col-sm-10">
					<textarea name="barcodes" id="barcodes" class="form-control"></textarea>
				</div>
			</div>

			<div class="form-group">
				<div class="controls col-sm-offset-2 col-sm-2">
					<input type="submit" name="submit" value="{translate text="Reset Reading History" inAttribute=true isAdminFacing=true}" class="btn btn-primary">
				</div>
			</div>
		</fieldset>
	</form>
{/strip}
