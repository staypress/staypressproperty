// return an array with any duplicate, whitespace or values removed
function array_unique_noempty(a) {
	var out = [];
	jQuery.each( a, function(key, val) {
		val = jQuery.trim(val);
		if ( val && jQuery.inArray(val, out) == -1 )
			out.push(val);
		} );
	return out;
}

(function($){
tagBox = {
	clean : function(tags) {
		return tags.replace(/\s*,\s*/g, ',').replace(/,+/g, ',').replace(/[,\s]+$/, '').replace(/^[,\s]+/, '');
	},

	parseTags : function(el) {
		var id = el.id, num = id.split('-check-num-')[1], taxbox = $(el).closest('.tagsdiv'), thetags = taxbox.find('.the-tags'), current_tags = thetags.val().split(','), new_tags = [];
		delete current_tags[num];

		$.each( current_tags, function(key, val) {
			val = $.trim(val);
			if ( val ) {
				new_tags.push(val);
			}
		});

		thetags.val( this.clean( new_tags.join(',') ) );

		this.quickClicks(taxbox);
		return false;
	},

	quickClicks : function(el) {
		var thetags = $('.the-tags', el), tagchecklist = $('.tagchecklist', el), current_tags;

		if ( !thetags.length )
			return;

		current_tags = thetags.val().split(',');
		tagchecklist.empty();

		$.each( current_tags, function( key, val ) {
			var txt, button_id, id = $(el).attr('id');

			val = $.trim(val);
			if ( !val.match(/^\s+$/) && '' != val ) {
				button_id = id + '-check-num-' + key;
	 			txt = '<span><a id="' + button_id + '" class="ntdelbutton">X</a>&nbsp;' + val + '</span> ';
	 			tagchecklist.append(txt);
	 			$( '#' + button_id ).click( function(){ tagBox.parseTags(this); });
			}
		});
	},

	flushTags : function(el, a, f) {
		a = a || false;
		var text, tags = $('.the-tags', el), newtag = $('input.newtag', el), newtags;

		text = a ? $(a).text() : newtag.val();
		tagsval = tags.val();
		newtags = tagsval ? tagsval + ',' + text : text;

		newtags = this.clean( newtags );
		newtags = array_unique_noempty( newtags.split(',') ).join(',');

		tags.val(newtags);
		this.quickClicks(el);

		if ( !a )
			newtag.val('');
		if ( 'undefined' == typeof(f) )
			newtag.focus();

		return false;
	},

	get : function(id) {
		var tax = id.substr(id.indexOf('-')+1);

		$.post(ajaxurl, {'action':'get-tagcloud','tax':tax}, function(r, stat) {
			if ( 0 == r || 'success' != stat )
				r = wpAjax.broken;

			r = $('<p id="tagcloud-'+tax+'" class="the-tagcloud">'+r+'</p>');
			$('a', r).click(function(){
				tagBox.flushTags( $(this).closest('.innersidebarbox').children('.tagsdiv'), this);
				return false;
			});

			$('#'+id).after(r);
		});
	},

	init : function() {
		var t = this, ajaxtag = $('div.ajaxtag');

	    $('.tagsdiv').each( function() {
	        tagBox.quickClicks(this);
	    });

		$('input.tagadd', ajaxtag).click(function(){
			t.flushTags( $(this).closest('.tagsdiv') );
		});

		$('div.taghint', ajaxtag).click(function(){
			$(this).css('visibility', 'hidden').siblings('.newtag').focus();
		});

		$('input.newtag', ajaxtag).blur(function() {
			if ( this.value == '' )
	            $(this).siblings('.taghint').css('visibility', '');
	    }).focus(function(){
			$(this).siblings('.taghint').css('visibility', 'hidden');
		}).keyup(function(e){
			if ( 13 == e.which ) {
				tagBox.flushTags( $(this).closest('.tagsdiv') );
				return false;
			}
		}).keypress(function(e){
			if ( 13 == e.which ) {
				e.preventDefault();
				return false;
			}
		}).each(function(){
			var tax = $(this).closest('div.tagsdiv').attr('id');
			$(this).suggest( ajaxurl + '?action=ajax-tag-search&tax=' + tax, { delay: 500, minchars: 2, multiple: true, multipleSep: ", " } );
		});

	    // save tags on post save/publish
	    $('#post').submit(function(){
			$('div.tagsdiv').each( function() {
	        	tagBox.flushTags(this, false, 1);
			});
		});

		// tag cloud
		$('a.tagcloud-link').click(function(){
			tagBox.get( $(this).attr('id') );
			$(this).unbind().click(function(){
				$(this).siblings('.the-tagcloud').toggle();
				return false;
			});
			return false;
		});
	}
};
})(jQuery);

function init_categories() {

	// categories
	jQuery('.categorydiv').each( function(){
		var this_id = jQuery(this).attr('id'), noSyncChecks = false, syncChecks, catAddAfter, taxonomyParts, taxonomy, settingName;

		taxonomyParts = this_id.split('-');
		taxonomyParts.shift();
		taxonomy = taxonomyParts.join('-');
 		settingName = taxonomy + '_tab';
 		if ( taxonomy == 'category' )
 			settingName = 'cats';

		// TODO: move to jQuery 1.3+, support for multiple hierarchical taxonomies, see wp-lists.dev.js
		jQuery('a', '#' + taxonomy + '-tabs').click( function(){
			var t = jQuery(this).attr('href');
			jQuery(this).parent().addClass('tabs').siblings('li').removeClass('tabs');
			jQuery('#' + taxonomy + '-tabs').siblings('.tabs-panel').hide();
			jQuery(t).show();
			if ( '#' + taxonomy + '-all' == t )
				deleteUserSetting(settingName);
			else
				setUserSetting(settingName, 'pop');
			return false;
		});

		if ( getUserSetting(settingName) )
			jQuery('a[href="#' + taxonomy + '-pop"]', '#' + taxonomy + '-tabs').click();

		// Ajax Cat
		jQuery('#new' + taxonomy).one( 'focus', function() { jQuery(this).val( '' ).removeClass( 'form-input-tip' ) } );
		jQuery('#' + taxonomy + '-add-submit').click( function(){ jQuery('#new' + taxonomy).focus(); });

		syncChecks = function() {
			if ( noSyncChecks )
				return;
			noSyncChecks = true;
			var th = jQuery(this), c = th.is(':checked'), id = th.val().toString();
			jQuery('#in-' + taxonomy + '-' + id + ', #in-' + taxonomy + '-category-' + id).attr( 'checked', c );
			noSyncChecks = false;
		};

		catAddBefore = function( s ) {
			if ( !jQuery('#new'+taxonomy).val() )
				return false;
			s.data += '&' + jQuery( ':checked', '#'+taxonomy+'checklist' ).serialize();
			return s;
		};

		catAddAfter = function( r, s ) {
			var sup, drop = jQuery('#new'+taxonomy+'_parent');

			if ( 'undefined' != s.parsed.responses[0] && (sup = s.parsed.responses[0].supplemental.newcat_parent) ) {
				drop.before(sup);
				drop.remove();
			}
		};

		jQuery('#' + taxonomy + 'checklist').wpList({
			alt: '',
			response: taxonomy + '-ajax-response',
			addBefore: catAddBefore,
			addAfter: catAddAfter
		});

		jQuery('#' + taxonomy + '-add-toggle').click( function() {
			jQuery('#' + taxonomy + '-adder').toggleClass( 'wp-hidden-children' );
			jQuery('a[href="#' + taxonomy + '-all"]', '#' + taxonomy + '-tabs').click();
			jQuery('#new'+taxonomy).focus();
			return false;
		});

		jQuery('#' + taxonomy + 'checklist li.popular-category :checkbox, #' + taxonomy + 'checklist-pop :checkbox').live( 'click', function(){
			var t = jQuery(this), c = t.is(':checked'), id = t.val();
			if ( id && t.parents('#taxonomy-'+taxonomy).length )
				jQuery('#in-' + taxonomy + '-' + id + ', #in-popular-' + taxonomy + '-' + id).attr( 'checked', c );
		});

	}); // end cats

}

function sp_stripePrices() {
	jQuery('ul.pricetable li.pricerow').removeClass('altstripe');
	jQuery('ul.pricetable li.pricerow:odd').addClass('altstripe');
}

function sp_deletePriceRowLine() {
	if(confirm(property.deletepriceperiod)) {
		jQuery(this).parents('.priceline').fadeOut('slow', function() {
				jQuery(this).remove();
				});
	}
	return false;
}
function sp_createPriceRowLine() {
	jQuery(this).parents('.pricecolumn').append( jQuery(this).parents('.priceline').clone() );
	jQuery(this).removeClass('addpriceperiodrow').addClass('removepriceperiodrow').attr('title',property.priceperioddeletetitle).unbind('click');

	jQuery('.removepriceperiodrow').unbind('click').click(sp_deletePriceRowLine);
	jQuery('.addpriceperiodrow').unbind('click').click(sp_createPriceRowLine);

	return false;
}

function sp_deletePriceRow() {
	if(confirm(property.deleteprices)) {
		jQuery(this).parents('.pricerow').fadeOut('slow', function() {
				jQuery(this).remove();
				sp_stripePrices();
				});
	}
	return false;
}
function sp_createPriceRow() {
	jQuery(this).parents('.pricenewrow').clone().appendTo('.pricetable');
	jQuery(this).removeClass('addpricerow').addClass('removepricerow').attr('title',property.pricerowdeletetitle).unbind('click');
	jQuery(this).parents('.pricenewrow').addClass('pricerow').removeClass('pricenewrow');

	lastrow = jQuery('#lastpricerow').val();
	jQuery(this).parents('.pricerow').attr('id','pricerow-' + lastrow);
	jQuery(this).parents('.pricerow').find('.pricerowidentifier').val(lastrow);
	jQuery(this).parents('.pricerow').find('.priceday').attr('id','priceday-' + lastrow).attr('name','priceday[' + lastrow + ']').end()
									 .find('.pricemonth').attr('id','pricemonth-' + lastrow).attr('name','pricemonth[' + lastrow + ']').end();

	jQuery('#lastpricerow').val(parseInt(lastrow)+1);

	jQuery('.removepricerow').unbind('click').click(sp_deletePriceRow);
	jQuery('.addpricerow').unbind('click').click(sp_createPriceRow);

	sp_stripePrices();

	return false;
}

function sp_deleteImage() {

	if(confirm(property.deleteimage)) {
		var href = jQuery(this).attr('href');

		jQuery.getJSON(href, {nocache: new Date().getTime()},
						function(data){
							if(data.errorcode != '200') {
								alert(data.message);
							} else {
								jQuery('#imageitem-' + data.id).fadeOut('slow', function() { jQuery(this).remove(); jQuery("#imagelist").sortable("refresh"); });
							}
						});
	}


	return false;

}

function sp_updateMoveImages(e, ui) {
	jQuery('#imageorder').val(jQuery(this).sortable('serialize'));
}

function sp_dropMainImage(e, ui) {

	jQuery(this).find('img').attr('src', jQuery(ui.draggable).find('img').attr('src'));

	imageid = jQuery(ui.draggable).find('img').attr('id');
	jQuery('#mainimage').val(imageid.replace(/image-/,''));

}

function sp_dropListImage(e, ui) {
	jQuery(this).find('img').attr('src', jQuery(ui.draggable).find('img').attr('src'));

	imageid = jQuery(ui.draggable).find('img').attr('id');
	jQuery('#listimage').val(imageid.replace(/image-/,''));
}

function sp_setupMoveImages() {
	jQuery("#imagelist").sortable({	items: "li",
									revert: true,
									placeholder: 'editimageitemhighlight',
									scroll:true,
									smooth:true,
									revert:true,
									containment:'#imageblock',
									opacity: 0.75,
									cursor:'move',
									tolerance: 'pointer',
									update: sp_updateMoveImages
								});

	jQuery("li.mainimage").droppable({ 	drop: sp_dropMainImage,
		 								accept: '.editimageitem',
										hoverClass: 'activedrop',
										tolerance: 'touch'
								});

	jQuery("li.listimage").droppable({ 	drop: sp_dropListImage,
										accept: '.editimageitem',
										hoverClass: 'activedrop',
										tolerance: 'touch'
								});

}

function imageUploadComplete(responseJSON, statusText)  {

	flipAddImageButton();

	if(responseJSON.errorcode != '200') {
		alert(responseJSON.message);
	} else {
		// Add the image to the bottom of the list

		jQuery('#uploadstatusmessage').fadeIn('slow', function() {
				jQuery(this).animate({opacity:1.0}, 1000).fadeOut('slow');
		});

		html = "<li class='editimageitem' id='imageitem-" + responseJSON.imageid + "'>";
		html += "<img src='" + responseJSON.imgurl + "' alt='' id='image-" + responseJSON.imageid + "'  class='editimage' />";

		html += "<div class='imgnavholder'>";
		html += "<a href='" + responseJSON.deleteurl + "' class='delimagelink' id='delimage-" + responseJSON.imageid + "' title='" + property.deleteimagetitle + "'></a>";
		html += "</div>";

		html += "</li>";

		// Need to add to the UL now
		jQuery("#imagelist").prepend(html);

		jQuery("#imagelist").sortable("refresh");

		jQuery('li.editimageitem').unbind('hover');
		jQuery('a.delimagelink').unbind('click');

		jQuery('li.editimageitem').hover(sp_showimgNav, sp_hideimgNav);
		jQuery('a.delimagelink').click(sp_deleteImage);

	}
}

function submitImageUpload() {

	var options = {
	        success: 	imageUploadComplete,  // post-submit callback
			dataType: 	"json",
	        resetForm: 	true        // reset the form after successful submit
	    };

	flipAddImageButton();

	jQuery('#uploadimageform').ajaxSubmit(options);

	return false;
}

function flipAddImageButton() {
	altimage = jQuery('#uploadimage').attr('alt');
	jQuery('#uploadimage').attr('alt', jQuery('#uploadimage').attr('src')).attr('src', altimage);
}

function saveProperty() {
	try{
		jQuery('#imageorder').val(jQuery("#imagelist").sortable('serialize'));
	}
	catch(e) {
		// nothing
	}
	jQuery('#editaddpropertyform #status').val(jQuery('#savestatus').val());
	jQuery('#editaddpropertyform').submit();
	return false;
}

function sp_showimgNav() {
	jQuery(this).children('div.imgnavholder').slideDown('fast');
}
function sp_hideimgNav() {
	jQuery(this).children('div.imgnavholder').slideUp('fast');
}

function sp_toggleSidebarBox() {
	jQuery(this).siblings('div.innersidebarbox').toggleClass('shrunk');
	jQuery(this).siblings('h2.rightbarheading').toggleClass('shrunk');
}

function sp_removeMessageBox() {
	jQuery('#upmessage').fadeOut('slow', function() { jQuery(this).remove(); });

	return false;
}

function sp_updateMovePrices() {
	sp_stripePrices();
}

function sp_setupMovePrices() {

	jQuery('li.draghandle a').click(function() {return false;});

	jQuery("ul.pricetable").sortable({	items: "li.pricerow",
										revert: true,
										handle: 'li.draghandle a',
										scroll:true,
										smooth:true,
										revert:true,
										//containment:'ul.pricetable',
										opacity: 0.75,
										cursor:'move',
										tolerance: 'pointer',
										update: sp_updateMovePrices
								});
}

function sp_propertyEditReady() {
	sp_setupMoveImages();
	sp_setupMovePrices();

	jQuery('#uploadimagefile').change(submitImageUpload);

	jQuery('.removepricerow').click(sp_deletePriceRow);
	jQuery('.addpricerow').click(sp_createPriceRow);

	jQuery('.addpriceperiodrow').click(sp_createPriceRowLine);
	jQuery('.removepriceperiodrow').click(sp_deletePriceRowLine);

	jQuery('#propertystatusform').submit(saveProperty);

	jQuery('li.editimageitem').hover(sp_showimgNav, sp_hideimgNav);
	jQuery('a.delimagelink').click(sp_deleteImage);

	jQuery('div.sidebarbox div.handlediv').click(sp_toggleSidebarBox);
	// click to remove - or auto remove in 60 seconds
	jQuery('a#closemessage').click(sp_removeMessageBox);
	setTimeout('sp_removeMessageBox()', 60000);

	if ( jQuery('div.tagsdiv').length > 0 ) {
		tagBox.init();
	}

	init_categories();

}

jQuery(document).ready(sp_propertyEditReady);