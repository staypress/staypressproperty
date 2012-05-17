function sp_onfocus_shortcodesearch() {
	if(jQuery(this).val() == staypresspublic.searchtext) {
		jQuery(this).val('');
	}
}

function sp_onblur_shortcodesearch() {
	if(jQuery(this).val() == '') {
		jQuery(this).val(staypresspublic.searchtext);
	}
}

function sp_propertyshortcodesearchready() {
	jQuery('.sp_shortcodesearchfor').focus(sp_onfocus_shortcodesearch);
	jQuery('.sp_shortcodesearchfor').blur(sp_onblur_shortcodesearch);

	jQuery('.sp_advshortcodesearchfor').focus(sp_onfocus_shortcodesearch);
	jQuery('.sp_advshortcodesearchfor').blur(sp_onblur_shortcodesearch);
}

jQuery(document).ready(sp_propertyshortcodesearchready);