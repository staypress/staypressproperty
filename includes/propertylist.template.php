<div class="property">

			[propertythumbnail holder='div' holderclass='image' itemislink='yes']

            <div class="details">
                [propertytitle holder='h5' itemislink='yes']
				[propertyexcerpt holder='div' holderclass='excerpt' wrapwith='p']

					<div class='pricingfrom'>
							[propertylowestprice priceperiod='1' priceperiodtype='m' postfix=' mnth' prefix='From ' wrapwith='strong' textcurrency='no' ]
							<br/>
							[propertylowestprice priceperiod='1' priceperiodtype='w' prefix='From ' postfix=' week' wrapwith='strong' textcurrency='no' ]
					</div>

					<ul class='property-facilities'>
						[propertymeta property='post' item='li' itemclass='bedrooms' meta='Bedrooms' default='0' showname='no' prefix='Bedrooms : ']
						[propertymeta property='post' item='li' itemclass='bathrooms' meta='Bathrooms' default='0' showname='no' prefix='Bathrooms : ']
					</ul>
            </div><!--details-->

			<p class="property-meta">
				<strong>Location</strong> : [propertycountry property='post' itemislink='yes' postfix=' &gt;'] [propertyregion property='post' itemislink='yes' postfix=' &gt;'] [propertytown property='post' itemislink='yes']
			</p>

			<div class="clear"></div>


</div><!--property-->