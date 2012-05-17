function sp_onfocus_advsearch() {
	if(jQuery(this).val() == advsearchwidget.searchtext) {
		jQuery(this).val('');
	}
}

function sp_onblur_advsearch() {
	if(jQuery(this).val() == '') {
		jQuery(this).val(advsearchwidget.searchtext);
	}
}

function sp_propertyadvsearchwidgetready() {
	jQuery('.sp_advsearchfor').focus(sp_onfocus_advsearch);
	jQuery('.sp_advsearchfor').blur(sp_onblur_advsearch);
}

jQuery(document).ready(sp_propertyadvsearchwidgetready);