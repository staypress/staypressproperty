function codeLatLng(latlng) {

	jQuery('#propertylat').val(latlng.lat());
	jQuery('#propertylng').val(latlng.lng());

    if (sp_geocoder) {
      sp_geocoder.geocode({'latLng': latlng}, function(results, status) {
        if (status == google.maps.GeocoderStatus.OK) {
          if (results[0]) {
			var sub = false; var loc = false; var admin2 = false; var admin1 = false; var country = false; var shortcountry = false;

			for (ac in results[0].address_components) {
				if(results[0].address_components[ac].types[0] && results[0].address_components[ac].types[0] == 'sublocality') {
					sub = results[0].address_components[ac].long_name;
				} else if(results[0].address_components[ac].types[0] && results[0].address_components[ac].types[0] == 'locality') {
					loc = results[0].address_components[ac].long_name;
				} else if(results[0].address_components[ac].types[0] && results[0].address_components[ac].types[0] == 'administrative_area_level_2') {
					admin2 = results[0].address_components[ac].long_name;
				} else if(results[0].address_components[ac].types[0] && results[0].address_components[ac].types[0] == 'administrative_area_level_1') {
					admin1 = results[0].address_components[ac].long_name;
				} else if(results[0].address_components[ac].types[0] && results[0].address_components[ac].types[0] == 'country') {
					country = results[0].address_components[ac].long_name;
					shortcountry = results[0].address_components[ac].short_name;
				}

			}

			// Populate some of the fields
			if(sub && !changedLocale) {
				jQuery('#propertytowntext').html(sub);
				jQuery('#propertytown').val(sub);
			} else if (loc && !changedLocale) {
				jQuery('#propertytowntext').html(loc);
				jQuery('#propertytown').val(loc);
			}
			if(admin2 && !changedLocale) {
				jQuery('#propertyregiontext').html(admin2);
				jQuery('#propertyregion').val(admin2);
			} else if (admin1 && !changedLocale) {
				jQuery('#propertyregiontext').html(admin1);
				jQuery('#propertyregion').val(admin1);
			}
			if(country && !changedLocale) {
				jQuery('#propertycountrytext').html(country);
				jQuery('#propertycountry').val(country);
			}

			jQuery('#propertylat').val(latlng.lat());
			jQuery('#propertylng').val(latlng.lng());


          }
        } else {
          alert(property.maperror);
        }
      });
    }
  }

function codeAddress(address) {
	    if (sp_geocoder) {
			sp_geocoder.geocode({"address": address}, function(results, status) {
				if (status == google.maps.GeocoderStatus.OK) {
					placeMarker(results[0].geometry.location, true);
					sp_map.panTo(results[0].geometry.location);
				}
			});
		}
}

function markerMoved() {
	codeLatLng(sp_marker.getPosition());
}

function placeMarker(location, geocode) {

	if(sp_marker) {
		sp_marker.setPosition(location);
	} else {
		sp_marker = new google.maps.Marker({
	    	position: location,
	    	map: sp_map,
			title:"Location",
			draggable: true
	  	});

		google.maps.event.addListener(sp_marker, 'dragend', markerMoved);

		// Switch off the message box
		jQuery('#innerlocationmessage').fadeOut('fast', function() { jQuery('#innerlocationdetails').fadeIn(); jQuery('#locationdetails h4').fadeIn();});
		changedLocale = false;
	}

	if(geocode) codeLatLng(location);

}

function localeChanged() {
	changedLocale = true;
}

function coordChanged() {
	if(coordCount >= 1) {
		var location = new google.maps.LatLng(jQuery('#propertylat').val(), jQuery('#propertylng').val());
		placeMarker(location, true);
		sp_map.panTo(location);
	} else {
		coordCount++;
	}
}

function editLocationDetails() {

	jQuery('#innerlocationdetails p.location').css('display', 'none');
	jQuery('#innerlocationdetails .locationinput').css('display', 'block');
	jQuery('label.locationsup').removeClass('locationsup');
	jQuery(this).css('display', 'none');
	// Set up the change checks
	jQuery('.locale').change(localeChanged);

	coordCount = 0;
	jQuery('input.coord').change(coordChanged);

	return false;
}

function grabGeocode(event) {
	placeMarker(event.latLng, true);
}

function sp_loadMaps() {

	var lat = jQuery('#propertylat').val();
	var lng = jQuery('#propertylng').val();

	sp_marker = false;

	if(lat != '') {
		var latlng = new google.maps.LatLng(lat, lng);
	} else {
		var latlng = new google.maps.LatLng(staypressmaps.latitude, staypressmaps.longitude);
	}

	var myOptions = {
      zoom: staypressmaps.zoom,
      center: latlng,
      mapTypeId: google.maps.MapTypeId.ROADMAP
    };

	sp_map = new google.maps.Map(document.getElementById("locationmap"), myOptions);
	sp_geocoder = new google.maps.Geocoder();

	google.maps.event.addListener(sp_map, 'click', grabGeocode);

	if(lat != '') {
		placeMarker(latlng, false);
	}

	jQuery('#editlocationlink').click(editLocationDetails);

	// Added for quick location finding
	jQuery("#mapquickfindbutton").click(function(){
		if(jQuery("#mapquickfind").val() != '') {
			searchfor = jQuery("#mapquickfind").val();
			codeAddress(searchfor);
			return false;
		}
	});

	jQuery("#mapquickfind").keyup(function(e) {
		if(e.keyCode == 13)
			jQuery("#mapquickfindbutton").click();
			return false;
	});
}

function sp_propertyMapEditReady() {

	sp_loadMaps();

}

jQuery(document).ready(sp_propertyMapEditReady);