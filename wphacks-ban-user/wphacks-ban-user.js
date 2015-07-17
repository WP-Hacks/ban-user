jQuery('body').on('click',".wphacks-row-action-link-ban-user",function(event){
//jQuery(".wphacks-row-action-link-ban-user").click(function(event){
	event.preventDefault();
	var sourceEle = this;
	var href = jQuery(this).attr('href');
	var data = {
		'action': 'wphacks_parse_url',
		'url': href,
	}
	jQuery.get(ajaxurl,data, function(response){
		if(response.success){
			if(response.data._get._wpnonce){
				var nonce = response.data._get._wpnonce;
				var user_id = response.data._get.user_id;
				var data = {
					'action': 'wphacks_ban_user',
					'user_id': user_id,
					'_wpnonce': nonce,
				};
				jQuery.get(ajaxurl,data,function(response){
					if(response.success){
						wphacks_toast("User banned");
						jQuery(sourceEle).parent().replaceWith(response.data);
					} else {
						wphacks_toast("Banning User Failed");
					}
				});
			}
		}
	});
});

jQuery('body').on('click',".wphacks-row-action-link-unban-user",function(event){
//jQuery(".wphacks-row-action-link-unban-user").click(function(event){
	event.preventDefault();
	var sourceEle = this;
	var href = jQuery(this).attr('href');
	var data = {
		'action': 'wphacks_parse_url',
		'url': href,
	}
	jQuery.get(ajaxurl,data, function(response){
		if(response.success){
			if(response.data._get._wpnonce){
				var nonce = response.data._get._wpnonce;
				var user_id = response.data._get.user_id;
				var data = {
					'action': 'wphacks_unban_user',
					'user_id': user_id,
					'_wpnonce': nonce,
				};
				jQuery.get(ajaxurl,data,function(response){
					if(response.success){
						wphacks_toast("User Unbanned");
						jQuery(sourceEle).parent().replaceWith(response.data);
					} else {
						wphacks_toast("Unbanning User Failed");
					}
				});
			}
		}
	});
});

function wphacks_toast(message){
	jQuery('<div class="wpcrm-toast toast-bottom" style="z-index:1000000;position: fixed;left:50%;width: 200px;height: auto;margin-left:-100px;font-size: 12px;background-color: #303437;color: #F5F5F5;text-align: center;padding: 5px;border-radius: 2px;opacity: 0.95;box-shadow: 1px 2px 2px 1px #222;bottom: 5%;">'+ message +'</div>').hide().appendTo("body").fadeIn(1000).delay(5000).fadeOut(1000);
}
