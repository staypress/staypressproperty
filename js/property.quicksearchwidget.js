function sp_onfocus_quicksearch() {
	if(jQuery(this).val() == quicksearchwidget.searchtext) {
		jQuery(this).val('');
	}
}

function sp_onblur_quicksearch() {
	if(jQuery(this).val() == '') {
		jQuery(this).val(quicksearchwidget.searchtext);
	}
}

function sp_propertyquicksearchwidgetready() {
	jQuery('.sp_quicksearchfor').focus(sp_onfocus_quicksearch);
	jQuery('.sp_quicksearchfor').blur(sp_onblur_quicksearch);
}

jQuery(document).ready(sp_propertyquicksearchwidgetready);