function openTab(evt, tabName) {
	var i, tabcontent, tablinks;
	tabcontent = document.getElementsByClassName("tabcontent");
	for (i = 0; i < tabcontent.length; i++) {
	tabcontent[i].style.display = "none";
	}
	tablinks = document.getElementsByClassName("tablinks");
	for (i = 0; i < tablinks.length; i++) {
	tablinks[i].className = tablinks[i].className.replace(" active", "");
	}
	document.getElementById(tabName).style.display = "block";
	evt.currentTarget.className += " active";
}

jQuery(document).ready(function(){
	selected_value = $("input[name='update_answer']:checked").val();
    if(selected_value=='yes'){
    	jQuery('#cron_time').show();
    }else{
    	jQuery('#cron_time').hide();
    }
	jQuery('#auto_update_frm').change(function(){
		selected_value = $("input[name='update_answer']:checked").val();
        if(selected_value=='yes'){
        	jQuery('#cron_time').show();
        }else{
        	jQuery('#cron_time').hide();
        }
    });

    var vars = [], hash;
    var q = document.URL.split('?')[1];

    if(q != undefined){
      	q = q.split('&');
      	for(var i = 0; i < q.length; i++){
	        hash = q[i].split('=');
	        var elem = [hash[0], hash[1]];
	        vars.push(elem);
	    }

	    if(vars.length != 0){
	    	if(vars[2]){
	    		var newstr = vars[2].toString();
		        var item = newstr.split(',');

		        if(item[0] == 'plugin_slug'){
		        	document.getElementById('default').style.display = "none";
		        	document.getElementById('advanced').style.display = "block";
		        	jQuery('#default-tab').removeClass('active');
		        	jQuery('#advanced-tab').addClass('active');
		        }
	    	}

	    	if(vars[2]){
	    		if(vars[2][0] == 'rlmsg'){
			    	jQuery("#rollback-message").css('display','block');

			    	setTimeout(function() {
				        jQuery("#rollback-message").css('display','none');
				    }, 5000);
			    }

			    if(vars[2][0] == 'msg'){
			    	jQuery("#message").css('display','block');

			    	setTimeout(function() {
				        jQuery("#message").css('display','none');
				    }, 5000);
			    }
	    	}
	    }
	}
});