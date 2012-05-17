function sp_MoveMonth() {

	var movingto = jQuery(this).attr('id');
	movingto = movingto.split('-');
	if(movingto.length == 2) {
		jQuery('div.month').load(ajaxurl, {action: '_propertymovemonth', year: movingto[0], month: movingto[1], nocache: new Date().getTime()},
		function() {
			jQuery('a.previousmonth').unbind('click');
			jQuery('a.nextmonth').unbind('click');
			jQuery('a.previousmonth').click(sp_MoveMonth);
			jQuery('a.nextmonth').click(sp_MoveMonth);
		}
		);
	}

	return false;
}

function sp_deleteProperty() {
	if(confirm(property.deleteproperty)) {
		propertyid = jQuery(this).attr('id');
		propertyid = propertyid.replace(/delete-/,'');

		jQuery.getJSON(ajaxurl, { _ajax_nonce: property.deletepropertynonce, action: '_deleteproperty', id: propertyid, nocache: new Date().getTime() },
						function(data){
							if(data.errorcode != '200' && data.message != null) {
								alert(data.message);
							} else {
								property.deletepropertynonce = data.newnonce;
								jQuery('#propertylistitem-' + data.id).fadeOut('slow', function() { jQuery(this).remove(); });
							}
						});

	}

	return false;
}

function sp_addFilterTag() {

	thetext = jQuery('#addtagselect option:selected').text();
	theval = jQuery('#addtagselect option:selected').val();

	if(theval == '') {
		return false;
	}

	newli = "<li class='selectedtag newtag'><a href='#filterremovetag' class='selectedtagremove' title=''>&nbsp;</a>" + thetext + "<input type='hidden' name='tagfilter[]' value='" + theval + "' /></li>";

	jQuery('ul.selectedtaglist').append(newli);
	jQuery('li.newtag').animate({backgroundColor: '#f9f9f9'}, 1500, 'linear', function() {jQuery(this).removeClass('newtag').css('background-color','transparent');});
	jQuery('a.selectedtagremove').unbind('click').click(sp_removeFilterTag);

	return false;
}

function sp_removeFilterTag() {
	jQuery(this).parent('li.selectedtag').fadeOut('slow', function() {
			jQuery(this).remove();
			});
	return false;
}

function sp_deleteFacGroup() {
	if(confirm(property.deletefacilitygroup)) {
		return true;
	}

	return false;
}

function sp_deleteFac() {
	if(confirm(property.deletefacility)) {
		return true;
	}

	return false;
}

function sp_removeMessageBox() {
	jQuery('#upmessage').fadeOut('slow', function() { jQuery(this).remove(); });

	return false;
}

function sp_toggleSidebarBox() {
	jQuery(this).siblings('div.innersidebarbox').toggleClass('shrunk');
	jQuery(this).siblings('h2.rightbarheading').toggleClass('shrunk');
}

function sp_propertyadminready() {

	jQuery('#addtaglink').click(sp_addFilterTag);
	jQuery('a.selectedtagremove').click(sp_removeFilterTag);

	jQuery('a.propertydeletelink').click(sp_deleteProperty);

	jQuery('a.deletefacgroup').click(sp_deleteFacGroup);
	jQuery('a.deletefac').click(sp_deleteFac);

	jQuery('div.sidebarbox div.handlediv').click(sp_toggleSidebarBox);

	// click to remove - or auto remove in 60 seconds
	jQuery('a#closemessage').click(sp_removeMessageBox);
	setTimeout('sp_removeMessageBox()', 60000);

	jQuery('a.previousmonth').click(sp_MoveMonth);
	jQuery('a.nextmonth').click(sp_MoveMonth);

}

jQuery(document).ready(sp_propertyadminready);