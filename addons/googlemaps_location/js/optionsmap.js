function sp_grabMapSettings() {

	var center = sp_map.getCenter();
	var zoom = sp_map.getZoom();

	jQuery('#latitude').val(center.lat());
	jQuery('#longitude').val(center.lng());
	jQuery('#zoom').val(zoom);

	staypressmaps.latitude = center.lat();
	staypressmaps.longitude = center.lng();
	staypressmaps.zoom = zoom;
}

function sp_propertyOptionsMapReady() {

	var latlng = new google.maps.LatLng(staypressmaps.latitude, staypressmaps.longitude);

	var myOptions = {
      zoom: staypressmaps.zoom,
      center: latlng,
      mapTypeId: google.maps.MapTypeId.ROADMAP
    };

	sp_map = new google.maps.Map(document.getElementById("locationmap"), myOptions);
	sp_geocoder = new google.maps.Geocoder();

	google.maps.event.addListener(sp_map, 'center_changed', sp_grabMapSettings);
	google.maps.event.addListener(sp_map, 'zoom_changed', sp_grabMapSettings);

}

jQuery(document).ready(sp_propertyOptionsMapReady);