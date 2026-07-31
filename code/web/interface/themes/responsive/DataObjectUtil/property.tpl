{assign var=propName value=$property.property}
{if $property.type != 'section'}
	{* Note, you cannot combine both a provided object with loading from property defaults. *}
	{if !empty($object)}
		{assign var=propValue value=$object->$propName}
		{assign var=objectId value=$object->getPrimaryKeyValue()}
	{else}
		{if isset($property.default)}
			{assign var=propValue value=$property.default}
		{else}
			{assign var=propValue value=""}
		{/if}
	{/if}
{else}
	{assign var=propValue value=""}
{/if}
{strip}
{if ((!isset($property.storeDb) || $property.storeDb == true) && !($property.type == 'oneToManyAssociation' || $property.type == 'hidden' || $property.type == 'method'))}
	<div {if !isset($addFormGroupToProperty) || $addFormGroupToProperty !== false}class="form-group propertyRow"{/if} id="propertyRow{$propName}" {if !empty($property.hiddenByDefault) && $property.hi[...]
		{* Output the label *}
		{if $property.type == 'enum'}
			{if !empty($property.renderAsHeading)}
				{if !empty($property.required)}
					<p style="margin-bottom: .5em">
						<p class="{if !empty($property.headingLevel)}{$property.headingLevel}{else}h2{/if}" style="display: inline; vertical-align: top; margin-right: .25em" {if !empty($property.description)}aria-d[...]
						{include file="DataObjectUtil/fieldLockingInfo.tpl"}
						{if !empty($property.description)}
							<a style="margin-right: .5em; margin-left: .25em; display: inline;" id="{$property.property}Tooltip" class="text-info" role="tooltip" tabindex="0" data-toggle="tooltip" data-placement="righ[...]
								<i class="fas fa-question-circle" style="vertical-align: top"></i>
							</a>
						{/if}
						<span class="label label-danger" style="margin-right: .5em;{if empty($property.description)}margin-left: .5em;{/if} vertical-align: top">{translate text="Required" isAdminFacing=true}</span>
					</div>
				{else}
					<p class="{if !empty($property.headingLevel)}{$property.headingLevel}{else}h2{/if}" style="display: inline;" {if !empty($property.description)}aria-describedby="{$property.property}Tooltip"{/[...]
					{if !empty($property.description)}
						<a style="margin-right: .5em; margin-left: .25em; display: inline;" id="{$property.property}Tooltip" class="text-info" role="tooltip" tabindex="0" data-toggle="tooltip" data-placement="right[...]
							<i class="fas fa-question-circle" style="vertical-align: top"></i>
						</a>
					{/if}
					{include file="DataObjectUtil/fieldLockingInfo.tpl"}
					{if !empty($property.required)}
						<span class="label label-danger" style="margin-right: .5em{if empty($property.description)}; margin-left: .5em{/if}">{translate text="Required" isAdminFacing=true}</span>
					{/if}
				{/if}
			{else}
				<label for='{$propName}Select' {if !empty($property.description)}aria-describedby="{$propName}Tooltip"{/if}>
					{translate text=$property.label isAdminFacing=true}
				</label>
				{if !empty($property.description)}<a style="margin-right: .5em; margin-left: .25em" id="{$propName}Tooltip" class="text-info" role="tooltip" tabindex="0" data-toggle="tooltip" data-placement="[...]
				{include file="DataObjectUtil/fieldLockingInfo.tpl"}
				{if !empty($property.required)}
					<span class="label label-danger" style="margin-right: .5em{if empty($property.description)};margin-left: .5em;{/if}">{translate text="Required" isAdminFacing=true}</span>
				{/if}
			{/if}
		{elseif $property.type == 'oneToMany' && !empty($property.helpLink)}
			<div class="row">
				<div class="col-xs-11">
				{if !empty($property.renderAsHeading)}
					{if !empty($property.required)}
						<div style="margin-bottom: .5em">
							<p class="{if !empty($property.headingLevel)}{$property.headingLevel}{else}h2{/if}" style="display: inline; vertical-align: top; margin-right: .25em" {if !empty($property.description)}aria-[...]
							{include file="DataObjectUtil/fieldLockingInfo.tpl"}
							{if !empty($property.description)}
								<a style="margin-right: .5em; margin-left: .25em; display: inline;" id="{$property.property}Tooltip" class="text-info" role="tooltip" tabindex="0" data-toggle="tooltip" data-placement="rig[...]
									<i class="fas fa-question-circle" style="vertical-align: top"></i>
								</a>
							{/if}
							<span class="label label-danger" style="margin-right: .5em;{if empty($property.description)}margin-left: .5em;{/if} vertical-align: top">{translate text="Required" isAdminFacing=true}</span[...]
							</div>
					{else}
						<p class="{if !empty($property.headingLevel)}{$property.headingLevel}{else}h2{/if}" style="display: inline;" {if !empty($property.description)}aria-describedby="{$property.property}Tooltip" [...]
						{include file="DataObjectUtil/fieldLockingInfo.tpl"}
						{if !empty($property.description)}
							<a style="margin-right: .5em; margin-left: .25em; display: inline;" id="{$property.property}Tooltip" class="text-info" role="tooltip" tabindex="0" data-toggle="tooltip" data-placement="righ[...]
								<i class="fas fa-question-circle" style="vertical-align: top"></i>
							</a>
						{/if}

						{if !empty($property.required)}
							<span class="label label-danger" style="margin-right: .5em{if empty($property.description)};margin-left: .5em;{/if}">{translate text="Required" isAdminFacing=true}</span>
						{/if}
					{/if}
				{else}
					<label for='{$propName}' {if !empty($property.description)}aria-describedby="{$property.property}Tooltip" {/if}>
						{translate text=$property.label isAdminFacing=true}
					</label>
					{if !empty($property.description)}<a style="margin-right: .5em; margin-left: .25em" id="{$property.property}Tooltip" class="text-info" role="tooltip" tabindex="0" data-toggle="tooltip" data-p[...]
					{include file="DataObjectUtil/fieldLockingInfo.tpl"}
					{if !empty($property.required)}
						<span class="label label-danger" style="margin-right: .5em{if empty($property.description)};margin-left: .5em;{/if}">{translate text="Required" isAdminFacing=true}</span>
					{/if}
				{/if}
				</div>
				<div class="col-xs-1">
					<a href="{$property.helpLink}" target="_blank"><img src="/interface/themes/responsive/images/help.png" alt="Help"></a>
				</div>
			{/if}
		{/elseif $property.type != 'section' && $property.type != 'checkbox' && $property.type != 'hidden' && $property.type != 'alert' }
			{if !empty($property.renderAsHeading)}
				{if !empty($property.required)}
					<div style="margin-bottom: .5em; {if !empty($property.showBottomBorder)}border-bottom: 2px solid {$secondaryBackgroundColor}{/if}">
						<p class="{if !empty($property.headingLevel)}{$property.headingLevel}{else}h2{/if}" style="display: inline; vertical-align: top; margin-right: .25em" {if !empty($property.description)}aria-[...]
						{if !empty($property.description)}
							<a style="margin-right: .5em; margin-left: .25em; display: inline;" id="{$property.property}Tooltip" class="text-info" role="tooltip" tabindex="0" data-toggle="tooltip" data-placement="rig[...]
								<i class="fas fa-question-circle" style="vertical-align: top"></i>
							</a>
						{/if}
						{include file="DataObjectUtil/fieldLockingInfo.tpl"}
						<span class="label label-danger" style="margin-right: .5em; vertical-align: top{if empty($property.description)};margin-left: .5em;{/if}">{translate text="Required" isAdminFacing=true}</spa[...]
					</div>
				{else}
					<div style="margin-bottom: .5em; {if !empty($property.showBottomBorder)}border-bottom: 2px solid {$secondaryBackgroundColor}{/if}">
						<p class="{if !empty($property.headingLevel)}{$property.headingLevel}{else}h2{/if}" style="display: inline;" {if !empty($property.description)}aria-describedby="{$property.property}Tooltip"[...]
